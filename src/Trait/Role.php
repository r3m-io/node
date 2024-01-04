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
                if(property_exists($data, 'uuid')){
                    d('yes');
                    $object->config('framework.role.system.uuid', $data->uuid);
                }
                return $data->data();
            }
            return false;
        }
    }

    public function role_has_permission($role, $permission=''): bool
    {
        $list = [];
        $object = $this->object();
        if(property_exists($role, 'uuid')){
            $list = $object->data($role->uuid);
            if(empty($list)){
                $list = [];
                foreach ($role->permission as $record){
                    if(
                        is_object($record) &&
                        property_exists($record, 'name')
                    ){
                        $list[$record->name] = true;
                    }
                }
                $object->data($role->uuid, $list);
            }
        }
        if(array_key_exists($permission, $list)){
            return true;
        }
        return false;
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
                            $list = [];
                            foreach($permissions as $nr => $permission){
                                if(
                                    is_object($permission) &&
                                    property_exists($permission, 'name')
                                ){
                                    $list[] = $permission->name;
                                }
                            }
                            $package_permissions = $data_package->get('permission');
                            if($package_permissions){
                                foreach($package_permissions as $permission){
                                    if(
                                        is_object($permission) &&
                                        property_exists($permission, 'name') &&
                                        !in_array($permission->name, $list, true)
                                    ){
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
                            File::permission($object, [
                                'url' => $url
                            ]);
                        }
                    };
                } else {
                    $data = new Storage();
                    $data_package = $object->data_read($url_package);
                    if($data_package){
                        $data->data($data_package->data());
                        $dir = Dir::name($url);
                        Dir::create($dir, Dir::CHMOD);
                        $uuid = $data->get('uuid');
                        if(empty($uuid)){
                            $data->set('uuid', Core::uuid());
                        }
                        $data->write($url);
                        File::permission($object, [
                            'dir_data' => $object->config('project.dir.data'),
                            'dir' => $dir,
                            'url' => $url
                        ]);
                    }
                }
            }
        }
    }
}