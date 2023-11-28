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
    private function filter($record=[], $filter=[], $options=[]){

        $list = [];
        $list[] = $record;
        $list = Module::list($list)->where($filter);
        if(!empty($list)){
            return $record;
        }
        return false;
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function filter_nodelist($class, $role, $options): array
    {
        $name =  Controller::name($class);
        $object = $this->object();
        $list = [];
        if(!array_key_exists('url', $options)){
            return $list;
        }
        if(!array_key_exists('key', $options)){
            return $list;
        }
        if(!array_key_exists('lines', $options)){
            return $list;
        }
        if(!array_key_exists('count', $options)){
            return $list;
        }
        if(!array_key_exists('function', $options)){
            return $list;
        }
        $list_data = File::read($options['url'], File::ARRAY);
        if($list_data){
            foreach($list_data as $index => $uuid){
                $uuid = rtrim($uuid, PHP_EOL);
                if(empty($uuid)){
                    continue;
                }
                $record_data = (object) [];
                $record_data->uuid = $uuid;
                $record_data->{'#read'} = [];
                $record_data->{'#read'}['url'] = $object->config('project.dir.data') .
                    'Node' .
                    $object->config('ds') .
                    'Storage' .
                    $object->config('ds') .
                    substr($record_data->uuid, 0, 2) .
                    $object->config('ds') .
                    $record_data->uuid .
                    $object->config('extension.json')
                ;
                $record_data->{'#read'}['lines'] = $options['lines'];
                $record_data->{'#read'}['count'] = $options['count'];
                $record_data->{'#read'}['index'] = $index;
                $record_data->{'#read'} = (object) $record_data->{'#read'};
                $read = $object->data_read($record_data->{'#read'}->url, sha1($record_data->{'#read'}->url));
                if($read){
                    $record_data = Core::object_merge($record_data, $read->data());
                }
                if(!property_exists($record_data, '#class')){
                    //need to trigger sync
                    continue;
                }
                $object_url = $object->config('project.dir.data') .
                    'Node' .
                    $object->config('ds') .
                    'Object' .
                    $object->config('ds') .
                    ucfirst($record_data->{'#class'}) .
                    $object->config('extension.json')
                ;
                $options_json = Core::object($options, Core::OBJECT_JSON);
                $object_data = $object->data_read($object_url, sha1($object_url . '.' . $options_json));
                $record_data = $this->binary_tree_relation($record_data, $object_data, $role, $options);
                $expose = $this->expose_get(
                    $object,
                    $record_data->{'#class'},
                    $record_data->{'#class'} . '.' . $options['function'] . '.expose'
                );
                $record = new Storage($record_data);
                if(
                    array_key_exists('parse', $options) &&
                    $options['parse'] === true
                ){
                    $record->set('R3m\Io', $object->data('R3m\Io'));
                    $parse = new Parse($object);
                    $record->set('#role', $role);
                    //add #role, #user to record ?
                    $record_data = $parse->compile($record_data, $record);
                    unset($record_data->{'#role'});
                }
                $record = $this->expose(
                    $record,
                    $expose,
                    $record_data->{'#class'},
                    $options['function'],   //maybe change this (because filter has different read attributes)
                    $role
                );
                $record_data = $record->data();
                $list[] = $record_data;
            }
        }
        return $list;
    }
}