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
    public function index_create_chunk($object_data, $chunk, $chunk_nr, $threads)
    {
        $object = $this->object();

        $is_unique = $object_data->data('is.unique');
        $index = $object_data->data('index');

        if(is_array($is_unique)){
            foreach($is_unique as $unique){
                $explode = explode(',', $unique);
                foreach($explode as $nr => $value){
                    $explode[$nr] = trim($value);
                }
                $found = [];
                foreach($index as $nr => $record){
                    foreach($explode as $value){
                        if(
                            is_object($record) &&
                            property_exists($record, 'name') &&
                            $record->name === $value
                        ){
                            $found[] = true;
                        }
                    }
                }
                if(count($found) === count($explode)){
                    $index[] = (object) [
                        'name' => $unique,
                        'unique' => true,
                    ];
                }
            }
        }
        foreach($index as $nr => $record){
            $unique = $record->unique ?? false;
            if($unique){
                $is_unique = 'unique';
            } else {
                $is_unique = '';
            }
            $ramdisk_dir_node = $object->config('ramdisk.url') .
                $object->config('posix.id') .
                $object->config('ds') .
                'Node' .
                $object->config('ds')
            ;
            $ramdisk_dir_index = $ramdisk_dir_node .
                'Index' .
                $object->config('ds')
            ;
            Dir::create($ramdisk_dir_index);
            $url = $ramdisk_dir_index .
                ($chunk_nr + 1) .
                '-' .
                $threads .
                '-' .
                $record->name .
                '-' .
                $is_unique .
                $object->config('extension.json');
            d($url);
        }
        ddd($index);
    }
}