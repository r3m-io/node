<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Config;

trait Role {

    public function role_system()
    {
        $object = $this->object();
        if(
            in_array(
                $object->config(Config::POSIX_ID),
            [
                0,
                33,     //remove this, how to handle www-data events, middleware and filter
            ]
            )
        ){
            $url = $object->config('project.dir.data') . 'Account' . $object->config('ds') . 'Role.System.json';
            $data = $object->data_read($url);
            if($data){
                return $data->data();
            }
            return false;
        }
    }
}