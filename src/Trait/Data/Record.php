<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Core;
use R3m\Io\Module\Controller;

use R3m\Io\Node\Service\Security;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

Trait Record {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function record($class, $role, $options=[]): ?array
    {
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $options['limit'] = 1;
        $options['page'] = 1;
        ddd($options);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!Security::is_granted(
            $name,
            $role,
            $options
        )){
            return null;
        }
        if(!array_key_exists('sort', $options)){
            $options['sort'] = [
                'uuid' => 'ASC'
            ];
        }
        $list = $this->list($name, $role, $options);
        if(
            is_array($list) &&
            array_key_exists('list', $list) &&
            array_key_exists(0, $list['list'])
        ){
            $record = $list;
            $record['node'] = $list['list'][0];
            if(property_exists($record['node'], '#index')){
                unset($record['node']->{'#index'});
            }
            unset($record['max']);
            unset($record['sort']);
            unset($record['list']);
            unset($record['page']);
            unset($record['limit']);
            unset($record['count']);
            return $record;
        }
        return null;
    }

}