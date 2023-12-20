<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

Trait Commit {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function commit($class, $role, $options=[]): array
    {
        $start = microtime(true);
        $name = Controller::name($class);
        $object = $this->object();
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $app_options = App::options($object);
        if (array_key_exists('time.limit', $options)) {
            set_time_limit((int)$options['time.limit']);
        } elseif (
            property_exists($app_options, 'time') &&
            property_exists($app_options->time, 'limit')
        ) {
            set_time_limit((int)$options->{'time'}->{'limit'});
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
            return [];
        }
        if (property_exists($app_options, 'force')) {
            $options['force'] = $app_options->force;
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
            $duration = (microtime(true) - $object->config('time.start') * 1000);
            $result['commit'] = File::size_format($speed) . '/sec';
            $result['duration'] = $duration;
        } else {
            throw new Exception('Commit-data not found for url: ' . $data);
        }
        return $result;
    }
}