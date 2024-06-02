<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\Config;

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

    public function index($class, $role, $options=[]){
        $name = Controller::name($class);
        $object = $this->object();
        $filter_name = $this->index_filter_name($name, $options);
        $where_name = $this->index_where_name($name, $options);
        $url_index = false;
        if($filter_name){
            $url_index = $object->config('ramdisk.url') .
                $object->config(Config::POSIX_ID) .
                $object->config('ds') .
                'Node' .
                $object->config('ds') .
                'Index' .
                $object->config('ds') .
                $name .
                '.' .
                $filter_name . //add sha1();
                //need filter keys and where attributes
                $object->config('extension.json');
        }
        elseif($where_name){
            $url_index = $object->config('ramdisk.url') .
                $object->config(Config::POSIX_ID) .
                $object->config('ds') .
                'Node' .
                $object->config('ds') .
                'Index' .
                $object->config('ds') .
                $name .
                '.' .
                $where_name . //add sha1()
                //need filter keys and where attributes
                $object->config('extension.json');
        }
        d($url_index);



        d($name);
        ddd($options);
    }

    private function index_filter_name($class, $options=[]): false | array
    {
        $filter = [];
        $is_filter = false;
        if(array_key_exists('filter', $options)){
            if(is_array($options['filter'])){
                foreach($options['filter'] as $attribute => $record){
                    $filter[] = $attribute;
                    $is_filter = true;
                }
            }
            elseif(is_object($options['filter'])){
                foreach($options['filter'] as $attribute => $record){
                    $filter[] = $attribute;
                    $is_filter = true;
                }
            }
            if($is_filter){
                return $filter;
            }
        }
        return false;
    }

    private function index_where_name($class, $options=[]): false | array
    {
        $where = [];
        $is_where = false;
        if(array_key_exists('where', $options)){
            if(is_array($options['where'])){
                foreach($options['where'] as $nr => $record){
                    if(
                        is_string($record) &&
                        in_array(
                            $record,
                            [
                                '(',
                                ')',
                            ],
                            true
                        )
                    ){
                        $where[] = '_';
                    }
                    elseif(
                        is_string($record) &&
                        in_array(
                            strotolower($record),
                            [
                                'and',
                                'or',
                                'xor'
                            ],
                            true
                        )
                    ){
                        $where[] = strtolower($record);
                    }
                    elseif(
                        is_array($record) &&
                        array_key_exists('attribute', $record)
                    ){
                        $where[] = $record['attribute'];
                        $is_where = true;
                    }
                    elseif(
                        is_object($record) &&
                        property_exists($record, 'attribute')
                    ){
                        $where[] = $record->attribute;
                        $is_where = true;
                    }
                }
            }
            if($is_where){
                return $where;
            }
        }
        return false;
    }


    /**
     * @throws Exception
     */
    public function index_create_chunk($object_data, $chunk, $chunk_nr, $threads, $mtime)
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
                if(count($found) !== count($explode)){
                    $index[] = (object) [
                        'name' => $unique,
                        'unique' => true,
                    ];
                }
            }
        }
        $url = [];
        $index_write = [];
        $continue = [];
        foreach($chunk as $nr => $item){
            foreach($index as $index_nr => $record){
                if(!array_key_exists($index_nr, $url)){
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
                    $url[$index_nr] = $ramdisk_dir_index .
                        ($chunk_nr + 1) .
                        '-' .
                        $threads .
                        '-' .
                        $record->name .
                        '-' .
                        $is_unique .
                        $object->config('extension.json');
                    $index_write[$index_nr] = (object) [];
                }
                if(
                    File::exist($url[$index_nr]) &&
                    File::mtime($url[$index_nr]) === $mtime
                ){
                    $continue[$index_nr] = true;
                    if(count($continue) === count($url)){
                        return;
                    }
                } else {
                    if(!Dir::is($ramdisk_dir_index)){
                        Dir::create($ramdisk_dir_index);
                    }
                    $explode = explode(',', $record->name);
                    $result = [];
                    foreach($explode as $explode_nr => $value){
                        $explode[$explode_nr] = trim($value);
                        $result[$explode_nr] = $item->{$explode[$explode_nr]};
                    }
                    $index_write[$index_nr]->{implode(',', $result)} = $nr;
                }
            }
        }
        foreach($index_write as $index_nr => $index){
            if(array_key_exists($index_nr, $continue)){
                continue;
            }
            File::write($url[$index_nr], Core::object($index, Core::OBJECT_JSON));
            File::touch($url[$index_nr], $mtime);
        }
    }
}