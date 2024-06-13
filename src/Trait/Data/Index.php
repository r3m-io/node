<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Exception\AuthorizationException;
use R3m\Io\Exception\DirectoryCreateException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Filter;
use R3m\Io\Module\Limit;
use R3m\Io\Module\Parallel;
use R3m\Io\Module\Route;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;
use SplFileObject;

trait Index {

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws Exception
     */
    private function index_record_expose($class, $role, $record, $options): mixed
    {
        $object = $this->object();
        $name = Controller::name($class);
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url_data = $dir_data . $name . $object->config('extension.json');
        $url_mtime = File::mtime($url_data);
        $cache = $object->data(App::CACHE);
        $data = $cache->get(sha1($url_data) . '_index');
        if (
            is_object($data) &&
            property_exists($data, $record->uuid)
        ) {
            $record = $data->{$record->uuid};
        } elseif (
            is_array($data) &&
            array_key_exists($record->uuid, $data)
        ) {
            $record = $data[$record->uuid];
        }
        if(!property_exists($record, '#class')){
            return false;
        }
        $expose = $this->expose_get(
            $object,
            $record->{'#class'},
            $record->{'#class'} . '.' . $options['function'] . '.output'
        );
        $node = new Storage($record);
        $node = $this->expose(
            $node,
            $expose,
            $record->{'#class'},
            $options['function'],
            $role
        );
        $record = $node->data();
        if ($options['relation'] === true) {
            ddd('need object_data from cache?');
//                                                $record = $this->relation($record, $object_data, $role, $options);
            //collect relation mtime
        }
        return $record;
    }

