<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Node\Service\Security;

Trait Import {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function import($class, $role, $options=[]): array
    {
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('url', $options)){
            return [];
        }
        if(!File::exist($options['url'])){
            return [];
        }
        set_time_limit(0);
        $start = microtime(true);
        $options['function'] = __FUNCTION__;
        $options['relation'] = false;
        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return [];
        }
        $object = $this->object();
        $app_options = App::options($object);
        if(property_exists($app_options, 'force')){
            $options['force'] = $app_options->force;
        }
        if(property_exists($app_options, 'disable-validation')){
            $options['validation'] = false;
        } else {
            $options['validation'] = true;
        }
        if(property_exists($app_options, 'disable-expose')){
            $options['expose'] = false;
        } else {
            $options['expose'] = true;
        }
        $data = false;
        $index = 0;
        $result = [
            'list' => [],
            'count' => 0,
            'error' => [
                'list' => [],
                'count' => 0
            ]
        ];
        $data = $object->data_read($options['url']);

        /**
         * route imports
         */

        if($data){
            $list = $data->data();
            $priority = $this->record(
                $class,
                $role,
                [
                    'sort' =>
                        [
                            'priority' => Sort::DESC
                        ]
                ]
            );
            if(
                array_key_exists('node', $priority) &&
                property_exists($priority['node'], 'priority')
            ) {
                $priority = $priority['node']->priority;
            } else {
                $priority = 1000;
            }
            d('test');
            ddd($priority);
            foreach($list as $name => $record){
                if(property_exists($record, 'resource')){
                    continue;
                }
                $node = new Storage();
                $node->set('name', $name);
                if(property_exists($record, 'host')){
                    $host = $record->host;
                    if(is_array($host) && array_key_exists(0, $host)){
                        $host = $host[0];
                        $node->set('host', $host);
                    }
                }
                if(property_exists($record, 'controller')){
                    $node->set('controller', $record->controller);
                }
                if(property_exists($record, 'method')){
                    $node->set('method', $record->method);
                }
                if(property_exists($record, 'request')){
                    $node->set('request', $record->method);
                }
                if(property_exists($record, 'path')){
                    $node->set('path', $record->path);
                }
                if(property_exists($record, 'url')){
                    $node->set('url', $record->url);
                }
                if(property_exists($record, 'redirect')){
                    $node->set('redirect', $record->redirect);
                }
                $node->set('priority', $priority);
                $options_create = [];
                $response = $this->create($class, $role, $node, $options_create);
                $priority++;
                d($response);
            }
        }
        ddd('end');


        if($data) {
            $create_many_count = 0;
            $put_many_count = 0;
            $create_many = [];
            $put_many = [];
            $counter = 0;
            $list = $data->data($class);
            if(empty($list)){
                return [];
            }
            $total = count($list);
            foreach ($data->data($class) as $key => $record) {
                $uuid = false;
                if (
                    is_array($record) &&
                    array_key_exists('uuid', $record)
                ) {
                    $uuid = $record['uuid'];
                } elseif (
                    is_object($record) &&
                    property_exists($record, 'uuid')
                ) {
                    $uuid = $record->uuid;
                }
                if(
                    property_exists($app_options, 'is_new') &&
                    $app_options->is_new === true
                ){
                    $uuid = false;
                }
                if ($uuid) {
                    $response = $this->read(
                        $class,
                        $role,
                        [
                            'uuid' => $uuid ,
                            'ramdisk' => true
                        ]
                    );
                    if (!$response) {
                        $create_many[] = $record;
                        $create_many_count++;
                    } else {
                        $put_many[] = $record;
                        $put_many_count++;
                    }
                } else {
                    $create_many[] = $record;
                    $create_many_count++;
                }
            }
            $i = 0;
            while($i < $create_many_count){
                $temp = array_slice($create_many, $i, 1000, true);
                $length = count($temp);
                $object->logger($object->config('project.log.node'))->info('Count: ' . $length . ' / ' . $create_many_count . ' Start: ' . $i . ' Offset: ' . $options['offset']);
                $create_many_response = $this->create_many($class, $role, $temp, $options);
                foreach ($create_many_response['list'] as $nr => $uuid) {
                    $result['list'][] = $uuid;
                    $index++;
                }
                $duration = microtime(true) - $start;
                $duration_per_item = $duration / $length;
                $item_per_second = 1 / $duration_per_item;
                $object->logger($object->config('project.log.node'))->info('Items (create_many) per second: ' . $item_per_second);
                $start = microtime(true);
                $result['count'] += $create_many_response['count'];
                if(array_key_exists('error', $create_many_response)){
                    foreach ($create_many_response['error']['list'] as $nr => $record) {
                        $result['error']['list'][] = $record;
                    }
                    $result['error']['count'] += $create_many_response['error']['count'];
                }
//                echo 'Create: ' . $i . '/' . $create_many_count . PHP_EOL;
                $i = $i + 1000;
            }
            $i =0;
            $start = microtime(true);
            while($i < $put_many_count){
                $temp = array_slice($put_many, $i, 1000, true);
                $length = count($temp);
                $put_options = $options;
//                $put_options['ramdisk'] = true;
                $put_many_response = $this->put_many($class, $role, $temp, $put_options);
                foreach ($put_many_response['list'] as $nr => $record) {
                    $result['list'][] = $record;
                    $index++;
                }
                if($object->config('project.log.node')){
                    $duration = microtime(true) - $start;
                    $duration_per_item = $duration / $length;
                    $item_per_second = 1 / $duration_per_item;
                    $object->logger($object->config('project.log.node'))->info('Items (put_many) per second: ' . $item_per_second);
                    $start = microtime(true);
                }
                $result['count'] += $put_many_response['count'];
                if(array_key_exists('error', $put_many_response)) {
                    if(array_key_exists('list', $put_many_response['error'])){
                        foreach ($put_many_response['error']['list'] as $nr => $record) {
                            $result['error']['list'][] = $record;
                        }
                    }
                    if(array_key_exists('uuid', $put_many_response['error'])){
                        foreach ($put_many_response['error']['uuid'] as $nr => $record) {
                            $result['error']['uuid'][] = $record;
                        }
                    }
                    $result['error']['count'] += $put_many_response['error']['count'];
                }
//                echo 'Update: ' . $i . '/' . $put_many_count . PHP_EOL;
                $i = $i + 1000;
            }
        }
        if($result['error']['count'] === 0){
            unset($result['error']);
        }
        $this->commit($class, $role, $result, $options);
        return $result;
    }
}
