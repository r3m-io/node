<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;

use R3m\Io\Node\Service\Security;

use Exception;

trait Property {

    /**
     * @throws Exception
     */
    public function property_delete($class, $role, $node=[], $options=[]): false|array
    {
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        $name = Controller::name($class);
        $object = $this->object();
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('import', $options)){
            $options['import'] = false;
        }
        $options['relation'] = false;
        if(!Security::is_granted(
            $class,
            $role,
            $options
        )){
            return false;
        }
        d($options);
        ddd($node);
        return false;
    }
}