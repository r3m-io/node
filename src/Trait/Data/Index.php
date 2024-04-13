<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Parallel;
use R3m\Io\Module\Route;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;

trait Index {

    /**
     * @throws Exception
     */
    public function index_create_chunk($object_data, $chunk, $forks)
    {
        $object = $this->object();

        $is_unique = $object_data->data('is.unique');
        $index = $object_data->data('index');

        d($index);
        ddd($is_unique);

        /*
        $data_url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json');
        */
    }
}