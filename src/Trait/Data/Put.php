<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;

use R3m\Io\Node\Service\Security;

use Exception;

Trait Put {

    /**
     * @throws Exception
     */
    public function put($class, $role, $node=[], $options=[]): false|array
    {
        $name = Controller::name($class);
        if(
            is_object($node) &&
            get_class($node) === Storage::class
        ){
            $node = $node->data();
        } else {
            $node = Core::object($node, Core::OBJECT_OBJECT);
        }
        $object = $this->object();
        $object->request('node', $node);
        $object->request('node.#class', $name);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return false;
        }
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url = $dir_data .
            $name .
            $object->config('extension.json')
        ;
        $dir_validate = $object->config('project.dir.node') .
            'Validate'.
            $object->config('ds')
        ;
        $validate_url =
            $dir_validate .
            $name .
            $object->config('extension.json');

        $data = $object->data_read($url);
        if(!$data){
            return false;
        }
        $list = $data->get($name);
        if(empty($list)){
            return false;
        }
        $is_found = false;
        foreach($list as $nr => $record){
            if(
                is_object($record) &&
                property_exists($record, 'uuid') &&
                $record->uuid === $object->request('node.uuid')
            ){
                $is_found = $nr;
                break;
            }
        }
        if($is_found === false){
            return false;
        }
        if(
            array_key_exists('validation', $options) &&
            $options['validation'] === false
        ){
            $validate = (object) ['success' => true];
        } else {
            $validate = $this->validate($object, $validate_url,  $name . '.create');
        }
        $response = [];
        if($validate) {
            if ($validate->success === true) {
                $expose = $this->expose_get(
                    $object,
                    $name,
                    $name . '.' . __FUNCTION__ . '.expose'
                );
                $node = new Storage();
                $node->data($object->request('node'));
                $node->set('#class', $name);
                if (
                    $expose &&
                    $role
                ) {
                    $record = $this->expose(
                        $node,
                        $expose,
                        $name,
                        __FUNCTION__,
                        $role
                    );
                    $list[$is_found] = $record->data();
                    $data->set($name, $list);
                    $data->write($url);
                    $response['node'] = $record->data();
                }
            } else {
                $response['error'] = $validate->test;
            }
        }
        return $response;
    }

    /**
     * @throws Exception
     */
    public function put_many($class, $role, $nodeList=[], $options=[]){
        $name = Controller::name($class);
        $object = $this->object();
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return false;
        }
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url = $dir_data .
            $name .
            $object->config('extension.json')
        ;
        $dir_validate = $object->config('project.dir.node') .
            'Validate'.
            $object->config('ds')
        ;
        $validate_url =
            $dir_validate .
            $name .
            $object->config('extension.json');

        $start = microtime(true);
        $data = $object->data_read($url, sha1($url));
        if($data && $data->has($name)){
            $list = $data->get($name);
        } else {
            $list = [];
        }
        $uuid = [];
        foreach($list as $nr => $record){
            $uuid[$record->uuid] = $nr;
        }
        $duration = (microtime(true) - $start) * 1000;
        $error = [];
        $result = [];
        foreach($nodeList as $nr => $record){
            if(property_exists($record, 'uuid')){
                if(array_key_exists($record->uuid, $uuid)){
                    $list_nr = $uuid[$record->uuid];
                    $list[$list_nr] = $record;
                    $result[] = $record->uuid;
                } else {
                    $error[] = $record;
                }
            }
        }
        if(!empty($error)){
            $response = [];
            $response['error'] = $error;
            return $response;
        }
        $data->set($name, $list);
        $write = $data->write($url);
        $duration = (microtime(true) - $start) * 1000;
        $response = [];
        $response['list'] = $result;
        $response['duration'] = $duration;
        $response['byte'] = $write;
        return $response;
    }
}