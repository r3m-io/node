<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;

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
                ],
                true
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

    public function role_system_create(): void
    {
        $object = $this->object();
        if($object->config(Config::POSIX_ID) === 0){
            $url = $object->config('project.dir.data') . 'Account' . $object->config('ds') . 'Role.System.json';
            $url_route = $object->config('project.dir.vendor') . 'r3m_io/route/Data/Role.System.json';

            if(File::exist($url_route)){
                if(File::exist($url)){

                } else {
                    $url = new Storage();
                    $data_route = $object->data_read($url_route);
                    if($data_route){
                        ddd($data_route);
                    }
                    //create url;
                }
            }


            d($url_route);
            ddd($url);
        }
    }
}