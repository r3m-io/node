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
        $name = Controller::name($class);
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
        $skip = 0;
        $put = 0;
        $patch = 0;
        $create = 0;
        if(!Security::is_granted(
            $name,
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
        $data = $object->data_read($options['url']);

        /**
         * route imports
         */

        if($data){
            $list = $data->data($name);
            $priority = $this->record(
                $name,
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
                $priority = $priority['node']->priority + 1;
            } else {
                $priority = 2001;
            }
            $url_object = $object->config('project.dir.node') .
                'Object' .
                $object->config('ds') .
                $name .
                $object->config('extension.json')
            ;
            $data_object = $object->data_read($url_object);
            foreach($list as $record){
                if(property_exists($record, 'resource')){
                    continue;
                }
                $node = new Storage();
                $node->data($record);
                $node->delete('uuid');
                $node->set('priority', $priority);
                $record = false;
                if(
                    $data_object &&
                    $data_object->has('is.unique')
                ){
                    $unique = (array) $data_object->get('is.unique');
                    $unique = array_shift($unique);
                    $explode = explode(',', $unique);
                    $count = 0;
                    foreach($explode as $nr => $value){
                        $explode[$nr] = trim($value);
                        $count++;
                    }
                    switch ($count){
                        case 2:
                            if(
                                $node->has($explode[0]) &&
                                $node->has($explode[1])
                            ){
                                $record = $this->record(
                                    $name,
                                    $role,
                                    [
                                        'filter' => [
                                            $explode[0] => [
                                                'value' => $node->get($explode[0]),
                                                'operator' => '==='
                                            ],
                                            $explode[1] => [
                                                'value' => $node->get($explode[1]),
                                                'operator' => '==='
                                            ]
                                        ]
                                    ]
                                );
                            }
                        break;
                        case 1:
                            if($node->has($explode[0])){
                                $record = $this->record(
                                    $name,
                                    $role,
                                    [
                                        'filter' => [
                                            $explode[0] => [
                                                'value' => $node->get($explode[0]),
                                                'operator' => '==='
                                            ]
                                        ]
                                    ]
                                );
                            }
                        break;
                    }
                }
                if($record){
                    if(
                        array_key_exists('force', $options) &&
                        $options['force'] === true &&
                        array_key_exists('node', $record) &&
                        property_exists($record['node'], 'uuid') &&
                        !empty($record['node']->uuid)
                    ){
                        $options_put = [];
                        $node->set('uuid', $record['node']->uuid);
                        $response = $this->put($class, $role, $node->data(), $options_put);
                        d($response);
                        $put++;
                    }
                    elseif(
                        array_key_exists('patch', $options) &&
                        $options['patch'] === true &&
                        array_key_exists('node', $record) &&
                        property_exists($record['node'], 'uuid') &&
                        !empty($record['node']->uuid)
                    ){
                        $options_patch = [];
                        $node->set('uuid', $record['node']->uuid);
                        $response = $this->patch($class, $role, $node->data(), $options_patch);
                        d($response);
                        $patch++;
                    } else {
                        $skip++;
                    }
                } else {
                    $options_create = [];
                    $response = $this->create($class, $role, $node, $options_create);
                    $create++;
                }
                $priority++;
            }
        }
        return [
            'skip' => $skip,
            'put' => $put,
            'patch' => $patch,
            'create' => $create,
            'duration' => (microtime(true) - $start) * 1000
        ];
    }
}
