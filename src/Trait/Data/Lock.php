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
        if(!array_key_exists('is_import', $options)){
            $options['is_import'] = false;
        }
        if(!array_key_exists('transaction', $options)){
            $options['transaction'] = false;
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
        if(
            File::exist($url_lock) &&
            $options['is_import'] === false
        ) {
            $timer = 0;
            $lock_wait_timeout = 60;
            if(array_key_exists('lock_wait_timeout', $options)){
                $lock_wait_timeout = $options['lock_wait_timeout'];
            }
            while(File::exist($url_lock)){
                sleep(1);
                $timer++;
                if($timer > $lock_wait_timeout){
                    throw new Exception('Lock timeout on class: ' . $name .' in create_many');
                }
            }
        }
        elseif(
            File::exist($url_lock) &&
            $options['is_import'] === true &&
            array_key_exists('url', $options)
        ){
            if(File::exist($url_lock)){
                $timer = 0;
                $lock_wait_timeout = 60;
                if(array_key_exists('lock_wait_timeout', $options)){
                    $lock_wait_timeout = $options['lock_wait_timeout'];
                }
                while(File::exist($url_lock)){
                    sleep(1);
                    $timer++;
                    if($timer > $lock_wait_timeout){
                        throw new Exception('Lock timeout on class: ' . $name .' with url: ' . $options['url']);
                    }
                }
            }
            Dir::create($dir_lock, Dir::CHMOD);
            $command = 'chown www-data:www-data ' . $dir_lock;
            exec($command);
            File::touch($url_lock);
            $command = 'chown www-data:www-data ' . $url_lock;
            exec($command);
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $command = 'chmod 777 ' . $dir_lock;
                exec($command);
                $command = 'chmod 666 ' . $url_lock;
                exec($command);
            }
        }
        elseif(
            !File::exist($url_lock) &&
            $options['is_import'] === false &&
            $options['transaction'] === false
        ){
            Dir::create($dir_lock, Dir::CHMOD);
            $command = 'chown www-data:www-data ' . $dir_lock;
            exec($command);
            File::touch($url_lock);
            $command = 'chown www-data:www-data ' . $url_lock;
            exec($command);
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $command = 'chmod 777 ' . $dir_lock;
                exec($command);
                $command = 'chmod 666 ' . $url_lock;
                exec($command);
            }
        }
        return true;
    }

    public function unlock($class, $options): bool
    {
        if(!array_key_exists('is_import', $options)){
            $options['is_import'] = false;
        }
        if(!array_key_exists('transaction', $options)){
            $options['transaction'] = false;
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
        if(
            (
                $options['is_import'] === false &&
                $options['transaction'] === false
            ) ||
            $options['is_import'] === true
        ){
            File::delete($url_lock);
        }
        return true;
    }
}