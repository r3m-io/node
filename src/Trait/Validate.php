<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;

use R3m\Io\Module\Core;
use R3m\Io\Module\Validate as Module;

use Exception;

trait Validate {

    /**
     * @throws Exception
     */
    protected function validate(App $object, $url, $type, $function=''): object | false
    {
        $data = $object->data(sha1($url));
        if($data === null){
            $data = $object->parse_read($url, sha1($url));
        }
        if($data){
            $clone = $data->data($type . '.validate');
            if(is_object($clone)){
                $validate = clone $clone;
                if(Core::object_is_empty($validate)){
                    throw new Exception('No validation found for ' . $type . ' in ' . $url . '.');
                }
                return Module::validate($object, $validate, false, $function);
            } else {
                throw new Exception('No validation found for ' . $type . ' in ' . $url . '.');
            }
        }
        return false;
    }
}