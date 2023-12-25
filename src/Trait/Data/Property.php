<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;

use R3m\Io\Node\Service\Security;

use Exception;

trait Property {

    /**
     * @throws Exception
     */
    public function property_delete($class, $role, $node=[], $options=[]): false|array
    {
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        $name = Controller::name($class);
        $object = $this->object();
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('import', $options)){
            $options['import'] = false;
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return false;
        }
        $transaction = $object->config('node.transaction.' . $name);
        if(
            $options['import'] === false &&
            empty($transaction)
        ){
            $this->lock($class, $options);
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
        if($transaction === true){
            $data = $object->data_read($url, sha1($url));
        } else {
            $data = $object->data_read($url);
        }
        if($data){
            $list = $data->get($name);
            if(!is_array($list)){
                throw new Exception('Array expected');
            }
        } else {
            $list = [];
        }
        $uuid = [];
        foreach($list as $nr => $record){
            $uuid[$record->uuid] = $nr;
        }
        $error = [];
        $result = [];
        if(
            is_object($node) &&
            get_class($node) === Storage::class
        ){
            $node = $node->data();
        } else {
            $node = Core::object($node, Core::OBJECT_OBJECT);
        }
        $record = (object) [];
        if(property_exists($node, 'uuid')){
            if(array_key_exists($node->uuid, $uuid)){
                $list_nr = $uuid[$node->uuid];
                $record = $list[$list_nr];
            }
        }
        ddd($record);

        /*
        $object->request('node', Core::object_merge($record, $node));
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
                    $name . '.' . $options['function'] . '.expose'
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
                        $options['function'],
                        $role
                    );
                    $record = $node->data();
                    if(Core::object_is_empty($record)){
                        throw new Exception('Empty node after expose...');
                    }
                    if(property_exists($record, 'uuid')){
                        if(array_key_exists($record->uuid, $uuid)){
                            $list_nr = $uuid[$record->uuid];
                            $list[$list_nr] = $record;
                            if(
                                array_key_exists('function', $options) &&
                                $options['function'] === __FUNCTION__
                            ){
                                $result[] = $record->uuid;
                            } else {
                                $result[] = $record;
                            }
                        }
                    }
                }
            } else {
                $error[] = $validate->test;
            }
        }
        */
        d($options);
        ddd($node);
        return false;
    }
}