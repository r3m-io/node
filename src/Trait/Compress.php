<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\Module\Controller;
use R3m\Io\Module\File;

use Exception;

use R3m\Io\Exception\ObjectException;

trait Compress {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function compress($class, $role, $options = []): array | bool
    {
        $object = $this->object();
        $name = Controller::name($class);
        $url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json');

        $data = $object->data_read($url);
        if($data){
            $count = count($data->data($name));
            $byte = $data->write($url, [
                'compact' => true,
                'compress' => true
            ]);
            return [
                'count' => $count,
                'byte' => $byte,
                'size' => File::size_format($byte)
            ];
        }
        return false;
    }
}