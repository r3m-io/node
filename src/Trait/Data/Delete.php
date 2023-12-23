<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\File;

use R3m\Io\Node\Service\Security;

use Exception;

trait Delete {

    /**
     * @throws Exception
     */
    public function delete($class, $role, $options=[]): bool
    {
        $name = Controller::name($class);
        d($name);
        d($options);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        d($options);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('uuid', $options)){
            d(1);
            return false;
        }
        if(!Core::is_uuid($options['uuid'])){
            d(2);
            return false;
        }
        ddd('here');
        $options['relation'] = false;
        if(!Security::is_granted(
            $name,
            $role,
            $options
        )){
            d(3);
            return false;
        }
        $object = $this->object();
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url = $dir_data . $name . $object->config('extension.json');
        $data = $object->data_read($url);
        if(!$data){
            d(4);
            return true;
        }
        $list = $data->data($name);
        if(!$list){
            d(5);
            return true;
        }
        ddd($list);
        foreach($list as $nr => $record){
            if(
                is_object($record) &&
                property_exists($record, 'uuid')
            ){
                if($record->uuid === $options['uuid']){
                    if(is_array($list)){
                        unset($list[$nr]);
                    }
                    elseif(is_object($list)){
                        unset($list->{$nr});
                    }
                    if(empty($list)){
                        File::delete($url);
                    } else {
                        if(is_object($list)){
                            $list = (array) $list;
                        }
                        $list = array_values($list);
                        $data->data($name, $list);
                        $data->write($url);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function delete_many($class, $role, $options=[]): array
    {
        $result = [];
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('uuid', $options)){
            return $result;
        }
        $uuid = $options['uuid'];
        if(!is_array($uuid)){
            return $result;
        }
        foreach($uuid as $nr => $value){
            if(!Core::is_uuid($value)){
                return $result;
            }
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $name,
            $role,
            $options
        )){
            return $result;
        }
        $object = $this->object();
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url = $dir_data . $name . $object->config('extension.json');
        $data = $object->data_read($url);
        if(!$data){
            return $result;
        }
        $list = $data->data($name);
        if(!$list){
            return $result;
        }
        $is_found = false;
        foreach($uuid as $uuid_nr => $uuid_value){
            $result[$uuid_value] = false;
            foreach($list as $nr => $record){
                if(
                    is_object($record) &&
                    property_exists($record, 'uuid')
                ){
                    if($record->uuid === $uuid_value){
                        $is_found = true;
                        unset($uuid[$uuid_nr]);
                        if(is_array($list)){
                            unset($list[$nr]);
                        }
                        elseif(is_object($list)){
                            unset($list->{$nr});
                        }
                        $result[$uuid_value] = true;
                    }
                }
            }
        }
        if($is_found){
            if(empty($list)){
                File::delete($url);
            } else {
                if(is_object($list)){
                    $list = (array) $list;
                }
                $list = array_values($list);
                $data->data($name, $list);
                $data->write($url);
            }
        }
        return $result;
    }
}