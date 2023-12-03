<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;

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
        $object->request('node', (object) $node);
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
        ddd($validate);
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
}