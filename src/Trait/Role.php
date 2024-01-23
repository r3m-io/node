<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;

use Exception;

use R3m\Io\Exception\DirectoryCreateException;
use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

trait Role {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function role_system(): false | object
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
                $object->config('framework.role.system.uuid', $data->get('uuid'));
                return $data->data();
            }
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function role_has_permission($role, $permission=''): bool
    {
        if(
            property_exists($role, 'uuid') &&
            property_exists($role, 'permission') &&
            (
                is_array($role->permission) ||
                is_object($role->permission)
            )
        ){
            foreach ($role->permission as $record){
                if(
                    is_object($record) &&
                    property_exists($record, 'name') &&
                    $permission === $record->name
                ){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws FileWriteException
     * @throws Exception
     */
    public function role_system_create($package=''): void
    {
        $object = $this->object();
        $skip = 0;
        if($object->config(Config::POSIX_ID) === 0){
            $url = $object->config('project.dir.data') . 'Account' . $object->config('ds') . 'Role.System.json';
            $url_package = $object->config('project.dir.vendor') . $package . '/Data/Role.System.json';
            d($url);
            d($url_package);
            if(File::exist($url_package)){
                if(File::exist($url)){
                    $data = $object->data_read($url);
                    $data_package = $object->data_read($url_package);
                    if($data && $data_package){
                        $name = $data_package->get('name');
                        d($name);
                        d($data->get('name'));
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
                            d($package_permissions);
                            if($package_permissions){
                                foreach($package_permissions as $permission){
                                    if(
                                        is_object($permission) &&
                                        property_exists($permission, 'name')
                                    ){
                                        if(!in_array($permission->name, $list, true)){
                                            $permissions[] = $permission;
                                        } else {
                                            $skip++;
                                        }
                                    }
                                }
                            }
                            d($skip);
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