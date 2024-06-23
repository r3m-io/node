<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\Module\Controller;

use Exception;

use R3m\Io\Exception\ObjectException;

trait Expand {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function expand($class, $role, $options = []){
        $object = $this->object();
        $name = Controller::name($class);
        $url_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        $data = $object->data_read($url_data);
        if($data){
            $data->write($url_data);
        }
    }
}