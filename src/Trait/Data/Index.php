<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Exception\DirectoryCreateException;
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
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function index($class, $role, $options=[]){
        $name = Controller::name($class);
        $object = $this->object();
        if(array_key_exists('where', $options)){
            $options['where'] = $this->nodelist_where($options);
        }
        $filter_name = $this->index_filter_name($name, $options);
        $where_name = $this->index_where_name($name, $options);
        d($where_name);
        $dir_index = $object->config('ramdisk.url') .
            $object->config(Config::POSIX_ID) .
            $object->config('ds') .
            'Node' .
            $object->config('ds') .
            'Index' .
            $object->config('ds')
        ;
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url_data = $dir_data . $name . $object->config('extension.json');
        $url_mtime = File::mtime($url_data);
        $url_index = false;
        $cache = $object->data(App::CACHE);
        d($url_data);
        $cache_select = $cache->get(sha1($url_data));
        ddd($cache_select);
        //url_index should be in node/index
        if($where_name === false){
            if($filter_name === false){
                $url_index = $dir_index .
                    $name .
                    '.' .
                    'Filter' .
                    '.' .
                    'uuid' .
                    //need filter keys and where attributes
                    $object->config('extension.btree');
            } else {
                $url_index = $dir_index .
                    $name .
                    '.' .
                    'Filter' .
                    '.' .
                    implode('.', $filter_name) . //add sha1();
                    //need filter keys and where attributes
                    $object->config('extension.btree');
            }
            if($cache_select){
                $select = [
                    'list' => $cache_select->get($name)
                ];
            } else {
                $select = $this->list(
                    $name,
                    $role,
                    [
                        'transaction' => true,
                        'limit' => '*',
                        'page' => 1
                    ]
                );
            }
            $list = $this->index_list(
                $name,
                $select,
                $filter_name,
                $count_index,
                $is_uuid,
                $cache_key
            );
        }
        elseif($where_name){
            $url_index = $dir_index .
                $name .
                '.' .
                'Where' .
                '.' .
                implode('.', $where_name) . //add sha1()
                //need filter keys and where attributes
                $object->config('extension.btree');
            if($url_index){
                Dir::create($dir_index, Dir::CHMOD);
                if($cache_select){
                    $select = [
                        'list' => $cache_select->get($name)
                    ];
                } else {
                    $select = $this->list(
                        $name,
                        $role,
                        [
                            'transaction' => true,
                            'limit' => '*',
                            'page' => 1
                        ]
                    );
                }
                $list = $this->index_list(
                    $name,
                    $select,
                    $where_name,
                    $count_index,
                    $is_uuid,
                    $cache_key
                );
            }
        }
        if($url_index){
            Dir::create($dir_index, Dir::CHMOD);
        }
        $result = [];
        foreach($list as $uuid => $record){
            if($is_uuid){
                $result[] = ';' . $uuid;
            } else {
                if(array_key_exists($record->{'#sort'}, $result)) {
                    //cannot create index, not unique
                    return false;
                }
                $result[] = $record->{'#sort'} . ';' . $uuid;
            }
        }
        $output = implode(PHP_EOL, $result);
        File::write($url_index, $output);
        return [
            'url' => $url_index,
            'cache' => $cache_key,
            'count' => $count_index,
            'filter' => $filter_name,
            'where'=> $where_name,
            'is_uuid' => $is_uuid
        ];
    }

    /**
     * @throws Exception
     */
    private function index_list($name, $select=[], $filter_name=false, &$count_index=false, &$is_uuid=false, &$cache_key=false){
        //nodelist all records in chunks of 4096 so we can parallelize the process later on.
        if(!array_key_exists('list', $select)){
            return false; //no-data
        }
        $object = $this->object();
        $cache = $object->data(App::CACHE);
        $list = [];
        $data_cache = (object) [];
        $count_index = 0;
        $is_uuid = false;
        foreach($select['list'] as $nr => $record){
            if(
                is_object($record) &&
                property_exists($record, 'uuid')
            ){
                $data_cache->{$record->uuid} = $record;
                $record_index = (object) [
                    'uuid' => $record->uuid
                ];
                $count_index++;
                $sort_key = [];
                if($filter_name === false){
                    $is_uuid = true;
                } else {
                    foreach($filter_name as $attribute){
                        if(!property_exists($record, $attribute)){
                            continue; //no-data
                        }
                        $record_index->{$attribute} = $record->{$attribute};
                        $sort_key[] = '\'' . $record->{$attribute} . '\'';
                    }
                    $record_index->{'#sort'} = implode(',', $sort_key);
                }
                $list[] = $record_index;
            }
        }
        $cache_key = sha1('index.' . $name);
        $cache->set($cache_key, $data_cache);
        if($is_uuid){
            $list = Sort::list($list)->with([
                'uuid' => 'asc'
            ]);
        } else {
            $list = Sort::list($list)->with([
                '#sort' => 'asc'
            ]);
        }
        return $list;
    }

    public function index_record($line, $options=[]): bool|object
    {
        $split = mb_str_split($line);
        $previous_char = false;
        $start = false;
        $end = false;
        $collect = [];
        $is_collect = false;
        $index = 0;
        $record = [];
        $uuid = [];
        $is_uuid = false;
        $filter = $options['index']['filter'] ?? false;
        $where = $options['index']['where'] ?? false;
        d($where);
        d($index);
        foreach ($split as $nr => $char) {
            if ($is_uuid) {
                $uuid[] = $char;
                continue;
            }
            if (
                $previous_char !== '\\' &&
                $char === '\'' &&
                $start === false
            ) {
                $start = $nr;
                $previous_char = $char;
                $is_collect = true;
                continue;
            } elseif (
                $previous_char !== '\\' &&
                $char === '\'' &&
                $start !== false
            ) {
                $end = $nr;
                if ($filter && array_key_exists($index, $filter)) {
                    $attribute = $filter[$index];
                    $record[$attribute] = implode('', $collect);
                }
                elseif ($where && array_key_exists($index, $where)) {
                    $attribute = $where[$index];
                    $record[$attribute] = implode('', $collect);
                }

                $previous_char = $char;
                $is_collect = false;
                $start = false;
                $collect = [];
                continue;
            }
            if ($is_collect) {
                $collect[] = $char;
            } else {
                if ($char === ',') {
                    $index++;
                } elseif ($char === ';') {
                    $is_uuid = true;
                }
            }
            $previous_char = $char;
        }
        if (array_key_exists(0, $uuid)) {
            $record['uuid'] = rtrim(implode('', $uuid), PHP_EOL);
            return (object) $record;
        }
        return false;
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
                            strtolower($record),
                            [
                                '(',
                                ')',
                                'and',
                                'or',
                                'xor'
                            ],
                            true
                        )
                    ){
//                        $where[] = strtolower($record);
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