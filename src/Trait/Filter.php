<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\Module\Core;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;
use R3m\Io\Module\Filter as Module;
use R3m\Io\Module\Parse;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

trait Filter {

    /**
     * @throws Exception
     */
    private function filter($record=[], $filter=[], $options=[]): mixed
    {

        $list = [];
        $list[] = $record;
        $list = Module::list($list)->where($filter);
        if(!empty($list)){
            return $record;
        }
        return false;
    }
}