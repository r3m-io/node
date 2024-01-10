<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Core;
use R3m\Io\Module\Controller;

use R3m\Io\Node\Service\Security;

use Exception;

use R3m\Io\Exception\ObjectException;

trait Record {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function record($class, $role, $options=[]): ?array
    {
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
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
        if(!array_key_exists('sort', $options)){
            $options['sort'] = [
                'uuid' => 'ASC'
            ];
        }
        $response = $this->list($name, $role, $options);
        if(
            is_array($response) &&
            array_key_exists('list', $response) &&
            array_key_exists(0, $response['list'])
        ){
            $record = $response;
            $record['node'] = $response['list'][0];
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