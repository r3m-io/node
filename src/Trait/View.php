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

        $source = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        $data = $object->data_read($source);
        $list = [];
        if($data){
            $count = 0;
            foreach($data->data($name) as $record){
                $count++;
                $node = new Storage($record);
                $new = new Storage();
                foreach($options->attribute as $nr => $attribute){
                    $new->data($attribute, $node->data($attribute));
                }
                $list[] = $new->data();
            }
        }
        d(count($list));
        ddd($options);
    }
}