<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;
use R3m\Io\Module\Parse;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Exception\AuthorizationException;

trait Compact {

    public function compact($class, $role, $options = []){
        $object = $this->object();
        $name = Controller::name($class);
        $url_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            '.json';
        ddd($url_data);
    }

}