<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;

use R3m\Io\Node\Service\Security;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
Trait Read {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function read($class, $role, $options=[]): false|array|object
    {
        $object = $this->object();
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('uuid', $options)){
            return false;
        }
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('relation', $options)){
            $options['relation'] = false;
        }
        if(!array_key_exists('parse', $options)){
            $options['parse'] = false;
        }
        $options_record = [
            'filter' => [
                "uuid" => [
                    'operator' => '===',
                    'value' => $options['uuid'],
                ]
            ],
            'function' => $options['function'],
            'relation' => $options['relation'],
            'parse' => $options['parse'],
        ];

        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return false;
        }
        $ramdisk_record = $object->config('package.r3m_io/node.ramdisk');
        if(empty($ramdisk_record)){
            $ramdisk_record = [];
        }
        if(in_array(
            $name,
            $ramdisk_record,
            true
        )){
            $options_record['ramdisk'] = true;
        }
        $data = $this->record($name, $role, $options_record);
        if($data){
            return $data;
        }
        return false;
    }
}