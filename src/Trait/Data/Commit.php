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
    public function commit($class, $role, $options=[]): int
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
            return 0;
        }
        if (property_exists($app_options, 'force')) {
            $options['force'] = $app_options->force;
        }
        //make url sha1(url) of class
        ddd('yes found commit');
        return 1;
    }
}