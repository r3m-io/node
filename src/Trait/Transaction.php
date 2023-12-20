<?php

namespace R3m\Io\Node\Trait;

use Exception;
use R3m\Io\App;
use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;
use R3m\Io\Node\Service\Security;

Trait Transaction {

    /**
     * @throws Exception
     */
    public function startTransaction($class, $options=[]): void
    {
        $name = Controller::name($class);
        $this->lock($name, $options);
        $object = $this->object();
        $object->config('node.transaction.' . $name, true);
        sleep(10);
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function commit($class, $role, $options=[]): false|array
    {
        $start = microtime(true);
        $name = Controller::name($class);
        $object = $this->object();
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $app_options = App::options($object);
        if (array_key_exists('time_limit', $options)) {
            set_time_limit((int)$options['time_limit']);
        } elseif ($object->config('time.limit')) {
            set_time_limit((int)$object->config('time.limit')); // 10 minutes
        } else {
            set_time_limit(600); // 10 minutes
        }
        $options['function'] = __FUNCTION__;
        $options['relation'] = false;
        if (!Security::is_granted(
            $name,
            $role,
            $options
        )) {
            $this->unlock($name, $options);
            return false;
        }
        if (property_exists($app_options, 'force')) {
            $options['force'] = $app_options->force;
        }
        $is_transaction = $object->config('node.transaction.' . $name);
        if(!$is_transaction){
            $this->unlock($name, $options);
            return false;
        }
        $result = [];
        //version 2 should append in json-line
        //make url sha1(url) of class
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url = $dir_data .
            $name .
            $object->config('extension.json')
        ;
        $data = $object->data(sha1($url));
        if($data){
            $start = microtime(true);
            $bytes = $data->write($url);
            $duration = microtime(true) - $start;
            $speed = $bytes / $duration;
            $result['bytes'] = $bytes;
            $result['size'] = File::size_format($bytes);
            $result['speed'] = File::size_format($speed) . '/sec';
        } else {
            throw new Exception('Commit-data not found for url: ' . $data);
        }
        $object->config('delete', 'node.transaction.' . $name);
        $this->unlock($name, $options);
        return $result;
    }
}