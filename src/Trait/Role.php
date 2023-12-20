<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
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

    public function role_system_create($package=''): void
    {
        $object = $this->object();
        if($object->config(Config::POSIX_ID) === 0){
            $url = $object->config('project.dir.data') . 'Account' . $object->config('ds') . 'Role.System.json';
            $url_package = $object->config('project.dir.vendor') . $package . '/Data/Role.System.json';
            if(File::exist($url_package)){
                if(File::exist($url)){
                    $data = $object->data_read($url);
                    $data_package = $object->data_read($url_package);
                    if($data && $data_package){
                        $name = $data_package->get('name');
                        if($data->get('name') === $name){
                            $permissions = $data->get('permission');
                            $package_permissions = $data_package->get('permission');
                            if($package_permissions){
                                foreach($package_permissions as $permission){
                                    if(!in_array($permission, $permissions, true)){
                                        $permissions[] = $permission;
                                    }
                                }
                            }
                            $uuid = $data->get('uuid');
                            if(empty($uuid)){
                                $data->set('uuid', Core::uuid());
                            }
                            $data->set('permission', $permissions);
                            $data->write($url);
                            $command = 'chown www-data:www-data ' . $url;
                            exec($command);
                            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                                $command = 'chmod 666 ' . $url;
                                exec($command);
                            }
                        }
                    };
                } else {
                    $data = new Storage();
                    $data_package = $object->data_read($url_package);
                    if($data_package){
                        $data->data($data_package->data());
                        $dir = Dir::name($url);
                        Dir::create($dir, Dir::CHMOD);
                        $data->write($url);
                        $command = 'chown www-data:www-data ' . $object->config('project.dir.data');
                        exec($command);
                        $command = 'chown www-data:www-data ' . $dir;
                        exec($command);
                        $command = 'chown www-data:www-data ' . $url;
                        exec($command);
                        if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                            $command = 'chmod 777 ' . $object->config('project.dir.data');
                            exec($command);
                            $command = 'chmod 777 ' . $dir;
                            exec($command);
                            $command = 'chmod 666 ' . $url;
                            exec($command);
                        }
                    }
                }
            }
        }
    }
}