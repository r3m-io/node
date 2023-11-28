<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Module\Cache;
use SplFileObject;

use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\File;

use R3m\Io\Node\Service\Security;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

Trait Count {
    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function count($class, $role, $options=[]): int
    {
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $count = 0;
        $name = Controller::name($class);
        if(!array_key_exists('function', $options)){
            $options['function'] = 'count';
        }
        $options['limit'] = '*';
        unset($options['page']);
        $response = $this->list($class, $role, $options);
        if(array_key_exists('count', $response)){
            $count = $response['count'];
        }
        return $count;


    }
}