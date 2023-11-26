<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;

trait Node {

    use Filter;
    use Tree;
    use Where;

    /**
     * @throws Exception
     */
    public function list($class, $role, $options=[]){
        $mtime = false;
        $name = Controller::name($class);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('relation', $options)){
            $options['relation'] = true;
        }
        if(!array_key_exists('parse', $options)){
            $options['parse'] = false;
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
            $result['mtime'] = $mtime;
            return $result;
        }
        $object = $this->object();
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
            $result['sort'] = $options['sort'];
            if(!empty($options['filter'])) {
                $result['filter'] = $options['filter'];
            }
            if(!empty($options['where'])) {
                $result['where'] = $options['where'];
            }
            $result['relation'] = $options['relation'];
            $result['parse'] = $options['parse'];
            $result['mtime'] = $mtime;
            return $result;
        }
        $data = $object->data_read($data_url);
        $mtime = File::mtime($data_url);
        $object_url = $object->config('project.dir.node') .
            'Object' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        $object_data = $object->data_read($object_url);
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
                if(!empty($relation) && is_array($relation)){
                    ddd('has relation');
                }
                if(
                    !empty(
                        $options['filter']) &&
                        is_array($options['filter'])
                ){
                    foreach($list as $nr => $record){
                        $record = $this->filter($record, $options['filter'], $options);
                        if(!$record){
                            unset($list[$nr]);
                        }
                    }
                    $list = array_values($list);
                }
                elseif(
                    !empty($options['where']) &&
                    (
                        is_string($options['where']) ||
                        is_array($options['where'])
                    )
                ){
                    $options['where'] = $this->where_convert($options['where']);
                    foreach($list as $nr => $record){
                        $record = $this->where($record, $options['where'], $options);
                        if(!$record){
                            unset($list[$nr]);
                        }
                    }
                    $list = array_values($list);
                }
                if(!empty($options['sort']) && is_array($options['sort'])){
                    $list = Sort::list($list)->with(
                        $options['sort'],
                        [
                            'key_reset' => true,
                        ]
                    );
                }
                if(!empty($options['limit']) && $options['limit'] === '*'){
                    $list_count = count($list);
                    $result = [];
                    $result['page'] = 1;
                    $result['limit'] = $list_count;
                    $result['count'] = $list_count;
                    $result['max'] = $max;
                    $result['list'] = $list;
                    $result['sort'] = $options['sort'] ?? [];
                    $result['filter'] = $options['filter'] ?? [];
                    $result['where'] = $options['where'] ?? [];
                    $result['relation'] = $options['relation'] ?? true;
                    $result['parse'] = $options['parse'] ?? false;
                    $result['mtime'] = $mtime;
                    return $result;
                }
                $page = $options['page'] ?? 1;
                $limit = $options['limit'] ?? 4096;
                foreach($list as $index => $record){
                    if(
                        $index < ($page - 1) * $limit ||
                        $index >= $page * $limit
                    ){
                        unset($list[$index]);
                    }
                }
                $list = array_values($list);
                $list_count = count($list);
                $result = [];
                $result['page'] = $page;
                $result['limit'] = $limit;
                $result['count'] = $list_count;
                $result['max'] = $max;
                $result['list'] = $list;
                $result['sort'] = $options['sort'] ?? [];
                $result['filter'] = $options['filter'] ?? [];
                $result['where'] = $options['where'] ?? [];
                $result['relation'] = $options['relation'] ?? true;
                $result['parse'] = $options['parse'] ?? false;
                $result['mtime'] = $mtime;
                return $result;
            }
        }
    }
}