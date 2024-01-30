<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;
use R3m\Io\Module\Route;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;

trait NodeList {

    /**
     * @throws Exception
     */
    public function list($class, $role, $options=[]): array
    {
        Core::interactive();
        $mtime = false;
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $object = $this->object();
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('relation', $options)){
            $options['relation'] = false;
        }
        if(!array_key_exists('parse', $options)){
            $options['parse'] = false;
        }
        if(!array_key_exists('transaction', $options)){
            $options['transaction'] = false;
        }
        if(!array_key_exists('lock', $options)){
            $options['lock'] = false;
        }
        if(!Security::is_granted(
            $name,
            $role,
            $options
        )){
            $list = [];
            $result = [];
            $result['page'] = $options['page'] ?? 1;
            $result['limit'] = $options['limit'] ?? 1000;
            $result['count'] = 0;
            $result['max'] = 0;
            $result['list'] = $list;
            $result['sort'] = $options['sort'];
            if(!empty($options['filter'])) {
                $result['filter'] = $options['filter'];
            }
            if(!empty($options['where'])) {
                $result['where'] = $options['where'];
            }
            $result['relation'] = $options['relation'];
            $result['parse'] = $options['parse'];
            $result['ramdisk'] = $options['ramdisk'] ?? false;
            $result['mtime'] = $mtime;
            $result['transaction'] = $options['transaction'] ?? false;
            $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
            return $result;
        }
        $data_url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        if(!File::exist($data_url)){
            $list = [];
            $result = [];
            $result['page'] = $options['page'] ?? 1;
            $result['limit'] = $options['limit'] ?? 1000;
            $result['count'] = 0;
            $result['max'] = 0;
            $result['list'] = $list;
            $result['sort'] = $options['sort'] ?? [];
            if(!empty($options['filter'])) {
                $result['filter'] = $options['filter'];
            }
            if(!empty($options['where'])) {
                $result['where'] = $options['where'];
            }
            $result['relation'] = $options['relation'];
            $result['parse'] = $options['parse'];
            $result['ramdisk'] = $options['ramdisk'] ?? false;
            $result['mtime'] = $mtime;
            $result['transaction'] = $options['transaction'] ?? false;
            $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
            return $result;
        }
        $mtime = File::mtime($data_url);
        $ramdisk_dir = false;
        $ramdisk_dir_node = false;
        $ramdisk_url_node = false;
        if(
            array_key_exists('ramdisk', $options) &&
            $options['ramdisk'] === true &&
            (
                !empty($object->config('ramdisk.url')) ||
                array_key_exists('ramdisk_dir', $options)
            )
        ){
            $key_options = $options;
            if(
                is_object($role) &&
                property_exists($role, 'uuid')
            ){
                //per role cache
                $key_options['role'] = $role->uuid;
            } else {
                throw new Exception('Role not set for ramdisk');
            }
            //cache key
            $key = sha1(Core::object($key_options, Core::OBJECT_JSON));
            if(array_key_exists('ramdisk_dir', $options)){
                $ramdisk_dir = $options['ramdisk_dir'];
            } else {
                $ramdisk_dir = $object->config('ramdisk.url') .
                    $object->config('posix.id') .
                    $object->config('ds')
                ;
            }
            if(empty($ramdisk_dir)){
                throw new Exception('Ramdisk dir not set');
            }
            $ramdisk_dir_node = $ramdisk_dir .
                'Node' .
                $object->config('ds')
            ;
            $ramdisk_url_node = $ramdisk_dir_node .
                $name .
                '.' .
                $key .
                $object->config('extension.json')
            ;
            if(File::exist($ramdisk_url_node)){
                $ramdisk = $object->data_read($ramdisk_url_node);
                if($ramdisk){
                    $is_cache_miss = false;
                    if($mtime === $ramdisk->get('mtime')) {
                        $relations = $ramdisk->get('relation');
                        if ($relations) {
                            foreach ($relations as $relation_url => $relation_mtime) {
                                if (!File::exist($relation_url)) {
                                    $is_cache_miss = true;
                                    break;
                                }
                                if ($relation_mtime !== File::mtime($relation_url)) {
                                    $is_cache_miss = true;
                                    break;
                                }
                            }
                        }
                    } else {
                        $is_cache_miss = true;
                    }
                    if($is_cache_miss === false){
                        $response = (array) $ramdisk->get('response');
                        if($response){
                            if(
                                array_key_exists('duration', $response)
                            ){
                                $response['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                            }
                            return $response;
                        }
                    }
                }
            }
        }
        if($options['transaction'] === true){
            //keep an eye on memory usage of this script, because it grows here...
            $data = $object->data_read($data_url, sha1($data_url));
        } else {
            $data = $object->data_read($data_url);
        }
        $object_url = $object->config('project.dir.node') .
            'Object' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        if($options['transaction'] === true){
            $object_data = $object->data_read($object_url, sha1($object_url));
        } else {
            $object_data = $object->data_read($object_url);
        }
        $has_relation = false;
        $count = 0;
        $list_filtered = [];
        if($data){
            $list = $data->data($name);
            if(
                !empty($list) &&
                is_array($list)
            ){
                $max = count($list);
                $relation = [];
                if($object_data){
                    $relation = $object_data->get('relation');
                }
                if(
                    !empty($relation) &&
                    is_array($relation) &&
                    array_key_exists('relation', $options) &&
                    $options['relation'] === true
                ){
                    $has_relation = true;
                }
                $is_filter = false;
                $is_where = false;
                if(
                    !empty(
                    $options['filter']) &&
                    is_array($options['filter'])
                ){
                    $is_filter = true;
                }
                elseif(
                    !empty($options['where']) &&
                    (
                        is_string($options['where']) ||
                        is_array($options['where'])
                    )
                ){
                    if(is_string($options['where'])){
                        $options['where'] = $this->where_convert($options['where']);
                    }
                    $is_where = true;
                }
                $limit = $options['limit'] ?? 4096;
                foreach($list as $nr => $record) {
                    if(
                        is_object($record) &&
                        property_exists($record, '#class')
                    ){
                        $expose = $this->expose_get(
                            $object,
                            $record->{'#class'},
                            $record->{'#class'} . '.' . $options['function'] . '.expose'
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
                        if($has_relation){
                            $record = $this->relation($record, $object_data, $role, $options);
                            //collect relation mtime
                        }
                        //parse the record if parse is enabled
                        if($is_filter){
                            $record = $this->filter($record, $options['filter'], $options);
                            if(!$record){
                                unset($list[$nr]);
                                continue;
                            }
                        }
                        elseif($is_where){
                            $record = $this->where($record, $options['where'], $options);
                            if(!$record){
                                unset($list[$nr]);
                                continue;
                            }
                        }
                        $count++;
                        $list_filtered[] = $record;
                        if($count === $limit){
                            break;
                        }
                    }
                }
                $list = $list_filtered;
                if(
                    !empty($options['sort']) &&
                    is_array($options['sort'])
                ){
                    $list_sort = Sort::list($list)->with(
                        $options['sort'],
                        [
                            'key_reset' => true,
                        ]
                    );
                } else {
                    $list_sort = $list;
                }
                if(
                    !empty($limit) &&
                    $limit === '*'
                ){
                    $list_count = 0;
                    foreach($list_sort as $index => $record){
                        if(is_object($record)){
                            $record->{'#index'} = $index;
                        }
                        $list_count++;
                    }
                    $result = [];
                    $result['page'] = 1;
                    $result['limit'] = $list_count;
                    $result['count'] = $list_count;
                    $result['max'] = $max;
                    $result['list'] = $this->nodeList_output_filter($object, $list_sort, $options);
                    $result['sort'] = $options['sort'] ?? [];
                    $result['filter'] = $options['filter'] ?? [];
                    $result['where'] = $options['where'] ?? [];
                    $result['relation'] = $options['relation'] ?? true;
                    $result['parse'] = $options['parse'] ?? false;
                    $result['ramdisk'] = $options['ramdisk'] ?? false;
                    $result['mtime'] = $mtime;
                    $result['transaction'] = $options['transaction'] ?? false;
                    $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                    if(
                        array_key_exists('ramdisk', $options) &&
                        $options['ramdisk'] === true &&
                        $ramdisk_url_node !== false
                    ){
                        $relation_mtime = $this->relation_mtime($object_data);
                        $ramdisk = new Storage();
                        $ramdisk->set('mtime', $mtime);
                        $ramdisk->set('response', $result);
                        $ramdisk->set('relation', $relation_mtime);
                        $ramdisk->write($ramdisk_url_node);
                    }
                    return $result;
                }
                $page = $options['page'] ?? 1;
                $limit = $options['limit'] ?? 4096;
                $list_temp = [];
                $list_count = 0;
                foreach($list_sort as $index => $record){
                    if(
                        $index < ($page - 1) * $limit
                    ){
                        //nothing
                    }
                    elseif($index >= $page * $limit){
                        break;
                    }
                    else {
                        if(is_object($record)){
                            $record->{'#index'} = $index;
                        }
                        $list_temp[] = $record;
                        $list_count++;
                    }
                }
                $list = $list_temp;
                $result = [];
                $result['page'] = $page;
                $result['limit'] = $limit;
                $result['count'] = $list_count;
                $result['max'] = $max;
                $result['list'] = $this->nodeList_output_filter($object, $list, $options);
                $result['sort'] = $options['sort'] ?? [];
                $result['filter'] = $options['filter'] ?? [];
                $result['where'] = $options['where'] ?? [];
                $result['relation'] = $options['relation'] ?? true;
                $result['parse'] = $options['parse'] ?? false;
                $result['ramdisk'] = $options['ramdisk'] ?? false;
                $result['mtime'] = $mtime;
                $result['transaction'] = $options['transaction'] ?? false;
                $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                if(
                    array_key_exists('ramdisk', $options) &&
                    $options['ramdisk'] === true &&
                    $ramdisk_url_node !== false &&
                    $ramdisk_dir !== false &&
                    $ramdisk_dir_node !== false
                ){
                    $relation_mtime = $this->relation_mtime($object_data);
                    $ramdisk = new Storage();
                    $ramdisk->set('mtime', $mtime);
                    $ramdisk->set('response', $result);
                    $ramdisk->set('relation', $relation_mtime);
                    $ramdisk->write($ramdisk_url_node);
                    if($object->config('posix.id') === 0){
                        //nothing
                        /*
                        File::permission($object, [
                            'ramdisk_dir' => $ramdisk_dir
                        ]);
                        */
                    } else {
                        File::permission($object, [
                            'ramdisk_dir' => $ramdisk_dir,
                            'ramdisk_dir_node' => $ramdisk_dir_node,
                            'ramdisk_url_node' => $ramdisk_url_node,
                        ]);
                    }
                }
                return $result;
            }
        }
        $list = [];
        $result = [];
        $result['page'] = $options['page'] ?? 1;
        $result['limit'] = $options['limit'] ?? 1000;
        $result['count'] = 0;
        $result['max'] = 0;
        $result['list'] = $list;
        $result['sort'] = $options['sort'] ?? [];
        if(!empty($options['filter'])) {
            $result['filter'] = $options['filter'];
        }
        if(!empty($options['where'])) {
            $result['where'] = $options['where'];
        }
        $result['relation'] = $options['relation'];
        $result['parse'] = $options['parse'];
        $result['ramdisk'] = $options['ramdisk'] ?? false;
        $result['mtime'] = $mtime;
        $result['transaction'] = $options['transaction'] ?? false;
        $result['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
        return $result;
    }

    private function nodeList_output_filter(App $object, $list, $options=[]): mixed
    {
        if(!array_key_exists('output', $options)){
            return $list;
        }
        if(!array_key_exists('filter', $options['output'])){
            return $list;
        }
        $output_filter = $options['output']['filter'];
        if($output_filter){
            foreach($output_filter as $output_filter_data){
                $route = (object) [
                    'controller' => $output_filter_data
                ];
                $route = Route::controller($route);
                if(
                    property_exists($route, 'controller') &&
                    property_exists($route, 'function')
                ){
                    //don't check on empty $list, an output filter can have defaults...
                    $list = $route->controller::{$route->function}($object, $list);
                }
            }
        }
        return $list;
    }
}