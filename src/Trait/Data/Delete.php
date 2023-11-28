<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;

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
        if(!is_array($data)){
            throw new Exception('Data corrupted?');
        }
        foreach($data as $nr => $record){
            if(
                is_object($record) &&
                property_exists($record, 'uuid')
            ){
                if($record->uuid === $options['uuid']){
                    unset($data[$nr]);
                    $data->write($url);
                    return true;
                }
            }
        }
        return false;
    }
}