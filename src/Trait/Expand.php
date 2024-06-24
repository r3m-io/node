<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\Module\Controller;
use R3m\Io\Module\File;

use Exception;

use R3m\Io\Exception\ObjectException;

trait Expand {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function expand($class, $role, $options = []): bool | int
    {
        $object = $this->object();
        $name = Controller::name($class);
        $url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        $data = $object->data_read($url);
        if($data){
            $count = count($data->data($name));
            $byte = $data->write($url);
            return [
                'count' => $count,
                'byte' => $byte,
                'size' => File::size_calculation($byte)
            ];
        }
        return false;
    }
}