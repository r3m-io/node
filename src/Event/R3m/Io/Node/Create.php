<?php

namespace Event\R3m\Io\Node;

use Event\R3m\Io\Framework\Email;

use R3m\Io\App;
use R3m\Io\Config;

use Exception;

use R3m\Io\Exception\ObjectException;

class Create {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function error(App $object, $event, $options=[]): void
    {
        if($object->config(Config::POSIX_ID) !== 0){
            return;
        }
        Create::notification($object, $event, $options);
    }

    /**
     * @throws Exception
     */
    public static function notification(App $object, $event, $options=[]): void
    {
        $action = $event->get('action');
        Email::queue(
            $object,
            $action,
            $options
        );
    }
}