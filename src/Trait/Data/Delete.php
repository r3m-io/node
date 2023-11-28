<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\File;

use R3m\Io\Node\Service\Security;

use Exception;

Trait Delete {

    /**
     * @throws Exception
     */
    public function delete($class, $role, $options=[]): bool
    {
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('uuid', $options)){
            return false;
        }
        if(!Core::is_uuid($options['uuid'])){
            return false;
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $name,
            $role,
            $options
        )){
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
            return true;
        }
        $list = $data->data($name);
        if(!$list){
            return true;
        }
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
}