    private function list_index($class, $role, $options=[]): bool | array
    {
        $object = $this->object();
        $name = Controller::name($class);
        $data_url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        $cache = $object->data(App::CACHE);
        $set_max = false;
        if($cache) {
            $data = $cache->get(sha1($data_url) . '_index');
            if ($data) {
                $file = new SplFileObject($options['index']['url']);
                $options['index']['min'] = 0;
                $options['index']['max'] = $options['index']['count'] - 1;
                $max = 4096;
                $counter = 0;
                $seek = false;
                $line = null;
                $is_found = false;
                $jump_max = 0;
                $record = false;
                $where = false;
                $operator = null;
                $key = null;
                $old_seek = null;
                while($options['index']['min'] <= $options['index']['max']) {
                    $seek = $options['index']['min'] +
                        floor(
                            (
                                $options['index']['max'] -
                                $options['index']['min']
                            )
                            / 2
                        );
                    $file->seek($seek);
                    $line = $file->current();
                    $counter++;
                    if ($counter > $max) {
                        break;
                    }
                    if($seek === $old_seek){
                        break;
                    }
                    d($line);
//                    $debug = debug_backtrace(1);
//                    d($debug[0]['line'] . ' ' . $debug[0]['file'] . ' ' . $debug[0]['function']);
//                    d($debug[1]['line'] . ' ' . $debug[1]['file'] . ' ' . $debug[1]['function']);
//                    d($debug[2]['line'] . ' ' . $debug[2]['file'] . ' ' . $debug[2]['function']);
                    $record = $this->index_record($line, $options);
                    $old_seek = $seek;
                    $list = [];
                    if($record){
                        if(array_key_exists('filter', $options)){
                            $list[] = $record;
                            $list = Filter::list($list)->where($options['filter']);
                        }
                        elseif(array_key_exists('where', $options)){
                            $options['where'] = $this->nodelist_where($options);
                            $record_where = $this->where($record, $options['where'], $options);
//                            d($record_where);
                            if ($record_where) {
                                $list[] = $record_where;
                            }
                        }
                        elseif($options['index']['is_uuid'] === true){
                            //no filter, no where, only sort, page & limit
                            $data_sort = Sort::list($data)->with($options['sort']);
                            $data_limit = Limit::list($data_sort)->with([
                                'page' => $options['page'],
                                'limit' => $options['limit']
                            ]);
                            $count = 0;
                            if($data_limit) {
                                foreach ($data_limit as $nr => $record) {
                                    $expose = $this->expose_get(
                                        $object,
                                        $record->{'#class'},
                                        $record->{'#class'} . '.' . $options['function'] . '.output'
                                    );
                                    $node = new Storage($record);
                                    $node = $this->expose(
                                        $node,
                                        $expose,
                                        $record->{'#class'},
                                        $options['function'],
                                        $role
                                    );
                                    $record = $node->data();
                                    $list[] = $record;
                                    $count++;
                                }
                            }
                            $result = [];
                            $result['page'] = $options['page'];
                            $result['limit'] = $options['limit'];
                            $result['count'] = $count;
                            $result['max'] = $options['index']['count'];
                            $result['list'] = $list;
                            $result['sort'] = $options['sort'] ?? [];
                            if (!empty($options['filter'])) {
                                $result['filter'] = $options['filter'];
                            }
                            if (!empty($options['where'])) {
                                $result['where'] = $options['where'];
                            }
                            $result['relation'] = $options['relation'];
                            $result['parse'] = $options['parse'];
                            $result['pre-compile'] = $options['pre-compile'] ?? false;
                            $result['ramdisk'] = $options['ramdisk'] ?? false;
                            $result['mtime'] = $options['mtime'];
                            $result['transaction'] = $options['transaction'] ?? false;
                            $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                            return $result;
                        }
                    }
//                    d($list);
                    if(array_key_exists(0, $list)) {
                        $record = $this->list_index_record($data, $record, $role, $options);
                        $record->{'#jump'} = $counter;
                        if($record->{'#jump'} > $jump_max){
                            $jump_max = $record->{'#jump'};
                            $record->{'#jump_max'} = $jump_max;
                        }
                        if (
                            $options['limit'] === 1 &&
                            $options['page'] === 1
                        ) {
                            $list = [];
                            $list[] = $record;
                            $result = [];
                            $result['page'] = $options['page'];
                            $result['limit'] = $options['limit'];
                            $result['count'] = 1;
                            $result['max'] = $options['index']['count'];
                            $result['list'] = $list;
                            $result['sort'] = $options['sort'] ?? [];
                            if (!empty($options['filter'])) {
                                $result['filter'] = $options['filter'];
                            }
                            if (!empty($options['where'])) {
                                $result['where'] = $options['where'];
                            }
                            $result['relation'] = $options['relation'];
                            $result['parse'] = $options['parse'];
                            $result['pre-compile'] = $options['pre-compile'] ?? false;
                            $result['ramdisk'] = $options['ramdisk'] ?? false;
                            $result['mtime'] = $options['mtime'];
                            $result['transaction'] = $options['transaction'] ?? false;
                            $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                            return $result;
                        } else {
                            ddd($record);
                        }
                    } else {
                        if(
                            array_key_exists('filter', $options) &&
                            is_array($options['filter'])
                        ){
                            foreach ($options['filter'] as $attribute => $filter) {
                                if (is_object($filter)) {
                                    ddd('filter is object, implement');
                                } elseif (is_array($filter)) {
                                    if (
                                        array_key_exists('operator', $filter) &&
                                        array_key_exists('value', $filter)
                                    ) {
                                        $operator = $filter['operator'];
                                        $value = $filter['value'];
                                        if(property_exists($record, $attribute)) {
                                            $list = [];
                                            $list[] = $record;
                                            $where = [
                                                $attribute => [
                                                    'operator' => $operator,
                                                    'value' => $value
                                                ]
                                            ];
                                            $list = Filter::list($list)->where($where);
                                            if(!array_key_exists(0, $list)){
                                                $sort = [
                                                    $value,
                                                    $record->{$attribute}
                                                ];
                                                sort($sort, SORT_NATURAL);
                                                if($sort[0] === $value){
                                                    $options['index']['max'] = $seek - 1;
                                                    break;
                                                } else {
                                                    //sort[1] === $value
                                                    //min becomes seek + 1
                                                    $options['index']['min'] = $seek + 1;
                                                    break;
                                                };
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        elseif(
                            array_key_exists('where', $options) &&
                            is_array($options['where'])
                        ){
                            $where = $options['where'];
                            $deepest = $this->where_get_depth($where);
                            $max_deep =0;
                            if(!array_key_exists('set', $options)){
                                $options['set'] = [];
                                $options['set']['index'] = 0;
                            }

                            while($deepest >= 0) {
                                if ($max_deep > 1024) {
                                    break;
                                }
                                $set = $this->where_get_set($where, $key, $deepest);
                                d($set);
                                if (!array_key_exists('max', $options['set'])){
                                    $options['set']['list'] = $set;
                                    $options['set']['max'] = count($set);
                                    d($options['set']);
                                }
                                if($options['set']['max'] > 2){
                                    if(array_key_exists(($options['set']['index'] + 1), $set)){
                                        $operator = $set[($options['set']['index'] + 1)];
                                    } else {
                                        d('cannot find operator in where set');
                                        ddd($set);
                                        throw new Exception('cannot find operator in where set');
                                    }
                                    switch(strtolower($operator)){
                                        case 'or' :
                                            if(array_key_exists($options['set']['index'], $set)){
                                                if(
                                                    is_array($set[$options['set']['index']]) &&
                                                    in_array(
                                                        $set[$options['set']['index']]['attribute'],
                                                        $options['index']['where'],
                                                        true
                                                    )
                                                ){
                                                    $sort = [
                                                        $set[$options['set']['index']]['value'],
                                                        $record->{$set[$options['set']['index']]['attribute']}
                                                    ];
                                                    sort($sort, SORT_NATURAL);
                                                    d($sort);
                                                    if(
                                                        $sort[0] === $set[$options['set']['index']]['value'] &&
                                                        $set[$options['set']['index']]['value'] === $record->{$set[$options['set']['index']]['attribute']}
                                                    ){
                                                        $is_found = true;

                                                        $record = $this->list_index_record($data, $record, $role, $options);
                                                        $record->{'#jump'} = $counter;
                                                        if($record->{'#jump'} > $jump_max){
                                                            $jump_max = $record->{'#jump'};
                                                            $record->{'#jump_max'} = $jump_max;
                                                        }
                                                        if (
                                                            $options['limit'] === 1 &&
                                                            $options['page'] === 1
                                                        ) {
                                                            $list = [];
                                                            $list[] = $record;
                                                            $result = [];
                                                            $result['page'] = $options['page'];
                                                            $result['limit'] = $options['limit'];
                                                            $result['count'] = 1;
                                                            $result['max'] = $options['index']['count'];
                                                            $result['list'] = $list;
                                                            $result['sort'] = $options['sort'] ?? [];
                                                            if (!empty($options['filter'])) {
                                                                $result['filter'] = $options['filter'];
                                                            }
                                                            if (!empty($options['where'])) {
                                                                $result['where'] = $options['where'];
                                                            }
                                                            $result['relation'] = $options['relation'];
                                                            $result['parse'] = $options['parse'];
                                                            $result['pre-compile'] = $options['pre-compile'] ?? false;
                                                            $result['ramdisk'] = $options['ramdisk'] ?? false;
                                                            $result['mtime'] = $options['mtime'];
                                                            $result['transaction'] = $options['transaction'] ?? false;
                                                            $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                                                            return $result;
                                                        } else {
                                                            ddd($record);
                                                        }
                                                    }
                                                    elseif($sort[0] === $set[$options['set']['index']]['value']){
                                                        $options['index']['max'] = $seek - 1;
                                                        break 2;
                                                    } else {
                                                        //sort[1] === $value
                                                        //min becomes seek + 1
                                                        $options['index']['min'] = $seek + 1;
                                                        break 2;
                                                    }
                                                }
                                                elseif($set[$options['set']['index']] === false){
                                                    break 2;
                                                }
                                            }
                                            break;
                                        case 'and' :
                                            if(array_key_exists($options['set']['index'], $set)){
                                                if(
                                                    in_array(
                                                        $set[$options['set']['index']]['attribute'],
                                                        $options['index']['where'],
                                                        true
                                                    )
                                                ){
                                                    $sort = [
                                                        $set[$options['set']['index']]['value'],
                                                        $record->{$set[$options['set']['index']]['attribute']}
                                                    ];
                                                    sort($sort, SORT_NATURAL);
                                                    d($sort);
                                                    if(
                                                        $sort[0] === $set[$options['set']['index']]['value'] &&
                                                        $set[$options['set']['index']]['value'] === $record->{$set[$options['set']['index']]['attribute']}
                                                    ){
                                                        $is_found = true;
                                                        $record = $this->list_index_record($data, $record, $role, $options);
                                                        $record->{'#jump'} = $counter;
                                                        if($record->{'#jump'} > $jump_max){
                                                            $jump_max = $record->{'#jump'};
                                                            $record->{'#jump_max'} = $jump_max;
                                                        }
                                                        if (
                                                            $options['limit'] === 1 &&
                                                            $options['page'] === 1
                                                        ) {
                                                            $list = [];
                                                            $list[] = $record;
                                                            $result = [];
                                                            $result['page'] = $options['page'];
                                                            $result['limit'] = $options['limit'];
                                                            $result['count'] = 1;
                                                            $result['max'] = $options['index']['count'];
                                                            $result['list'] = $list;
                                                            $result['sort'] = $options['sort'] ?? [];
                                                            if (!empty($options['filter'])) {
                                                                $result['filter'] = $options['filter'];
                                                            }
                                                            if (!empty($options['where'])) {
                                                                $result['where'] = $options['where'];
                                                            }
                                                            $result['relation'] = $options['relation'];
                                                            $result['parse'] = $options['parse'];
                                                            $result['pre-compile'] = $options['pre-compile'] ?? false;
                                                            $result['ramdisk'] = $options['ramdisk'] ?? false;
                                                            $result['mtime'] = $options['mtime'];
                                                            $result['transaction'] = $options['transaction'] ?? false;
                                                            $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                                                            return $result;
                                                        } else {
                                                            ddd($record);
                                                        }
                                                    }
                                                    elseif($sort[0] === $set[$options['set']['index']]['value']){
                                                        $options['index']['max'] = $seek - 1;
                                                        break 2;
                                                    } else {
                                                        //sort[1] === $value
                                                        //min becomes seek + 1
                                                        $options['index']['min'] = $seek + 1;
                                                        break 2;
                                                    }
                                                }
                                            }
                                            break;
                                    }
                                } else {
                                    if(array_key_exists($options['set']['index'], $set)){
                                        if(
                                            in_array(
                                                $set[$options['set']['index']]['attribute'],
                                                $options['index']['where'],
                                                true
                                            )
                                        ){
                                            $sort = [
                                                $set[$options['set']['index']]['value'],
                                                $record->{$set[$options['set']['index']]['attribute']}
                                            ];
                                            sort($sort, SORT_NATURAL);
//                                            d($sort);
                                            if(
                                                $sort[0] === $set[$options['set']['index']]['value'] &&
                                                $set[$options['set']['index']]['value'] === $record->{$set[$options['set']['index']]['attribute']}
                                            ){
                                                $is_found = true;
                                                $record = $this->list_index_record($data, $record, $role, $options);
                                                $record->{'#jump'} = $counter;
                                                if($record->{'#jump'} > $jump_max){
                                                    $jump_max = $record->{'#jump'};
                                                    $record->{'#jump_max'} = $jump_max;
                                                }
                                                if (
                                                    $options['limit'] === 1 &&
                                                    $options['page'] === 1
                                                ) {
                                                    $list = [];
                                                    $list[] = $record;
                                                    $result = [];
                                                    $result['page'] = $options['page'];
                                                    $result['limit'] = $options['limit'];
                                                    $result['count'] = 1;
                                                    $result['max'] = $options['index']['count'];
                                                    $result['list'] = $list;
                                                    $result['sort'] = $options['sort'] ?? [];
                                                    if (!empty($options['filter'])) {
                                                        $result['filter'] = $options['filter'];
                                                    }
                                                    if (!empty($options['where'])) {
                                                        $result['where'] = $options['where'];
                                                    }
                                                    $result['relation'] = $options['relation'];
                                                    $result['parse'] = $options['parse'];
                                                    $result['pre-compile'] = $options['pre-compile'] ?? false;
                                                    $result['ramdisk'] = $options['ramdisk'] ?? false;
                                                    $result['mtime'] = $options['mtime'];
                                                    $result['transaction'] = $options['transaction'] ?? false;
                                                    $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                                                    return $result;
                                                } else {
                                                    ddd($record);
                                                }
                                            }
                                            elseif($sort[0] === $set[$options['set']['index']]['value']){
                                                $options['index']['max'] = $seek - 1;
                                                break;
                                            } else {
                                                //sort[1] === $value
                                                //min becomes seek + 1
                                                $options['index']['min'] = $seek + 1;
                                                break;
                                            }
                                        }
                                    }
                                    $max_deep++;
                                    break;
                                }
                            }
                        }
                    }
                }
                if($operator === 'or'){
                    d($operator);
                    if($options['set']['max'] > 2){
                        //1st where returned false
                        for($i=2; $i < $options['set']['max']; $i++){
                            d($i);
                            $options_list_index = $options;
                            $options_list_index['set']['max'] = 1;
                            $options_list_index['set']['index'] = $i;
                            unset($options_list_index['index']['min']);
                            unset($options_list_index['index']['max']);
                            $result = $this->list_index($class, $role, $options_list_index);
                            if($result){
                                return $result;
                            }
                            $i++;
                        }
                    }
                    if($where){
                        ddd('has some more where');
                        //options_list_index = options
                        //options_list_index['where'] = $where
//                    $record = $this->list_index($class, $role, $options_list_index);
                        ddd($record);
                    }
                }
                elseif($operator === 'and'){
                    //1st where returned false
                    $has_or = false;
                    foreach($options['set']['list'] as $nr => $set){
                        if($set === 'or'){
                            $has_or = true;
                            ddd($options);
                        }
                    }
                    if($has_or === false){
                        if($where){
                            $options_list_index = $options;
                            unset($options_list_index['index']['min']);
                            unset($options_list_index['index']['max']);
                            $options_list_index['where'] = [
                                $key => false,
                                ...$where
                            ];
                            unset($options_list_index['set']);
                            d($options_list_index);
                            $debug = debug_backtrace(1);
                            d($debug[0]['line'] . ' ' . $debug[0]['file'] . ' ' . $debug[0]['function']);
                            d($debug[1]['line'] . ' ' . $debug[1]['file'] . ' ' . $debug[1]['function']);
                            d($debug[2]['line'] . ' ' . $debug[2]['file'] . ' ' . $debug[2]['function']);
                            $record = $this->list_index($class, $role, $options_list_index);
                            d($options_list_index);
                            ddd($record);
                        } else {
                            return false;
                        }
                    } else {
                        ddd('has or');
                    }
                    d($where);
                    d($options);
                    if($options['set']['max'] > 2){
                        for($i=2; $i < $options['set']['max']; $i++){
                            $options_list_index = $options;
                            $options_list_index['set']['max'] = 1;
                            $options_list_index['set']['index'] = $i;
                            unset($options_list_index['index']['min']);
                            unset($options_list_index['index']['max']);
                            d($options_list_index);
                            $record = $this->list_index($class, $role, $options_list_index);
                            ddd($record);
                            $i++;
                        }
                    }
                    if($where) {
                        ddd('has some more where');
                        //options_list_index = options
                        //options_list_index['where'] = $where
                    }
                }
                else {
                    d($options);
                    d($where);
                    ddd($record);
                    d($operator);
                }

            }
        }
        d($options);
        return false;
    }

    public function index_record_next($found=[], $options=[]){
        $where = [];
        if(
            array_key_exists('where', $options) &&
            is_array($options['where'])
        ){
            $where[] = '(';
            foreach($options['where'] as $nr => $set){
                $where[] = $set;
            }
            $where[] = ')';
            $where[] = 'and';
        }
        if(!empty($found)){
            $where[] = [
                'attribute' => 'uuid',
                'operator' => 'not-in',
                'value' => $found
            ];
        }
        return $where;
    }

    /**
     * @throws Exception
     */
    public function index_list_record($class, $role, $options=[]): bool | object
    {
        if(!array_key_exists('index', $options)){
            return false;
        }
        if(!array_key_exists('url', $options['index'])){
            return false;
        }
        if(!array_key_exists('url_uuid', $options['index'])){
            return false;
        }
        if(!array_key_exists('count', $options['index'])){
            return false;
        }
        $name = Controller::name($class);
        $object = $this->object();
        $record = (object) [];
        $file = [];
        if(!array_key_exists('url_uuid', $options['index'])){
            return false;
        }
        if(!File::exist($options['index']['url_uuid'])){
            return false;
        }
        $file['uuid'] = new SplFileObject($options['index']['url_uuid']);
        foreach($options['index']['url'] as $nr => $url){
            $file[$nr] = new SplFileObject($url);
        }
        $options['index']['min'] = 0;
        $options['index']['max'] = $options['index']['count'] - 1;

        $counter = 0;
        $max = 4096;
        $seek_old = false;
        while($options['index']['min'] <= $options['index']['max']) {
            $seek = $options['index']['min'] +
                floor(
                    (
                             $options['index']['max'] -
                             $options['index']['min']
                         )
                     / 2
                );
            d('seek: ' . $seek . ' seek old: ' . $seek_old);
            if($seek === $seek_old){
                d('found 1');
                break;
            }
            $counter++;
            if ($counter > $max) {
                d('found 2');
                break;
            }
            $operator = '===';
            foreach ($options['index']['where'] as $nr => $attribute){
                $file[$nr]->seek($seek);
                $line = $file[$nr]->current();
                $value = rtrim($line, PHP_EOL);
                $record->{$attribute} = $value;
            }
            $file['uuid']->seek($seek);
            $line = $file['uuid']->current();
            $value = rtrim($line, PHP_EOL);
            $record->uuid = $value;
//            d($record);
//           d($options['where']);
            $record_where = $this->where($record, $options['where'], $options);
            if($record_where){
                d('found 3');
                return $record;
            } else {
                $where = $options['where'];
                $deepest = $this->where_get_depth($where);
                $max_deep = 0;
                while($deepest >= 0) {
                    if ($max_deep > 1024) {
                        d('found 4');
                        break;
                    }
                    $set = $this->where_get_set($where, $key, $deepest);
                    $set_init = null;
                    $where_process = $where;
                    while($record !== false){
                        if(!$set_init){
                            $set_init = $set;
                        }
//                        d($set);
//                        d($deepest);
                        $set = $this->where_process($record, $set, $where_process, $key, $operator, $index_where, $options);
                        d($set);
                        d($set_init);
                        d($where_process);
                        d($index_where);
                        if(empty($set) && $deepest === 0){
//                            d('yes');
                            d('found 5');
                            return $record;
                        }
                        elseif(empty($set) && $deepest > 0){
                            d('found 6');
                            break;
                        }
                        if(is_array($set)){
                            $count_set = count($set);
                            if($count_set === 1){
                                if($operator === null && $set[0]['match'] === true){
                                    if(empty($where_process)){
                                        d('found 7');
                                        return $record;
                                    } else {
                                        $deepest--;
//                                        d($deepest);
                                        $set = $this->where_get_set($where_process, $key, $deepest);
                                        $set_init = $set;
                                        d($set_init);
                                        d($where_process);
                                        $set = $this->where_process($record, $set, $where_process, $key, $operator, $index_where, $options);
                                        d($where_process);
                                        d($set);
                                        d($index_where);
                                    }
                                }
                            }
                        }
                        if($index_where){
                            $set_index_0 = [$set_init[0]];
                            $set_index_0 = $this->where_process($record, $set_index_0);
                            $set_index_2 = null;
//                            d($set_index_0);
                            //if($set_index_0[0] === true){}
                            if($set_index_0[0]['match'] === false){
                                if(
                                    array_key_exists('match', $set_init[0]) &&
                                    $set_init[0]['match'] === false
                                ){
                                    sort($index_where[2], SORT_NATURAL);
                                    if($index_where[2][0] === $set_init[2]['value']){
                                        $options['index']['max'] = $seek - 1;
                                        break 2;

                                    } else {
                                        //sort[1] === $value
                                        //min becomes seek + 1
                                        $options['index']['min'] = $seek + 1;
                                        break 2;
                                    }
                                } else {
                                    if(
                                        !array_key_exists(0, $index_where) &&
                                        array_key_exists(2, $index_where)
                                    ){
                                        sort($index_where[2], SORT_NATURAL);
                                        if($index_where[2][0] === $set_init[2]['value']){
                                            $options['index']['max'] = $seek - 1;
                                            d('found: 10');
                                            break 2;

                                        } else {
                                            //sort[1] === $value
                                            //min becomes seek + 1
                                            $options['index']['min'] = $seek + 1;
                                            d('found: 11');
                                            break 2;
                                        }
                                    } else {
                                        sort($index_where[0], SORT_NATURAL);
//                                        d($index_where);
//                                        d($set_init);
                                        if($index_where[0][0] === $set_init[0]['value']){
                                            $options['index']['max'] = $seek - 1;
                                            if($options['index']['max'] < $options['index']['min']){
                                                $set = [
                                                    false
                                                ];
                                                $record = false;
                                                d('found: 8');
                                                break;
                                            }
//                                            d('break: 2');
                                            d('found: 9');
                                            break 2;
                                        } else {
                                            //sort[1] === $value
                                            //min becomes seek + 1
                                            $options['index']['min'] = $seek + 1;
                                            if($options['index']['max'] < $options['index']['min']){
                                                $set = [
                                                    false
                                                ];
                                                $record = false;
                                                d('found: 12');
//                                                d('break: 1');
                                                break;
                                            }
                                            d('found: 13');
//                                            d('break: 2');
                                            break 2;
                                        }
                                    }
                                }
                            } else {
//                                d($set_init);
                                if(array_key_exists(1, $set_init)){
                                    switch($set_init[1]){
                                        case 'and':
                                            $set_index_2 = [$set_init[2]];
//                                            d($record);
//                                            d($set_index_2);
                                            $set_index_2 = $this->where_process($record, $set_index_2);
//                                            d($set_index_2);
                                            if($set_index_0[0]['match'] === true && $set_index_2[0]['match'] === true){
                                                array_shift($set);
                                                array_shift($set);
                                            }
                                            if($set_index_2[0]['match'] === false){
                                                if(
                                                    is_array($index_where[2][0]) &&
                                                    $set_init[2]['operator'] === 'not-in'
                                                ){
                                                    if(!in_array($index_where[2][1], $index_where[2][0])) {
                                                        d('found record');
                                                        d($index_where);
                                                        ddd($record);
                                                    }
                                                    if(
                                                        in_array(
                                                            $index_where[2][1],
                                                            $index_where[2][0],
                                                            true
                                                        )
                                                    ){
                                                        $sort = new Sort();
                                                        usort($index_where[2][0], array($sort,"uuid_compare_ascending"));
                                                        $not_in_index = array_search($index_where[2][1], $index_where[2][0]);
                                                        $not_in_count = count($index_where[2][0]);
                                                        $not_in_seek = floor($not_in_count / 2);
                                                        if($not_in_index < $not_in_seek){
                                                            $options['index']['min'] = $seek + 1;
                                                        } else {
                                                            $options['index']['max'] = $seek - 1;
                                                        }
                                                        d('found 31');
                                                        d($index_where[2]);
                                                        d('min: ' . $options['index']['min'] . ', max: ' . $options['index']['max']);
                                                        if($options['index']['max'] < $options['index']['min']){
                                                            $set = [
                                                                false
                                                            ];
                                                            $record = false;
//                                                            d('break: 2');
                                                            d('found: 14');
                                                            break 2;

                                                        }
                                                    } else {
                                                        ddd('found');
                                                        d($set_index_0);
                                                        d($set_index_2);
                                                        d($index_where);
                                                        d($set_init);
                                                        d('found 32');
                                                        //sort[1] === $value
                                                        //min becomes seek + 1
//                                                        d($options['index']['min']);
                                                        $options['index']['max'] = $seek + 1;
//                                                        d($options['index']['min']);
//                                                        d($options['index']['max']);


                                                        if($options['index']['max'] < $options['index']['min']){
                                                            $set = [
                                                                false
                                                            ];
                                                            $record = false;
//                                                            d('break: 2');
                                                            d('found: 15');
                                                            break 2;
                                                        }
                                                    }
//                                                    d('break: 3');
                                                    d('found: 16');
                                                    break 3;
                                                }
                                                sort($index_where[2], SORT_NATURAL);
                                                if($index_where[2][0] === $set_init[2]['value']){
                                                    $options['index']['max'] = $seek - 1;
                                                    if($options['index']['max'] < $options['index']['min']){
                                                        $set = [
                                                            false
                                                        ];
                                                        d('found: 17');
                                                        break 2;
                                                    }
                                                    d('found: 18');
                                                    break 3;

                                                } else {
                                                    //sort[1] === $value
                                                    //min becomes seek + 1
                                                    $options['index']['min'] = $seek + 1;
                                                    if($options['index']['max'] < $options['index']['min']){
                                                        $set = [
                                                            false
                                                        ];
                                                        d('found: 19');
                                                        break 2;
                                                    }
                                                    d('found: 20');
                                                    break 3;
                                                }
                                            }
                                            break;
                                        case 'or':
                                            $logger = $object->config('project.log.debug');
                                            if($logger){
                                                $object->logger($logger)->debug('Unknown behavior: index or', [ $record, $set_index, $set_index_where, $index_where]);
                                            }
                                            //first or is true so return
                                            break;
                                        case 'xor':
                                            //first xor is true so check next
                                            $logger = $object->config('project.log.debug');
                                            if($logger){
                                                $object->logger($logger)->debug('Unknown behavior: index xor', [ $record, $set_index, $set_index_where, $index_where]);
                                            }
                                            break;
                                    }
                                } else {
                                    if($set_index_0[0]['match'] === true){
                                        array_shift($set);
                                    } else {
                                        d($set);
                                        d($set_init);
                                        d($set_index_0);
                                        ddd($record);
                                    }
                                }
                            }
                        } else {
                            /*
                            if(
                                array_key_exists(0, $set) &&
                                count($set) === 1
                            ){
                                d($set);
                                d($record);
                                ddd($options);
                            }
                            */
                            if(
                                array_key_exists(0, $set) &&
                                array_key_exists(1, $set) &&
                                array_key_exists(2, $set)
                            ){
                                switch($set[1]) {
                                    case 'and':
                                        if(
                                            $set[0]['match'] === true &&
                                            $set[2]['match'] === true
                                        ){
                                            array_shift($set);
                                            array_shift($set);
                                        } else {
                                            array_shift($set);
                                            array_shift($set);
                                            d($record);
                                            d($set);
                                            d($where_process);
                                            d($index_where);
                                            ddd('found unmatched and');
                                        }
                                        break;
                                    case 'or':
                                        if($set[0]['match'] === true || $set[2]['match'] === true){
                                            $set_index = $set;
                                            array_shift($set_index);
                                            array_shift($set_index);
                                            array_shift($set_index);
                                            if($set[0]['match'] === true){
                                                array_unshift($set_index, $set[0]);
                                            }
                                            elseif($set[2]['match'] === true){
                                                array_unshift($set_index, $set[2]);
                                            }
                                            $set_index_init = $set_index;
                                            $set_index = $this->where_process(
                                                $record,
                                                $set_index,
                                                $set_index_where,
                                                $set_index_key,
                                                $set_index_operator,
                                                $index_where,
                                                $options
                                            );
                                            if($index_where){
                                                switch($set_index[1]){
                                                    case 'and':
                                                        if($set_index[2]['match'] === false){
                                                            sort($index_where[2], SORT_NATURAL);
                                                            if($index_where[2][0] === $set_index_init[2]['value']){
                                                                $options['index']['max'] = $seek - 1;
                                                                d('found: 21');
                                                                break 3;
                                                            } else {
                                                                //min becomes seek + 1
                                                                $options['index']['min'] = $seek + 1;
                                                                d('found: 22');
                                                                break 3;
                                                            }
                                                        }
                                                        break;
                                                    case 'or':
                                                        $logger = $object->config('project.log.debug');
                                                        if($logger){
                                                            $object->logger($logger)->debug('Unknown behavior: index or', [ $record, $set_index, $set_index_where, $index_where]);
                                                        }
                                                        break;
                                                    case 'xor':
                                                        $logger = $object->config('project.log.debug');
                                                        if($logger){
                                                            $object->logger($logger)->debug('Unknown behavior: index xor', [ $record, $set_index, $set_index_where, $index_where]);
                                                        }
                                                        break;
                                                }
                                            }
                                        } elseif(array_key_exists('match', $set[2]) && is_bool($set[2]['match'])) {
                                            array_shift($set);
                                            array_shift($set);
                                            array_shift($set);
                                            array_unshift($set, $set[2]);
                                        }
                                        break;
                                }
                            }
                        }
                        $counter++;
                        if($counter > 1024){
                            d('found: 23');
                            break 2;
                        }
                    }
                    if($record === false){
                        d('found: 24');
                        break 2;
                    }
                    if($deepest === 0){
                        d('found: 25');
                        break;
                    }
                    if(
                        array_key_exists(0, $where_process) &&
                        $where_process[0] === false
                    ){
                        $record = false;
                        d('found: 26');
                        break 2;
                    }
//                    d($where_process);
                    $deepest = $this->where_get_depth($where_process);
                    unset($key);
                    $counter++;
                    $nested_options = $options;
                    $nested_options['where'] = $where_process;
                    $record = $this->index_list_record($class, $role, $nested_options);
                    if($record){
                        d('found: 27');
                        return $record;
                    }
//                    $where_name = $this->index_where_name($name, $nested_options);
                    $options['index']['min'] = 0;
                    $options['index']['max'] = $options['index']['count'] - 1;
                    $max_deep++;
                }
            }
            $seek_old = $seek;
        }
        d($options['index']['min']);
        d($options['index']['max']);
        d('found: 28');
        return $record;
    }

    /**
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function index_create($class, $role, $options=[]): array
    {
        $name = Controller::name($class);
        $object = $this->object();
        $filter_name = $this->index_filter_name($name, $options);
        $where_name = $this->index_where_name($name, $options);
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
        $cache = $object->data(App::CACHE);
        $cache_select = $cache->get(sha1($url_data));
        $count = 0;
        $url_uuid = null;
        $url = [];
        //url_index should be in node/index
        if($where_name === false){
            if($filter_name === false){
                $url_index = $dir_index .
                    $name .
                    '.' .
                    'Filter' .
                    '.' .
                    'uuid' .
                    $object->config('extension.btree');
            } else {
                $key = [
                    'where' => $where_name,
                    'sort' => 'asc'
                ];
                $key = sha1(Core::object($key, Core::OBJECT_JSON));

                $url_uuid = $dir_index .
                    $name .
                    '.' .
                    'Filter' .
                    '.' .
                    $key .
                    '.' .
                    'uuid' .
                    //need filter keys and where attributes
                    $object->config('extension.btree')
                ;
                $url = [];
                foreach ($filter_name as $nr => $attribute) {
                    $url_index = $dir_index .
                        $name .
                        '.' .
                        'Filter' .
                        '.' .
                        $key .
                        '.' .
                        $attribute .
                        //need filter keys and where attributes
                        $object->config('extension.btree')
                    ;
                    if(
                        !in_array(
                            $url_index,
                            $url,
                            true
                        )
                    ){
                        $url[$nr] = $url_index;
                    }
                }
                if(File::mtime($url_uuid) !== $url_mtime){
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
                                'page' => 1,
                                'index' => 'create'
                            ]
                        );
                    }
                    if(
                        is_array($select) &&
                        array_key_exists('list', $select)
                    ){
                        $list = [];
                        $count = 0;
                        foreach($select['list'] as $nr => $record){
                            if(!property_exists($record, 'uuid')){
                                continue;
                            }
                            $record_index = (object) [
                                'uuid' => $record->uuid
                            ];
                            $count++;
                            $sort_key = [];
                            foreach($filter_name as $attribute){
                                if(!property_exists($record, $attribute)){
                                    continue; //no-data
                                }
                                $record_index->{$attribute} = $record->{$attribute};
                                $sort_key[] = '\'' . $record->{$attribute} . '\'';
                            }
                            $record_index->{'#sort'} = implode(',', $sort_key);
                            $list[] = $record_index;
                        }
                        $list = Sort::list($list)->with([
                            '#sort' => 'asc'
                        ]);
                        $data = [];
                        foreach($list as $uuid => $record){
                            if(!array_key_exists('uuid', $data)){
                                $data['uuid'] = [];
                            }
                            $data['uuid'][] = $record->uuid;
                            foreach($filter_name as $nr => $attribute){
                                if(!array_key_exists($nr, $data)){
                                    $data[$nr] = [];
                                }
                                $data[$nr][] = $record->{$attribute};
                            }
                        }
                        if(!Dir::exist($dir_index)){
                            Dir::create($dir_index, Dir::CHMOD);
                        }
                        File::write($url_uuid, implode(PHP_EOL, $data['uuid']));
                        File::touch($url_uuid, $url_mtime);
                        foreach($url as $nr => $url_index){
                            File::write($url_index, implode(PHP_EOL, $data[$nr]));
                            File::touch($url_index, $url_mtime);
                        }
                    }
                }
            }
        }
        elseif($where_name) {
            $key = [
                'where' => $where_name,
                'sort' => 'asc'
            ];
            $key = sha1(Core::object($key, Core::OBJECT_JSON));
            $url = [];
            $url_uuid = $dir_index .
                $name .
                '.' .
                'Where' .
                '.' .
                $key .
                '.' .
                'uuid' .
                //need filter keys and where attributes
                $object->config('extension.btree')
            ;
            foreach ($where_name as $nr => $attribute) {
                $url_index = $dir_index .
                    $name .
                    '.' .
                    'Where' .
                    '.' .
                    $key .
                    '.' .
                    $attribute .
                    //need filter keys and where attributes
                    $object->config('extension.btree')
                ;
                if(
                    !in_array(
                        $url_index,
                        $url,
                        true
                    )
                ){
                    $url[$nr] = $url_index;
                }
            }
            if(File::mtime($url_uuid) !== $url_mtime){
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
                            'page' => 1,
                            'index' => 'create'
                        ]
                    );
                }
                if(
                    is_array($select) &&
                    array_key_exists('list', $select)
                ){
                    $list = [];
                    $count = 0;
                    foreach($select['list'] as $nr => $record){
                        if(!property_exists($record, 'uuid')){
                            continue;
                        }
                        $record_index = (object) [
                            'uuid' => $record->uuid
                        ];
                        $count++;
                        $sort_key = [];
                        foreach($where_name as $attribute){
                            if(!property_exists($record, $attribute)){
                                continue; //no-data
                            }
                            $record_index->{$attribute} = $record->{$attribute};
                            $sort_key[] = '\'' . $record->{$attribute} . '\'';
                        }
                        $record_index->{'#sort'} = implode(',', $sort_key);
                        $list[] = $record_index;
                    }
                    $list = Sort::list($list)->with([
                        '#sort' => 'asc'
                    ]);
                    $data = [];
                    foreach($list as $uuid => $record){
                        if(!array_key_exists('uuid', $data)){
                            $data['uuid'] = [];
                        }
                        $data['uuid'][] = $record->uuid;
                        foreach($where_name as $nr => $attribute){
                            if(!array_key_exists($nr, $data)){
                                $data[$nr] = [];
                            }
                            if(property_exists($record, $attribute)){
                                $data[$nr][] = $record->{$attribute};
                            }
                        }
                    }
                    if(!Dir::exist($dir_index)){
                        Dir::create($dir_index, Dir::CHMOD);
                    }
                    File::write($url_uuid, implode(PHP_EOL, $data['uuid']));
                    File::touch($url_uuid, $url_mtime);
                    foreach($url as $nr => $url_index){
                        File::write($url_index, implode(PHP_EOL, $data[$nr]));
                        File::touch($url_index, $url_mtime);
                    }
                }
            }
        }
        return [
            'url' => $url,
            'url_uuid' => $url_uuid,
            'count' => $count,
            'filter' => $filter_name,
            'where'=> $where_name,
        ];
    }

    /**
     * @throws Exception
     */
    /*
    private function index_list($name, $select=[], $filter_name=false, &$count_index=false, &$is_uuid=false){
        //nodelist all records in chunks of 4096 so we can parallelize the process later on.
        if(!array_key_exists('list', $select)){
            return false; //no-data
        }
        $object = $this->object();
        $cache = $object->data(App::CACHE);
        $list = [];
        $count_index = 0;
        $is_uuid = false;
        foreach($select['list'] as $nr => $record){
            if(
                is_object($record) &&
                property_exists($record, 'uuid')
            ){
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
    */

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
                    if(
                        !in_array(
                            $attribute,
                            $filter,
                            true
                        )
                    ){
                        $filter[] = $attribute;
                        $is_filter = true;
                    }
                }
            }
            elseif(is_object($options['filter'])){
                foreach($options['filter'] as $attribute => $record){
                    if(
                        !in_array(
                            $attribute,
                            $filter,
                            true
                        )
                    ){
                        $filter[] = $attribute;
                        $is_filter = true;
                    }
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
                        if(
                            !in_array(
                                $record['attribute'],
                                $where,
                                true
                            )
                        ){
                            $where[] = $record['attribute'];
                            $is_where = true;
                        }

                    }
                    elseif(
                        is_object($record) &&
                        property_exists($record, 'attribute')
                    ){
                        if(
                            !in_array(
                                $record->attribute,
                                $where,
                                true
                            )
                        ){
                            $where[] = $record->attribute;
                            $is_where = true;
                        }
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