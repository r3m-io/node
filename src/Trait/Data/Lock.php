<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Config;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

Trait Lock {

    /**
     * @throws Exception
     */
    public function lock($class, $options=[]): bool
    {
        if(!array_key_exists('lock_wait_timeout', $options)){
            $options['lock_wait_timeout'] = 60;
        }
        $name = Controller::name($class);
        $object = $this->object();
        $dir_cache = $object->config('framework.dir.temp') .
            'Node' .
            $object->config('ds')
        ;
        $dir_lock = $dir_cache .
            'Lock' .
            $object->config('ds')
        ;
        $url_lock = $dir_lock .
            $name .
            $object->config('extension.lock')
        ;
        if(File::exist($url_lock)) {
            $timer = 0;
            $lock_wait_timeout = $options['lock_wait_timeout'];
            while(File::exist($url_lock)){
                sleep(1);
                $timer++;
                if($timer > $lock_wait_timeout){
                    throw new Exception('Lock timeout on class: ' . $name);
                }
            }
        }
        File::touch($url_lock);
        return true;
    }

    public function unlock($class): bool
    {
        $name = Controller::name($class);
        $object = $this->object();
        $dir_cache = $object->config('framework.dir.temp') .
            'Node' .
            $object->config('ds')
        ;
        $dir_lock = $dir_cache .
            'Lock' .
            $object->config('ds')
        ;
        $url_lock = $dir_lock .
            $name .
            $object->config('extension.lock')
        ;
        File::delete($url_lock);
        return true;
    }
}