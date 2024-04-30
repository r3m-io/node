<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Parallel;
use R3m\Io\Module\Parse;
use R3m\Io\Module\Route;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;

/**
 * app r3m_io/node list -class=RaXon.Php.Word.Embedding -page=1 -limit=10 -parallel -thread=96 -ramdisk
 * count 960 duration: 120.88 msec
 * app r3m_io/node list -class=RaXon.Php.Word.Embedding -page=1 -limit=100 -parallel -thread=96 -ramdisk
 * count 9600 duration: 1050.85 msec
 */
trait NodeList {

    /**
     * @throws Exception
     */
    public function list($class, $role, $options=[]): array
    {
        $mtime = false;
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $object = $this->object();
        $parse = false;
        if (!array_key_exists('function', $options)) {
            $options['function'] = __FUNCTION__;
        }
        if (!array_key_exists('relation', $options)) {
            $options['relation'] = false;
        }
        if (!array_key_exists('parse', $options)) {
            $options['parse'] = false;
        }
        if (!array_key_exists('transaction', $options)) {
            $options['transaction'] = false;
        }
        if (!array_key_exists('lock', $options)) {
            $options['lock'] = false;
        }
        if (!array_key_exists('key', $options)) {
            $options['key'] = null; //numeric
        }
        if (!array_key_exists('memory', $options)) {
            $options['memory'] = false; //true
        }
        if (!array_key_exists('parallel', $options)) {
            $options['parallel'] = false; //true
        }
        if (!array_key_exists('thread', $options)) {
            if(array_key_exists('threads', $options)){
                $options['thread'] = $options['threads'];
            } else {
                $options['thread'] = 8;
            }
        }
        if (!Security::is_granted(
            $name,
            $role,
            $options
        )) {
            $list = [];
            $result = [];
            $result['page'] = $options['page'] ?? 1;
            $result['limit'] = $options['limit'] ?? 1000;
            $result['count'] = 0;
            $result['max'] = 0;
            $result['list'] = $list;
            $result['sort'] = $options['sort'];
            if (!empty($options['filter'])) {
                $result['filter'] = $options['filter'];
            }
            if (!empty($options['where'])) {
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
        if(
            $options['parse'] === true ||
            (
                array_key_exists('pre-compile', $options) &&
                $options['pre-compile'] === true
            )
        ){
            $controller_dir_root = $object->config('controller.dir.root');
            if(!$controller_dir_root){
                $object->config(
                    'controller.dir.root',
                    $object->config('project.dir.root') .
                    'vendor' .
                    $object->config('ds') .
                    'r3m_io' .
                    $object->config('ds') .
                    'framework' .
                    $object->config('ds') .
                    'src' .
                    $object->config('ds')
                );
            }
            $parse = new Parse($object);

        }
        $data_url = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            $name .
            $object->config('extension.json');
        if (!File::exist($data_url)) {
            $list = [];
            $result = [];
            $result['page'] = $options['page'] ?? 1;
            $result['limit'] = $options['limit'] ?? 1000;
            $result['count'] = 0;
            $result['max'] = 0;
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
        $data = null;
        if (
            array_key_exists('ramdisk', $options) &&
            $options['ramdisk'] === true &&
            (
                !empty($object->config('ramdisk.url')) ||
                array_key_exists('ramdisk_dir', $options)
            )
        ) {
            $key_options = $options;
            if (
                is_object($role) &&
                property_exists($role, 'uuid')
            ) {
                //per role cache
                $key_options['role'] = $role->uuid;
            } else {
                throw new Exception('Role not set for ramdisk');
            }
            //cache key
            $key = sha1(Core::object($key_options, Core::OBJECT_JSON));
            if (
                array_key_exists('ramdisk_dir', $options) &&
                $options['ramdisk_dir'] !== false
            ) {
                $ramdisk_dir = $options['ramdisk_dir'];
            } else {
                $ramdisk_dir = $object->config('ramdisk.url') .
                    $object->config('posix.id') .
                    $object->config('ds');
            }
            if (empty($ramdisk_dir)) {
                throw new Exception('Ramdisk dir not set');
            }
            $ramdisk_dir_node = $ramdisk_dir .
                'Node' .
                $object->config('ds')
            ;
            $ramdisk_dir_list = $ramdisk_dir_node .
                'List' .
                $object->config('ds')
            ;
            $ramdisk_url_node = $ramdisk_dir_list .
                $name .
                '.' .
                $key .
                $object->config('extension.json');
            if (File::exist($ramdisk_url_node)) {
                if ($options['transaction'] === true) {
                    $ramdisk = $object->data_read($ramdisk_url_node, sha1($ramdisk_url_node));
                } else {
                    $ramdisk = $object->data_read($ramdisk_url_node);
                }
                if ($ramdisk) {
                    $is_cache_miss = false;
                    if ($mtime === $ramdisk->get('mtime')) {
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
                    if ($is_cache_miss === false) {
                        $response = (array)$ramdisk->get('response');
                        if ($response) {
                            if (
                                array_key_exists('duration', $response)
                            ) {
                                $response['duration'] = (microtime(true) - $object->config('time.start')) * 1000;
                            }
                            return $response;
                        }
                    }
                }
            }
        }
        if (
            $options['transaction'] === true ||
            $options['memory'] === true
        ) {
            //keep an eye on memory usage of this script, because it grows here...
            if(array_key_exists('view', $options)){
                $view_url = $object->config('ramdisk.url') .
                    $object->config('posix.id') .
                    $object->config('ds') .
                    'Node' .
                    $object->config('ds') .
                    'View' .
                    $object->config('ds') .
                    $name .
                    $object->config('ds') .
                    'List' .
                    $object->config('ds') .
                    $options['view'] .
                    $object->config('extension.json')
                ;
                $data = $object->data_read($view_url, sha1($view_url));
            }
            if(!$data){
                $data = $object->data_read($data_url, sha1($data_url));
            }
        } else {
            if(array_key_exists('view', $options)){
                $view_url = $object->config('ramdisk.url') .
                    $object->config('posix.id') .
                    $object->config('ds') .
                    'Node' .
                    $object->config('ds') .
                    'View' .
                    $object->config('ds') .
                    $name .
                    $object->config('ds') .
                    'List' .
                    $object->config('ds') .
                    $options['view'] .
                    $object->config('extension.json')
                ;
                $data = $object->data_read($view_url);
            }
            if(!$data) {
                $data = $object->data_read($data_url);
            }
        }
        $object_url = $object->config('project.dir.node') .
            'Object' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        if (
            $options['transaction'] === true ||
            $options['memory'] === true
        ) {
            $object_data = $object->data_read($object_url, sha1($object_url));
        } else {
            $object_data = $object->data_read($object_url);
        }
        $has_relation = false;
        $count = 0;
        $list_filtered = [];
        if ($data) {
            $list = $data->data($name);
            if (
                !empty($list) &&
                is_array($list)
            ) {
                $max = count($list);
                $relation = [];
                if ($object_data) {
                    $relation = $object_data->get('relation');
                }
                if (
                    !empty($relation) &&
                    is_array($relation) &&
                    array_key_exists('relation', $options) &&
                    $options['relation'] === true
                ) {
                    $has_relation = true;
                }
                $is_filter = false;
                $is_where = false;
                if (
                    !empty(
                    $options['filter']) &&
                    is_array($options['filter'])
                ) {
                    $is_filter = true;
                } elseif (
                    !empty($options['where']) &&
                    (
                        is_string($options['where']) ||
                        is_array($options['where'])
                    )
                ) {
                    if (is_string($options['where'])) {
                        $options['where'] = $this->where_convert($options['where']);
                    }
                    if (is_array($options['where'])) {
                        foreach ($options['where'] as $key => $where) {
                            if (is_string($where)) {
                                $split = mb_str_split($where);
                                $is_quote = false;
                                $attribute = '';
                                $operator = '';
                                $value = '';
                                $is_attribute = false;
                                $is_operator = false;
                                $is_value = false;
                                foreach ($split as $nr => $char) {
                                    if ($char === '\'') {
                                        if ($is_quote === false) {
                                            $is_quote = true;
                                        } else {
                                            $is_quote = false;
                                        }
                                        continue;
                                    }
                                    if (
                                        $char === ' ' &&
                                        $is_quote === false &&
                                        $is_attribute === false
                                    ) {
                                        $is_attribute = $attribute;
                                        continue;
                                    } elseif ($char === ' ' &&
                                        $is_quote === false &&
                                        $is_operator === false
                                    ) {
                                        $is_operator = $operator;
                                        continue;
                                    }
                                    if ($is_attribute === false) {
                                        $attribute .= $char;
                                    } elseif (
                                        $is_attribute &&
                                        $is_operator === false
                                    ) {
                                        $operator .= $char;
                                    } elseif (
                                        $is_attribute &&
                                        $is_operator &&
                                        $is_value === false
                                    ) {
                                        $value .= $char;
                                    }
                                }
                                if ($attribute && $operator && $value) {
                                    $options['where'][$key] = [
                                        'attribute' => $attribute,
                                        'operator' => $operator,
                                        'value' => $value
                                    ];
                                }

                            }
                        }
                    }
                    $is_where = true;
                }
                $limit = $options['limit'] ?? 4096;
                if ($options['parallel'] === true && Core::is_cli()) {
                    $threads = $options['thread'];
                    $chunks = array_chunk($list, ceil(count($list) / $threads));
                    $chunk_count = count($chunks);
                    $count = 0;
                    $done = 0;
                    $result = [];
                    $expose = false;
                    $closures = [];
                    $ramdisk_dir_parallel = false;
                    $ramdisk_dir_parallel_name = false;
                    if (
                        array_key_exists('ramdisk', $options) &&
                        $options['ramdisk'] === true
                    ){
                        $ramdisk_dir_parallel = $ramdisk_dir_node .
                            'Parallel' .
                            $object->config('ds')
                        ;
                        $ramdisk_dir_parallel_name = $ramdisk_dir_parallel .
                            $name .
                            $object->config('ds')
                        ;
                    }
                    foreach ($chunks as $chunk_nr => $chunk) {
                        $forks = count($chunk);
                        $chunk_url = null;
                        if($ramdisk_dir_parallel_name){
                            $chunk_url = $ramdisk_dir_parallel_name .
                                'Chunk-' .
                                ($chunk_nr + 1) .
                                '-' .
                                $threads .
                                $object->config('extension.json')
                            ;
                        }
                        if(
                            $chunk_url !== null &&
                            File::exist($chunk_url) &&
                            File::mtime($chunk_url) === $mtime
                        ){
                            //we have valid cache of the chunk.
                            $read = $object->data_read($chunk_url);
                            $chunk = $read->data();
                        } else {
                            for ($i = 0; $i < $forks; $i++) {
                                $record = $chunk[$i];
                                if (
                                    is_object($record) &&
                                    property_exists($record, '#class')
                                ) {
                                    if (!$expose) {
                                        $expose = $this->expose_get(
                                            $object,
                                            $record->{'#class'},
                                            $record->{'#class'} . '.' . $options['function'] . '.output'
                                        );
                                    }
                                    $node = new Storage($record);
                                    $node = $this->expose(
                                        $node,
                                        $expose,
                                        $record->{'#class'},
                                        $options['function'],
                                        $role
                                    );
                                    $record = $node->data();
                                    if ($has_relation) {
                                        $record = $this->relation($record, $object_data, $role, $options);
                                        //collect relation mtime
                                    }
                                    //parse the record if parse is enabled, parsing cannot run in parallel
                                    // this should be called: pre.compile
                                    if(
                                        array_key_exists('pre-compile', $options) &&
                                        $options['pre-compile'] === true &&
                                        $parse !== false
                                    ){
                                        $record = $parse->compile($record, $object->data(), $parse->storage());
                                        $chunks[$chunk_nr][$i] = $record;
                                    }

                                    $chunk[$i] = $record;
                                }
                            }
                            if(
                                $ramdisk_dir_parallel &&
                                $ramdisk_dir_parallel_name
                            ){
                                Dir::create($ramdisk_dir_parallel_name, Dir::CHMOD);
                                File::write($chunk_url, Core::object($chunk, Core::OBJECT_JSON));
                                File::touch($chunk_url, $mtime);
                                if($object->config('posix.id') !== 0){
                                    File::permission($object, [
                                        'ramdisk_dir_parallel' => $ramdisk_dir_parallel,
                                        'ramdisk_dir_parallel_name' => $ramdisk_dir_parallel_name,
                                        'chunk_url' => $chunk_url,
                                    ]);
                                }
                            }
                        }
//                        $this->index_create_chunk($object_data, $chunk, $chunk_nr, $threads, $mtime);
                        $closures[] = function () use (
                            $object,
                            $chunk,
                            $chunk_nr,
                            $threads,
                            $forks,
                            $limit,
                            $count,
                            $mtime,
                            $name,
                            $options,
                            $is_filter,
                            $is_where
                        ) {
                            $result = [];
                            /*
                            $options['index'] = [
                                'class' => $name,
                                'chunk_nr' => $chunk_nr,
                                'threads' => $threads,
                                'mtime' => $mtime,
                                'unique' => true
                            ];
                            */
                            for ($i = 0; $i < $forks; $i++) {
                                $record = $chunk[$i];
//                                $options['index']['iterator'] = $i;
                                if ($is_filter) {
                                    $record = $this->filter($record, $options['filter'], $options);
                                    if (!$record) {
                                        $result[$i] = 0;
                                        continue;
                                    }
                                } elseif ($is_where) {
                                    $record = $this->where($record, $options['where'], $options);
                                    if (!$record) {
                                        $result[$i] = 0;
                                        continue;
                                    }
                                }
                                $count++;
                                $result[$i] = 1;
                                if ($count === $limit) {
                                    break;
                                }
                            }
                            return $result;
                        };
                    }
                    $expose = false;
                    $list_parallel = Parallel::new()->execute($closures);
                    foreach($list_parallel as $nr => $list_parallel_result){
                        if(is_array($list_parallel_result)){
                            foreach($list_parallel_result as $i => $bool){
                                if($bool === 1){
                                    $record = $chunks[$nr][$i];
                                    if(
                                        $options['parse'] === true &&
                                        $parse !== false
                                    ){
                                        $record = $parse->compile($record, $object->data(), $parse->storage());
                                    }
                                    if(array_key_exists('view', $options)){
                                        $view_url = $object->config('ramdisk.url') .
                                            $object->config('posix.id') .
                                            $object->config('ds') .
                                            'Node' .
                                            $object->config('ds') .
                                            'View' .
                                            $object->config('ds') .
                                            $name .
                                            $object->config('ds') .
                                            'Record' .
                                            $object->config('ds') .
                                            $record->uuid .
                                            $object->config('extension.json')
                                        ;
                                        $view_data = $object->data_read($view_url, sha1($view_url));
                                        if($view_data){
                                            $record = $view_data->data();
                                            if (!$expose) {
                                                $expose = $this->expose_get(
                                                    $object,
                                                    $record->{'#class'},
                                                    $record->{'#class'} . '.' . $options['function'] . '.output'
                                                );
                                            }
                                            $node = new Storage($record);
                                            $node = $this->expose(
                                                $node,
                                                $expose,
                                                $record->{'#class'},
                                                $options['function'],
                                                $role
                                            );
                                            $record = $node->data();
                                            if ($has_relation) {
                                                $record = $this->relation($record, $object_data, $role, $options);
                                                //collect relation mtime
                                            }
                                        }
                                    }
                                    $result[] = $record;
                                }
                            }
                        }
                    }
                    $list = $result;
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
                    $limit = '*'; //handler
                } else {
                    $expose = false;
                    foreach($list as $nr => $record) {
                        if(
                            is_object($record) &&
                            property_exists($record, '#class')
                        ){
                            if(!$expose){
                                $expose = $this->expose_get(
                                    $object,
                                    $record->{'#class'},
                                    $record->{'#class'} . '.' . $options['function'] . '.output'
                                );
                            }
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
                            if(
                                array_key_exists('pre-compile', $options) &&
                                $options['pre-compile'] === true &&
                                $parse !== false
                            ){
                                $record = $parse->compile($record, $object->data(), $parse->storage());
                            }
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
                            if(
                                $options['parse'] === true &&
                                $parse !== false
                            ){
                                $record = $parse->compile($record, $object->data(), $parse->storage());
                            }
                            $count++;
                            if($options['key'] === null){
                                $list_filtered[] = $record;
                            }
                            elseif(is_array($options['key'])) {
                                $key = [];
                                foreach($options['key'] as $attribute){
                                    $value = $node->get($attribute);
                                    if(is_scalar($value) || $value === null){
                                        $key[] = $value;
                                    } else {
                                        $key[] = Core::object($value, Core::OBJECT_JSON);
                                    }
                                }
                                $key = implode('', $key);
                                $list_filtered[$key] = $record;
                            }
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
                }
                if(
                    !empty($limit) &&
                    $limit === '*'
                ){
                    $list_count = 0;
                    foreach($list_sort as $index => $record){
                        if(is_object($record)){
                            $record->{'#index'} = $list_count;
                        }
                        $list_count++;
                    }
                    if(array_key_exists('view', $options)){
                        d($list_sort);
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
                    $result['pre-compile'] = $options['pre-compile'] ?? false;
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
                $index_counter = 0;
                foreach($list_sort as $index => $record){
                    if(
                        $index_counter < ($page - 1) * $limit
                    ){
                        //nothing
                    }
                    elseif($index_counter >= $page * $limit){
                        break;
                    }
                    else {
                        if(is_object($record)){
                            $record->{'#index'} = $index_counter;
                        }
                        if($options['key'] === null){
                            $list_temp[] = $record;
                        }
                        elseif(is_array($options['key'])) {
                            $list_temp[$index] = $record;
                        }
                        $list_count++;
                    }
                    $index_counter++;
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
                $result['pre-compile'] = $options['pre-compile'] ?? false;
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
                            'ramdisk_dir_list' => $ramdisk_dir_list,
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
        $result['pre-compile'] = $options['pre-compile'] ?? false;
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