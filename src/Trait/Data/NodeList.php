<?php

namespace R3m\Io\Node\Trait\Data;

use ErrorException;
use R3m\Io\App;

use R3m\Io\Config;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Filter;
use R3m\Io\Module\Limit;
use R3m\Io\Module\Parallel;
use R3m\Io\Module\Parse;
use R3m\Io\Module\Route;
use R3m\Io\Module\SharedMemory;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;
use SplFileObject;

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
        $start = false;
        if(
            array_key_exists('duration', $options) &&
            $options['duration'] === true
        ){
            $start = microtime(true);
        }
//        d($options);
//        d($name);
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
        if(!array_key_exists('strategy', $options)){
            $options['strategy'] = 'around';
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
        if(array_key_exists('where', $options)){
            $where = false;
            if(
                is_string($options['where']) ||
                is_array($options['where'])
            ){
                $where = $this->list_where($options);
            }
            $options['where'] = $where;
        }
        $options['page'] = $options['page'] ?? 1;
        $options['limit'] = $options['limit'] ?? 1000;
        if (!Security::is_granted(
            $name,
            $role,
            $options
        )) {
            $list = [];
            $result = [];
            $result['page'] = $options['page'];
            $result['limit'] = $options['limit'];
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
            if($start){
                $result['#duration'] = (object) [
                    'boot' => ($start - $object->config('time.start')) * 1000,
                    'total' => (microtime(true) - $object->config('time.start')) * 1000,
                    'nodelist' => (microtime(true) - $start) * 1000
                ];
                $result['#duration']->item_per_second = 0;
                $result['#duration']->item_per_second_nodelist = 0;
            }
            return $result;
        }
        if (!array_key_exists('index', $options)) {
            $options['index'] = false;
        }
        elseif($options['index'] === true){
            Core::interactive();
            $options['index'] = $this->index_create($name, $role, $options);
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
        /*
        if(stristr($name, 'account.permission')){
            $data = $object->data_read($data_url);
            if($data){
                $start = 0;
                $limit = 100000;
                $list = [];
                for($i=$start; $i < $limit; $i++){
                    $record = (object) [
                        'uuid' => Core::uuid(),
                        '#class' => $name,
                        'name' => 'permission:' . $i,
                    ];
                    $list[]= $record;
                }
                $data->data($name, $list);
                $data->write($data_url);
            }
            ddd($data_url);
        }
        */

        if (!File::exist($data_url)) {
            $list = [];
            $result = [];
            $result['page'] = $options['page'];
            $result['limit'] = $options['limit'];
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
            if($start){
                $result['#duration'] = (object) [
                    'boot' => ($start - $object->config('time.start')) * 1000,
                    'total' => (microtime(true) - $object->config('time.start')) * 1000,
                    'nodelist' => (microtime(true) - $start) * 1000
                ];
                $result['#duration']->item_per_second = 0;
                $result['#duration']->item_per_second_nodelist = 0;
            }
            return $result;
        }
        $mtime = File::mtime($data_url);
        if(
            $options['index'] !== false &&
            $options['index'] !== 'create' &&
            array_key_exists('url', $options['index']) &&
            array_key_exists('url_uuid', $options['index']) &&
            array_key_exists('count', $options['index']) &&
            array_key_exists('filter', $options['index']) &&
            array_key_exists('where', $options['index'])
        ){
            if($options['index']['count'] === 0){
                $dir_ramdisk_count = $object->config('ramdisk.url') .
                    $object->config(Config::POSIX_ID) .
                    $object->config('ds') .
                    'Node' .
                    $object->config('ds') .
                    'Count' .
                    $object->config('ds')
                ;
                $count = File::read($dir_ramdisk_count . sha1($data_url) . $object->config('extension.txt'));
                if($count){
                    $options['index']['count'] = $count;
                }
            }
            $count = 0;
            $list = [];
            if(
                $options['limit'] === 1 &&
                $options['page'] === 1
            ){
                $options['parallel'] = false;
                $record = $this->index_list_record($class, $role, $options);
                if($record){
//                    $record = $this->index_record_expose($class, $role, $record, $options);
                    $list[] = new Storage($record);
                    $count++;
                }
            } else {
                $local_options = $options;
                $local_options['limit'] = 1;
                $local_options['page'] = 1;
                d($local_options);
                $record = $this->index_list_record($class, $role, $local_options);
                $found = [];
                while($record !== false){
                    if(is_array($record)){
                        //parallel left + right search
//                        $limit = $options['limit'] * $options['thread'] * $options['page'];
                        foreach($record as $rec){
                            //index_record_expose is handled in the separate thread
                            if(
                                $rec &&
                                !in_array(
                                    $rec->get('uuid'),
                                    $found,
                                    true
                                )
                            ){
                                $list[] = $rec;
                                $found[] = $rec->get('uuid');
                                $count++;
                            }
                        }
                        //one record to much, the binarysearch start
                        if($options['parallel'] === true){
                            d(count($list));
                            d($count);
                            $partition = Core::array_partition($list, $options['thread'], $count-1);
                            ddd($partition);
                        } else {
                            d($list);
                            $count = 0;
                            $list = Limit::list($list)->with([
                                'limit' => $options['limit'],
                                'page' => $options['page']
                            ], [], $count);
                        }
                        $record = false;
                    } else {
//                        $record = $this->index_record_expose($class, $role, $record, $local_options);
                        if(
                            !in_array(
                                $record->get('uuid'),
                                $found,
                                true
                            )
                        ){
                            $list[] = $record;
                            $found[] = $record->get('uuid');
                            $count++;
                        }
//                    d($count);
//                    d($options['page']);
//                    d($options['limit']);
                        if(
                            $options['limit'] !== '*' &&
                            $count === (($options['page'] * $options['limit']))
                        ){
//                        d($options['limit']);
//                        d($options['page']);
//                        d($list);
//                        d($found);
//                        d($count);
                            break;
                        }
                        $options_where = $this->index_record_next($found, $options);
                        $local_options['where'] = $options_where;
                        $local_options['limit'] = $options['limit'];
                        $local_options['page'] = $options['page'];
                        $local_options['debug'] = true;
                        $record = $this->index_list_record($class, $role, $local_options);
                    }
                }
                $object->config('delete', 'node.record.leftsearch');
                $object->config('delete', 'node.record.rightsearch');
            }
            $list = $this->index_list_expose($class, $role, $list, $options);
            $index = 0;
            foreach($list as $nr => $record){
                $record->{'#index'} = $index;
                $index++;
            }
            //add sort
            d('from index:' . $name);
            $result = [];
            $result['page'] = $options['page'];
            $result['limit'] = $options['limit'];
            $result['count'] = $count;
            $result['max'] = $options['index']['count'];
            if($options['limit'] !== '*'){
                $result['range'] = [
                    ($options['page'] * $options['limit'] - $options['limit']),
                    ($options['page'] * $options['limit'])
                ];
            }
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
            if($start){
                $result['#duration'] = (object) [
                    'boot' => ($start - $object->config('time.start')) * 1000,
                    'total' => (microtime(true) - $object->config('time.start')) * 1000,
                    'nodelist' => (microtime(true) - $start) * 1000
                ];
                $result['#duration']->item_per_second = ($count / $result['#duration']->total) * 1000;
                $result['#duration']->item_per_second_nodelist = ($count / $result['#duration']->nodelist) * 1000;
            }
            return $result;
        }
        $ramdisk_dir = false;
        $ramdisk_dir_node = false;
        $ramdisk_dir_list = false;
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

            $ramdisk_url_nodelist = [];
            if($options['parallel'] === true){
                for($i=0; $i<$options['thread']; $i++){
                    $ramdisk_url_nodelist[] = $ramdisk_dir_list .
                        $name .
                        '.' .
                        $key .
                        '.' .
                        $i .
                        $object->config('extension.json');
                }
                if(File::exist($ramdisk_url_nodelist[0])){
                    $pipes = [];
                    $children = [];

// Create pipes and fork processes
                    for ($i = 0; $i < $options['thread']; $i++) {
                        // Create a pipe
                        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                        if ($sockets === false) {
                            die("Unable to create socket pair for child $i");
                        }

                        $pid = pcntl_fork();
                        if ($pid == -1) {
                            die("Could not fork for child $i");
                        } elseif ($pid) {
                            // Parent process
                            // Close the child's socket
                            fclose($sockets[0]);

                            // Store the parent socket and child PID
                            $pipes[$i] = $sockets[1];
                            $children[$i] = $pid;
                        } else {
                            // Child process
                            // Close the parent's socket
                            fclose($sockets[1]);

                            $data = File::read($ramdisk_url_nodelist[$i]);

                            // Prepare data to send o the parent
                            /*
                            $data = [
                                'message' => "Hello from child $i",
                                'timestamp' => time(),
                                'pid' => posix_getpid()
                            ];
                            */
                            // Serialize the data
//                            $serializedData = serialize($data);

                            // Send serialized data to the parent
                            fwrite($sockets[0], $data);
                            fclose($sockets[0]);

                            exit(0);
                        }
                    }

// Parent process: read data from each child
                    $list = [];
                    $response = false;
                    foreach ($pipes as $i => $pipe) {
                        // Read serialized data from the pipe
                        $data = stream_get_contents($pipe);
                        fclose($pipe);
                        $data = (array) Core::object($data, Core::OBJECT_OBJECT);
                        if(array_key_exists('response', $data)){
                            $response = (array) $data['response'];
                            if(array_key_exists('list', $response)) {
                                foreach($response['list'] as $item){
                                    $list[] = $item;
                                }
                            }
                        }
                    }
// Wait for all children to exit
                    foreach ($children as $child) {
                        pcntl_waitpid($child, $status);
                    }
                    if($response){
                        $response['list'] = $list;
                        if ($start) {
                            $response['#duration'] = (object) [
                                'boot' => ($start - $object->config('time.start')) * 1000,
                                'total' => (microtime(true) - $object->config('time.start')) * 1000,
                                'nodelist' => (microtime(true) - $start) * 1000
                            ];
                            if (array_key_exists('count', $response)) {
                                $response['#duration']->item_per_second = ($response['count'] / $response['#duration']->total) * 1000;
                                $response['#duration']->item_per_second_nodelist = ($response['count'] / $response['#duration']->nodelist) * 1000;
                            } else {
                                $response['#duration']->item_per_second_with_limit = true;
                                $response['#duration']->item_per_second = ( (int) $options['limit'] / $response['#duration']->total) * 1000;
                                $response['#duration']->item_per_second_nodelist = ( (int) $options['limit'] / $response['#duration']->nodelist) * 1000;
                            }
                        }
                        return $response;
                    }


                    //fix transaction
                    $is_cache_miss = [];
                    $ramdisk = [];



                    /*
                    $closures = [];

                    foreach($ramdisk_url_nodelist as $i => $ramdisk_url_nodelist_item) {
                        $closures[] = function () use (
                            $object,
                            $options,
                            $mtime,
                            $ramdisk_url_nodelist_item
                        ) {
                            $data = $object->data_read($ramdisk_url_nodelist_item);
                            if ($data) {
                                $is_cache_miss = false;
                                if ($mtime === $data->get('mtime')) {
                                    $relations = $data->get('relation');
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
                                if($is_cache_miss){
                                    return false;
                                }
                                $shm = SharedMemory::open(ftok($ramdisk_url_nodelist_item, 'a'), 'n');
                                if($shm !== false){
                                    SharedMemory::delete($shm);
                                }
                                $size = SharedMemory::write($shm, Core::object($data->data(), Core::OBJECT_JSON_LINE));
                                return [
                                    'url' => $ramdisk_url_nodelist_item,
                                    'size' => $size
                                ];
                            } else {
                                return false;
                            }
                        };
                    }
                    $list_parallel = Parallel::new()->execute($closures);
                    $is_ok = true;
                    $list = [];
                    foreach($list_parallel as $i => $item){
                        if(!$item){
                            $is_ok = false;
                            break;
                        }
                        try {
                            ddd($item);
                            $shm = SharedMemory::open(ftok($item['url'], 'a'), 'a');
                            $data = SharedMemory::read($shm, 0, $item['size']);
                            ddd($data);
                            $response = (array) $item->response;
                            if(array_key_exists('list', $response)) {
                                foreach($response['list'] as $item){
                                    $list[] = $item;
                                }
                            }
                        }
                        catch(ErrorException $exception){
                            ddd($list_parallel[$i]);
                        }
                    }
                    if($is_ok && $response){
                        $response['list'] = $list;
                        if ($start) {
                            $response['#duration'] = (object)[
                                'boot' => ($start - $object->config('time.start')) * 1000,
                                'total' => (microtime(true) - $object->config('time.start')) * 1000,
                                'nodelist' => (microtime(true) - $start) * 1000
                            ];
                            if (array_key_exists('count', $response)) {
                                $response['#duration']->item_per_second = ($response['count'] / $response['#duration']->total) * 1000;
                                $response['#duration']->item_per_second_nodelist = ($response['count'] / $response['#duration']->nodelist) * 1000;
                            }
                        }
                        return $response;
                    }
                    */
                }
            }
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
                        $response = (array) $ramdisk->get('response');
                        if ($response) {
                            if($start){
                                $response['#duration'] = (object) [
                                    'boot' => ($start - $object->config('time.start')) * 1000,
                                    'total' => (microtime(true) - $object->config('time.start')) * 1000,
                                    'nodelist' => (microtime(true) - $start) * 1000
                                ];
                                if(array_key_exists('count', $response)){
                                    $response['#duration']->item_per_second = ($response['count'] / $response['#duration']->total) * 1000;
                                    $response['#duration']->item_per_second_nodelist = ($response['count'] / $response['#duration']->nodelist) * 1000;    
                                }
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
//                d($options);
                if($options['index'] === 'create'){
                    $data = $object->data_read($data_url, sha1($data_url), [
                        'index' => 'create',
                        'class' => $name
                    ]);
                } else {
                    $data = $object->data_read($data_url, sha1($data_url));
                }

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
        d('no-index:' . $name);
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
                }
                elseif (!empty($options['where'])) {
                    $is_where = true;
                }
                $limit = $options['limit'] ?? 4096;
                $options_limit = $limit;
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
                    for ($i = 0; $i < $options['thread']; $i++) {
                        // Create a pipe
                        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                        if ($sockets === false) {
                            die("Unable to create socket pair for child $i");
                        }
                        $chunk = $chunks[$i];
                        $pid = pcntl_fork();
                        if ($pid == -1) {
                            die("Could not fork for child $i");
                        } elseif ($pid) {
                            // Parent process
                            // Close the child's socket
                            fclose($sockets[0]);

                            // Store the parent socket and child PID
                            $pipes[$i] = $sockets[1];
                            $children[$i] = $pid;
                        } else {
                            // Child process
                            // Close the parent's socket
                            fclose($sockets[1]);
                            $result = [];
                            if($is_filter){
                                foreach($chunk as $nr => $record){
                                    $record = $this->filter($record, $options['filter'], $options);
                                    if (!$record) {
                                        $result[$nr] = 0;
                                        continue;
                                    }
                                    $result[$nr] = 1;
                                    if(
                                        $limit === 1 &&
                                        $options['page'] === 1
                                    ){
                                        break;
                                    }
                                }
                            }
                            elseif ($is_where) {
                                foreach($chunk as $nr => $record){
                                    $record = $this->where($record, $options['where'], $options);
                                    if (!$record) {
                                        $result[$nr] = 0;
                                        continue;
                                    }
                                    $result[$nr] = 1;
                                    if(
                                        $limit === 1 &&
                                        $options['page'] === 1
                                    ){
                                        break;
                                    }
                                }
                            } else {
                                foreach($chunk as $nr => $record){
                                    if (!$record) {
                                        $result[$nr] = 0;
                                        continue;
                                    }
                                    $result[$nr] = 1;
                                    if(
                                        $limit === 1 &&
                                        $options['page'] === 1
                                    ){
                                        break;
                                    }
                                }
                            }
                            // Send serialized data to the parent
                            fwrite($sockets[0], Core::object($result, Core::OBJECT_JSON_LINE));
                            fclose($sockets[0]);
                            exit(0);
                        }
                    }

// Parent process: read data from each child
                    $result = [];
                    foreach ($pipes as $i => $pipe) {
                        // Read serialized data from the pipe
                        $data = stream_get_contents($pipe);
                        fclose($pipe);
                        $array = Core::object($data, Core::OBJECT_ARRAY);
                        $chunk = $chunks[$i];
                        if(is_array($array)){
                            foreach($chunk as $nr => $record){
                                if(!array_key_exists($nr, $array)){
                                    d($data);
                                    ddd($array);
                                }
                                if($array[$nr] === 1){
                                    if(
                                        $options['parse'] === true &&
                                        $parse !== false
                                    ){
                                        $record = $parse->compile($record, $object->data(), $parse->storage());
                                    }
                                    if ($has_relation) {
                                        $record = $this->relation($record, $object_data, $role, $options);
                                        //collect relation mtime
                                    }
                                    $result[] = new Storage($record);
                                }
                            }
                        } else {
                            ddd($data);
                        }
                    }
// Wait for all children to exit
                    foreach ($children as $child) {
                        pcntl_waitpid($child, $status);
                    }
                    $list = $result;
                    if (!$expose) {
                        $expose = $this->expose_get(
                            $object,
                            $name,
                            $name . '.' . $options['function'] . '.output'
                        );
                    }
                    $list = $this->expose_list(
                        $list,
                        $expose,
                        $name,
                        $options['function'],
                        $role
                    );
                    foreach($list as $i => $record){
                        $list[$i] = $record->data();
                    }
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
                            /*
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
                            */
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
                                $node = new Storage($record);
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
                            if($limit === 1 && $options['page'] === 1){
                                break;
                            }
                            /*
                            if(
                                $limit !== '*' &&
                                $count === ($options['page'] * $limit)
                            ){
                                break;
                            }
                            */
                        }
                    }
                    $list = $list_filtered;
                    if(
                        $limit === 1 &&
                        $options['page'] === 1
                    ){
                        $list_sort = $list;
                    }
                    elseif(
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
                    if($options['limit'] !== '*'){
                        $list_sort = Limit::list($list_sort)->with([
                            'page' => $options['page'],
                            'limit' => $options['limit']
                        ]);
                    }

                    if(array_key_exists('view', $options)){
//                        d($list_sort);
                    }
                    $result = [];
                    $result['page'] = $options['page'] ?? 1;
                    $result['limit'] = $options['limit'] ?? $limit;
                    $result['count'] = count($list_sort);
                    $result['max'] = $max;
                    $result['list'] = $this->nodelist_output_filter($object, $list_sort, $options);
                    $result['sort'] = $options['sort'] ?? [];
                    $result['filter'] = $options['filter'] ?? [];
                    $result['where'] = $options['where'] ?? [];
                    $result['relation'] = $options['relation'] ?? true;
                    $result['parse'] = $options['parse'] ?? false;
                    $result['pre-compile'] = $options['pre-compile'] ?? false;
                    $result['ramdisk'] = $options['ramdisk'] ?? false;
                    $result['mtime'] = $mtime;
                    $result['transaction'] = $options['transaction'] ?? false;
                    if(
                        array_key_exists('ramdisk', $options) &&
                        $options['ramdisk'] === true &&
                        $ramdisk_url_node !== false
                    ){
                        if(
                            $options['parallel'] === true
                        ){
                            $result_ramdisk = $result;
                            $result_ramdisk['list'] = Core::array_partition($result['list'], $options['thread']);
                            $relation_mtime = $this->relation_mtime($object_data);
                            foreach($ramdisk_url_nodelist as $i => $ramdisk_url_nodelist_item){
                                $ramdisk_data = $result_ramdisk;
                                $ramdisk_data['list'] = $result_ramdisk['list'][$i];
                                $ramdisk = new Storage();
                                $ramdisk->set('mtime', $mtime);
                                $ramdisk->set('response', $ramdisk_data);
                                $ramdisk->set('relation', $relation_mtime);
                                $ramdisk->write($ramdisk_url_nodelist_item);
                                if($object->config('posix.id') !== 0){
                                    File::permission($object, [
                                        'ramdisk_url_nodelist_item' => $ramdisk_url_nodelist_item,
                                    ]);
                                }
                            }
                            if($object->config('posix.id') !== 0){
                                File::permission($object, [
                                    'ramdisk_dir' => $ramdisk_dir,
                                    'ramdisk_dir_node' => $ramdisk_dir_node,
                                    'ramdisk_dir_list' => $ramdisk_dir_list,
                                ]);
                            }
                        } else {
                            $relation_mtime = $this->relation_mtime($object_data);
                            $ramdisk = new Storage();
                            $ramdisk->set('mtime', $mtime);
                            $ramdisk->set('response', $result);
                            $ramdisk->set('relation', $relation_mtime);
                            $ramdisk->write($ramdisk_url_node);
                            if($object->config('posix.id') !== 0){
                                File::permission($object, [
                                    'ramdisk_dir' => $ramdisk_dir,
                                    'ramdisk_dir_node' => $ramdisk_dir_node,
                                    'ramdisk_dir_list' => $ramdisk_dir_list,
                                    'ramdisk_url_node' => $ramdisk_url_node,
                                ]);
                            }
                        }
                    }
                    if($start){
                        $result['#duration'] = (object) [
                            'boot' => ($start - $object->config('time.start')) * 1000,
                            'total' => (microtime(true) - $object->config('time.start')) * 1000,
                            'nodelist' => (microtime(true) - $start) * 1000
                        ];
                        $result['#duration']->item_per_second = ($list_count / $result['#duration']->total) * 1000;
                        $result['#duration']->item_per_second_nodelist = ($list_count / $result['#duration']->nodelist) * 1000;
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
                $result['list'] = $this->nodelist_output_filter($object, $list, $options);
                $result['sort'] = $options['sort'] ?? [];
                $result['filter'] = $options['filter'] ?? [];
                $result['where'] = $options['where'] ?? [];
                $result['relation'] = $options['relation'] ?? true;
                $result['parse'] = $options['parse'] ?? false;
                $result['pre-compile'] = $options['pre-compile'] ?? false;
                $result['ramdisk'] = $options['ramdisk'] ?? false;
                $result['mtime'] = $mtime;
                $result['transaction'] = $options['transaction'] ?? false;
                if(
                    array_key_exists('ramdisk', $options) &&
                    $options['ramdisk'] === true &&
                    $ramdisk_url_node !== false &&
                    $ramdisk_dir !== false &&
                    $ramdisk_dir_node !== false
                ){
                    if(
                        $options['parallel'] === true
                    ){
                        $result_ramdisk = $result;
                        $result_ramdisk['list'] = Core::array_partition($result['list'], $options['thread']);
                        $relation_mtime = $this->relation_mtime($object_data);
                        foreach($ramdisk_url_nodelist as $i => $ramdisk_url_nodelist_item){
                            $ramdisk_data = $result_ramdisk;
                            $ramdisk_data['list'] = $result_ramdisk['list'][$i];
                            $ramdisk = new Storage();
                            $ramdisk->set('mtime', $mtime);
                            $ramdisk->set('response', $ramdisk_data);
                            $ramdisk->set('relation', $relation_mtime);
                            $ramdisk->write($ramdisk_url_nodelist_item);
                            if($object->config('posix.id') !== 0){
                                File::permission($object, [
                                    'ramdisk_url_nodelist_item' => $ramdisk_url_nodelist_item,
                                ]);
                            }
                        }
                        if($object->config('posix.id') !== 0){
                            File::permission($object, [
                                'ramdisk_dir' => $ramdisk_dir,
                                'ramdisk_dir_node' => $ramdisk_dir_node,
                                'ramdisk_dir_list' => $ramdisk_dir_list,
                            ]);
                        }
                    } else {
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

                }
                if($start){
                    $result['#duration'] = (object) [
                        'boot' => ($start - $object->config('time.start')) * 1000,
                        'total' => (microtime(true) - $object->config('time.start')) * 1000,
                        'nodelist' => (microtime(true) - $start) * 1000
                    ];
                    $result['#duration']->item_per_second = ($list_count / $result['#duration']->total) * 1000;
                    $result['#duration']->item_per_second_nodelist = ($list_count / $result['#duration']->nodelist) * 1000;
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
        if($start){
            $result['#duration'] = (object) [
                'boot' => ($start - $object->config('time.start')) * 1000,
                'total' => (microtime(true) - $object->config('time.start')) * 1000,
                'nodelist' => (microtime(true) - $start) * 1000
            ];
            $result['#duration']->item_per_second = 0;
            $result['#duration']->item_per_second_nodelist = 0;
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    private function list_where($options=[]): bool | array
    {
        if(!array_key_exists('where', $options)){
            return false;
        }
        if (is_string($options['where'])) {
            $options['where'] = $this->where_convert($options['where']);
        }
        if (is_array($options['where'])) {
            foreach ($options['where'] as $key => $where) {
                if (is_string($where)) {
                    $split = mb_str_split($where);
                    $is_quote = false;
                    $is_double_quote = false;
                    $attribute = '';
                    $operator = '';
                    $value = '';
                    $is_attribute = false;
                    $is_operator = false;
                    $is_value = false;
                    $is_array = false;
                    $previous = false;
                    foreach ($split as $nr => $char) {
                        if(
                            $char === '[' &&
                            $is_array === false &&
                            $previous !== '\\'
                        ) {
                            $is_array = true;
                        }
                        elseif(
                            $char === ']' &&
                            $is_array === true &&
                            $previous !== '\\'
                        ) {
                            $is_array = false;
                        }
                        if ($char === '\'' && $previous !== '\\') {
                            if ($is_quote === false) {
                                $is_quote = true;
                            } else {
                                $is_quote = false;
                            }
                            if (
                                $is_attribute &&
                                $is_operator &&
                                $is_value === false &&
                                $is_array
                            ) {
                                $value .= $char;
                            }
                            continue;
                        }
                        elseif ($char === '"' && $previous !== '\\') {
                            if ($is_double_quote === false) {
                                $is_double_quote = true;
                            } else {
                                $is_double_quote = false;
                            }
                            if (
                                $is_attribute &&
                                $is_operator &&
                                $is_value === false &&
                                $is_array
                            ) {
                                $value .= $char;
                            }
                            continue;
                        }
                        if (
                            $char === ' ' &&
                            $is_quote === false &&
                            $is_double_quote === false &&
                            $is_attribute === false
                        ) {
                            $is_attribute = $attribute;
                            continue;
                        } elseif ($char === ' ' &&
                            $is_quote === false &&
                            $is_double_quote === false &&
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
                        $previous = $char;
                    }
                    if ($attribute && $operator && $value) {
                        if(
                            substr($value, 0,1) === '[' &&
                            substr($value, -1) === ']'
                        ){
                            $possible_array = str_replace([
                                '[\'',
                                '\']',
                                '\',\'',
                                '\', \'',
                                '\',  \'',
                                '\',   \''
                            ],[
                                '["',
                                '"]',
                                '","',
                                '","',
                                '","',
                                '","'
                            ], $value);
                            $array = Core::object($possible_array, Core::OBJECT_ARRAY);
                            if(is_array($array)){
                                $value = $array;
                            }
                        }
                        $options['where'][$key] = [
                            'attribute' => $attribute,
                            'operator' => $operator,
                            'value' => $value
                        ];
                    }

                }
            }
        }
        return $options['where'];
    }

    private function nodelist_output_filter(App $object, $list, $options=[]): mixed
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