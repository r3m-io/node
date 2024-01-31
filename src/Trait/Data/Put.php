<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;

use R3m\Io\Node\Service\Security;

use Exception;

trait Put {

    /**
     * @throws Exception
     */
    public function put($class, $role, $node=[], $options=[]): false | array
    {
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        $nodeList = [$node];
        $response = $this->patch_many($class, $role, $nodeList, $options);
        return $this->single($response);
    }

    /**
     * @throws Exception
     */
    public function put_many($class, $role, $nodeList=[], $options=[]): false | array
    {
        $name = Controller::name($class);
        $object = $this->object();
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('import', $options)){
            $options['import'] = false;
        }
        if(!array_key_exists('uuid', $options)){
            $options['uuid'] = false;
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
            $object->request('node.#class', $name);
            if(
                array_key_exists('validation', $options) &&
                $options['validation'] === false
            ){
                $validate = (object) ['success' => true];
            } else {
                $validate = $this->validate($object, $validate_url,  $name . '.put', $options['function']);
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
                                if($options['import'] === true){
                                    $number = $object->config('r3m.io.node.import.list.number');
                                    if(empty($number)){
                                        $number = 1;
                                    } else {
                                        $number++;
                                    }
                                    $object->config('r3m.io.node.import.list.number', $number);
                                    $amount = $object->config('r3m.io.node.import.list.count');
                                    if($amount > 0){
                                        if($number === 1){
                                            echo 'Imported (PUT) ' . $number . ' of ' . $amount . ' nodes ('. round(($number / $amount) * 100 , 2) .' %)...' . PHP_EOL;
                                        }
                                        elseif($number % 10 === 0){
                                            if($number > 1){
                                                echo Cli::tput('cursor.up');
                                            }
                                            echo 'Imported (PUT) ' . $number . ' of ' . $amount . ' nodes ('. round(($number / $amount) * 100 , 2) .' %)...' . PHP_EOL;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $error[] = $validate->test;
                }
            }
        }
        if(!empty($error)){
            $response = [];
            $response['error'] = $error;
            if(
                $options['import'] === false &&
                empty($transaction)
            ){
                $this->unlock($class);
            }
            return $response;
        }
        $data->set($name, $list);
        $response = [];
        $response['list'] = $result;
        if($transaction === true){
            $object->data(sha1($url), $data);
            $response['transaction'] = true;
        } else {
            $write = $data->write($url);
            $response['byte'] = $write;
            $response['transaction'] = false;
            if($options['import'] === false){
                $this->unlock($class);
            }
        }
        $duration = (microtime(true) - $start) * 1000;
        $response['duration'] = $duration;
        return $response;
    }
}