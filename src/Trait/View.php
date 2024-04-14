<?php

namespace R3m\Io\Node\Trait;

use Exception;
use R3m\Io\App;
use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;
use R3m\Io\Node\Service\Security;

Trait View {

    /**
     * @throws Exception
     */
    public function view_create($class, $role, $options=[])
    {
        $name = Controller::name($class);
        $object = $this->object();

        /**
         * need
         */

        $url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        $data = $object->data_read($url);
        if($data){
            d(count($data->data()));
        }
        ddd($options);
    }
}