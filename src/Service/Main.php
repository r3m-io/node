<?php

namespace R3m\Io\Node\Service;

use R3m\Io\App;
use R3m\Io\Module\Core;
use R3m\Io\Module\Validate;

use Exception;

use R3m\Io\Exception\ObjectException;
use R3m\Io\Exception\FileWriteException;

class Main {

    const API = 'api';
    const ADMIN = 'admin';

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    protected static function validate(App $object, $url, $type): object | false
    {
        $data = $object->data(sha1($url));
        if($data === null){
            $data = $object->parse_read($url, sha1($url));
        }
        if($data){
            $validate = $data->data($type . '.validate');
            if(empty($validate)){
                return false;
            }
            return Validate::validate($object, $validate);
        }
        return false;
    }

    /**
     * @throws ObjectException
     */
    protected static function castValue($array=[]){
        if(is_array($array)){
            foreach($array as $key => $value) {
                if(is_object($value) || is_array($value)){
                    $array[$key] = Entity::castValue($value);
                } else {
                    if($value === 'null'){
                        $array[$key] = null;
                    }
                    elseif($value === 'true'){
                        $array[$key] = true;
                    }
                    elseif($value === 'false'){
                        $array[$key] = false;
                    }
                    elseif(is_numeric($value)){
                        $array[$key] = $value + 0;
                    }
                    elseif(substr($value, 0, 1) === '[' && substr($value, -1, 1) === ']'){
                        $array[$key] = Core::object($value, Core::OBJECT_ARRAY);
                    }
                }
            }
            return $array;
        }
        elseif(is_object($array)){
            foreach($array as $key => $value) {
                if(is_object($value) || is_array($value)){
                    $array->$key = Entity::castValue($value);
                } else {
                    if($value === 'null'){
                        $array->$key = null;
                    }
                    elseif($value === 'true'){
                        $array->$key = true;
                    }
                    elseif($value === 'false'){
                        $array->$key = false;
                    }
                    elseif(is_numeric($value)){
                        $array->$key = $value + 0;
                    }
                    elseif(substr($value, 0, 1) === '[' && substr($value, -1, 1) === ']'){
                        $array->$key = Core::object($value, Core::OBJECT_ARRAY);
                    }
                }
            }
            return $array;
        }
        elseif($array === 'null'){
            return null;
        }
        elseif($array === 'true'){
            return true;
        }
        elseif($array === 'false'){
            return false;
        }
        elseif(is_numeric($array)){
            return $array + 0;
        }
        elseif(substr($array, 0, 1) === '[' && substr($array, -1, 1) === ']'){
            return Core::object($array, Core::OBJECT_ARRAY);
        }
        else {
            return $array;
        }
    }
}