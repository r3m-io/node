<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\Module\Controller;

use Exception;

use R3m\Io\Exception\ObjectException;

trait Compact {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function compact($class, $role, $options = []): bool | int
    {
        $object = $this->object();
        $name = Controller::name($class);
        $url_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json');

        $data = $object->data_read($url_data);
        if($data){
            return $data->write($url_data, [
                'compact' => true
            ]);
        }
        return false;
    }
}