<?php

namespace R3m\Io\Node\Trait\Data;

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
        $options['limit'] = 1;
        $options['page'] = 1;
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
        $list = $this->list($name, $role, $options);
        if(
            is_array($list) &&
            array_key_exists('list', $list) &&
            array_key_exists(0, $list['list'])
        ){
            $record = $list;
            $record['node'] = $list['list'][0];
            unset($record['list']);
            unset($record['page']);
            unset($record['limit']);
            unset($record['count']);
            return $record;
        }
        return null;
    }

}