<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Config;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;

use R3m\Io\Node\Service\Security;

use Exception;

Trait Create {

    /**
     * @throws Exception
     */
    public function create($class, $role, $node=[], $options=[]): false|array
    {
        $name = Controller::name($class);
        $object = $this->object();
        if(
            is_object($node) &&
            get_class($node) === Storage::class
        ){
            $node = $node->data();
        } else {
            $node = Core::object($node, Core::OBJECT_OBJECT);
        }
        $object->request('node', $node);
        $object->request('node.uuid', Core::uuid());
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
                    $node = $this->expose(
                        $node,
                        $expose,
                        $name,
                        __FUNCTION__,
                        $role
                    );
                    $data = $object->data_read($url);
                    if(!$data){
                        $data = new Storage();
                    }
                    $list = $data->get($name);
                    if(empty($list)){
                        $list = [];
                    }
                    $record = $node->data();
                    if(Core::object_is_empty($record)){
                        throw new Exception('Empty node after expose...');
                    }
                    $list[] = $node->data();
                    $data->set($name, $list);
                    $data->write($url);
                    $response['node'] = $node->data();
                    $this->sync_file([
                        'dir_data' => $dir_data,
                        'url' => $url,
                    ]);
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
    public function create_many($class, $role, $nodeList=[], $options=[]): false|array
    {
        $name = Controller::name($class);
        $object = $this->object();
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('transaction', $options)){
            $options['transaction'] = false;
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return false;
        }
        $this->lock($name, $options);
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
        $list = [];
        $result = [];
        $error = [];
        foreach($nodeList as $nr => $node){
            if(
                is_object($node) &&
                get_class($node) === Storage::class
            ){
                $node = $node->data();
            } else {
                $node = Core::object($node, Core::OBJECT_OBJECT);
            }
            $object->request('node', $node);
            $object->request('node.uuid', Core::uuid());
            $object->request('node.#class', $name);
            if(
                array_key_exists('validation', $options) &&
                $options['validation'] === false
            ){
                $validate = (object) ['success' => true];
            } else {
                $validate = $this->validate($object, $validate_url,  $name . '.create');
            }
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
                        $node = $this->expose(
                            $node,
                            $expose,
                            $name,
                            __FUNCTION__,
                            $role
                        );
                        $record = $node->data();
                        if(Core::object_is_empty($record)){
                            throw new Exception('Empty node after expose...');
                        }
                        $list[] = $record;
                        $result[] = $record->uuid;
                    }
                } else {
                    $error[] = $validate->test;
                }
            }
        }
        if(empty($list)){
            $this->unlock($name, $options);
            return false;
        }
        if(!empty($error)){
            $response = [];
            $response['error'] = $error;
            $this->unlock($name, $options);
            return $response;
        }
        if($options['transaction'] === true){
            $data = $object->data_read($url, sha1($url));
        } else {
            $data = $object->data_read($url);
        }
        if(!$data){
            $data = new Storage();
        } else {
            $original = $data->get($name);
            if(is_array($original)){
                $list = array_merge($original, $list);
            }
        }
        $data->set($name, $list);
        $response = [];
        $response['list'] = $result;
        if ($options['transaction'] === true){
            $object->data(sha1($url), $data);
            $response['transaction'] = true;
        } else {
            $write = $data->write($url);
            $this->sync_file([
                'dir_data' => $dir_data,
                'url' => $url,
            ]);
            $response['byte'] = $write;
            $response['transaction'] = false;
        }
        $response['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
        $this->unlock($name, $options);
        return $response;
    }
}