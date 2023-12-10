<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;

use R3m\Io\Node\Service\Security;

use Exception;

Trait Rename {

    /**
     * @throws Exception
     */
    public function rename($from, $to, $role, $options=[]): false|array|object
    {
        $object = $this->object();
        $from = Controller::name($from);
        $to = Controller::name($to);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!Security::is_granted(
            $from,
            $role,
            $options
        )){
            return false;
        }
        $dir_object = $object->config('project.dir.node') .
            'Object' .
            $object->config('ds')
        ;
        $url_data_from = $object->config('project.dir.node') . 'Data' . $object->config('ds') . $from . $object->config('extension.json');
        $url_data_to = $object->config('project.dir.node') . 'Data' . $object->config('ds') . $to . $object->config('extension.json');
        $url_expose_from = $object->config('project.dir.node') . 'Expose' . $object->config('ds') . $from . $object->config('extension.json');
        $url_expose_to = $object->config('project.dir.node') . 'Expose' . $object->config('ds') . $to . $object->config('extension.json');
        $url_object_from = $object->config('project.dir.node') . 'Object' . $object->config('ds') . $from . $object->config('extension.json');
        $url_object_to = $object->config('project.dir.node') . 'Object' . $object->config('ds') . $to . $object->config('extension.json');
        $url_validate_from = $object->config('project.dir.node') . 'Validate' . $object->config('ds') . $from . $object->config('extension.json');
        $url_validate_to = $object->config('project.dir.node') . 'Validate' . $object->config('ds') . $to . $object->config('extension.json');
        // 4 options: -force (overwrite file) or -skip (skip) or -merge (merge patch) or -merge-overwite (merge overwrite)
        $force = false;
        if(array_key_exists('force', $options)){
            $force = $options['force'];
        }
        $merge = false;
        if(array_key_exists('merge', $options)){
            $merge = $options['merge'];
        }
        $skip = false;
        if(array_key_exists('skip', $options)){
            $skip = $options['skip'];
        }
        $merge_overwrite = false;
        if(array_key_exists('merge-overwrite', $options)){
            $merge_overwrite = $options['merge-overwrite'];
        }
        if(
            File::exist($url_data_to) &&
            $force === false &&
            $merge === false &&
            $skip === false &&
            $merge_overwrite === false
        ){
            throw new Exception('To (Data) ('. $to .') already exists');
        }
        if(
            File::exist($url_expose_to) &&
            $force === false &&
            $merge === false &&
            $skip === false &&
            $merge_overwrite === false
        ){
            throw new Exception('To (Expose) ('. $to .') already exists');
        }
        if(
            File::exist($url_object_to) &&
            $force === false &&
            $merge === false &&
            $skip === false &&
            $merge_overwrite === false
        ){
            throw new Exception('To (Object) ('. $to .') already exists');
        }
        if(
            File::exist($url_validate_to) &&
            $force === false &&
            $merge === false &&
            $skip === false &&
            $merge_overwrite === false
        ){
            throw new Exception('To (Validate) ('. $to .') already exists');
        }
        $list = [];
        if(File::exist($url_data_from)){
            $merger = new Storage();
            $read = $object->data_read($url_data_from);
            if(
                (
                    $merge ||
                    $skip ||
                    $merge_overwrite
                ) &&
                File::exist($url_data_to)
            ){
                $write_to = $object->data_read($url_data_to);
                if($write_to){
                    foreach($write_to->data($to) as $record){
                        if(
                            is_array($record) &&
                            array_key_exists('uuid', $record)
                        ){
                            $merger->set($record['uuid'], $record);
                        }
                        elseif(
                            is_object($record) &&
                            property_exists($record, 'uuid')
                        ){
                            $merger->set($record->uuid, $record);
                        }
                    }
                }
                $write = new Storage();
            }
            elseif($force &&
                File::exist($url_data_to)
            ){
                File::delete($url_data_to);
                $write = new Storage();
            } else {
                $write = new Storage();
            }
            if($read){
                foreach($read->data($from) as $record){
                    if(
                        is_array($record) &&
                        array_key_exists('uuid', $record)
                    ){
                        $record['#class'] = $to;
                        if(
                            $skip &&
                            $merger->has($record['uuid'])
                        ){
                            //merge skip
                            continue;
                        }
                        elseif(
                            $merge_overwrite &&
                            $merger->has($record['uuid'])
                        ){
                            //merge overwrite
                            //use of $record
                        }
                        elseif(
                            $merge &&
                            $merger->has($record['uuid'])
                        ){
                            //merge patch
                            $record = array_merge($merger->get($record['uuid']), $record);
                        }
                        $merger->delete($record['uuid']);
                    }
                    elseif(
                        is_object($record) &&
                        property_exists($record, 'uuid')
                    ) {
                        $record->{'#class'} = $to;
                        if(
                            $skip &&
                            $merger->has($record->uuid)
                        ){
                            //merge skip
                            continue;
                        }
                        elseif(
                            $merge_overwrite &&
                            $merger->has($record->uuid)
                        ){
                            //merge overwrite
                            //use of $record
                        }
                        elseif(
                            $merge &&
                            $merger->has($record->uuid)
                        ){
                            //merge patch
                            $record = Core::object_merge($merger->get($record->uuid), $record);
                        }
                        $merger->delete($record->uuid);
                    }
                    $list[] = $record;
                }
            }
            foreach($merger->data() as $record){
                $list[] = $record;
            }
            $write->set($to, $list);
            $url_data_write = $write->write($url_data_to);
        } else {
            //can still process expose, object & validate & relations
        }
        $read = $object->data_read($url_expose_from);
        $write = new Storage();
        if($read){
            $write->data($to, $read->data($from));
        }
        if(Core::object_is_empty($write->data())){
            throw new Exception('Empty expose write, fix from: ' . $url_expose_from);
        }
        if(File::exist($url_expose_to)){
            File::delete($url_expose_to);
        }
        $url_expose_write =$write->write($url_expose_to);

        $read = $object->data_read($url_object_from);
        $write = new Storage();
        if($read){
            $write->data($read->data());
        }
        if(Core::object_is_empty($write->data())){
            throw new Exception('Empty object write, fix from: ' . $url_object_from);
        }
        if(File::exist($url_object_to)){
            File::delete($url_object_to);
        }
        $url_object_write = $write->write($url_object_to);
        $read = $object->data_read($url_validate_from);
        $write = new Storage();
        if($read){
            $write->data($to, $read->data($from));
        }
        if(Core::object_is_empty($write->data($to))){
            throw new Exception('Empty validate write, fix from: ' . $url_object_from);
        }
        if(File::exist($url_validate_to)){
            File::delete($url_validate_to);
        }
        $url_validate_write = $write->write($url_validate_to);
        //node.relations
        if(
            $url_expose_write &&
            $url_object_write &&
            $url_validate_write
        ){
            $dir = new Dir();
            $read = $dir->read($dir_object);
            if(
                $read &&
                is_array($read)
            ){
                foreach($read as $file){
                    if($file->type === Dir::TYPE){
                        continue;
                    }
                    if($file->name === 'System.Config.Log.json'){
                        $read_data = $object->data_read($file->url);
                        if($read_data){
                            $relations = $read_data->get('relation');
                            foreach($relations as $nr => $relation){
                                if(
                                    property_exists($relation, 'class') &&
                                    $relation->class === $from
                                ){
                                    $relation->class = $to;
                                    $relations[$nr] = $relation;
                                }
                            }
                            $read_data->set('relation', $relations);
                            $read_data->write($file->url);
                        }
                    }
                }
            }
        }


        //node.validate
        d($url_expose_write);
        d($url_data_write);
        d($url_object_write);
        d($from);
        d($to);
        ddd($options);
        return false;
    }

    /**
     * @throws Exception
     */
    /*
    public function rename(): void
    {
        $object = $this->object();
        $options = App::options($object);
        if(property_exists($options, 'from')){
            $options->from = Controller::name(trim($options->from));
        } else {
            throw new Exception('Option "from" is missing');
        }
        if(property_exists($options, 'to')){
            $options->to = Controller::name(trim($options->to));
        } else {
            throw new Exception('Option "to" is missing');
        }
        $role = $this->role_system();
        if(!property_exists($options, 'function')){
            $options->function = 'rename';
        }
        if(!Security::is_granted(
            $options->from,
            $role,
            Core::object($options, Core::OBJECT_ARRAY)
        )){
            return;
        }

        $from_dir_binary_tree = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'BinaryTree' .
            $object->config('ds') .
            $options->from .
            $object->config('ds')
        ;
        $to_dir_binary_tree = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'BinaryTree' .
            $object->config('ds') .
            $options->to .
            $object->config('ds')
        ;
        $from_url_expose = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Expose' .
            $object->config('ds') .
            $options->from .
            $object->config('extension.json')
        ;
        $to_url_expose = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Expose' .
            $object->config('ds') .
            $options->to .
            $object->config('extension.json')
        ;
        if($from_dir_binary_tree === $to_dir_binary_tree){
            throw new Exception('From and to are the same');
        }
        if(!Dir::is($from_dir_binary_tree)){
            throw new Exception('From does not exist');
        }
        if(Dir::is($to_dir_binary_tree)){
            throw new Exception('To already exists');
        }
        $dir_node = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds');
        $dir_binary_tree = $dir_node .
            'BinaryTree' .
            $object->config('ds');
        ;
        $dir_binary_tree_class = $dir_binary_tree .
            $options->from .
            $object->config('ds')
        ;
        $dir_binary_tree_sort = $dir_binary_tree_class .
            'Asc' .
            $object->config('ds')
        ;
        $url_binary_tree_sort = $dir_binary_tree_sort .
            'Uuid' .
            $object->config('extension.btree');
        if(!File::exist($url_binary_tree_sort)){
            //logger error $url_binary_tree_sort not found
        }
        $mtime = File::mtime($url_binary_tree_sort);
        $data_uuid = File::read($url_binary_tree_sort, File::ARRAY);
        if(is_array($data_uuid)){
            foreach($data_uuid as $uuid){
                $uuid = rtrim($uuid, PHP_EOL);
                $url_node = $object->config('project.dir.data') .
                    'Node' .
                    $object->config('ds') .
                    'Storage' .
                    $object->config('ds') .
                    substr($uuid, 0, 2) .
                    $object->config('ds') .
                    $uuid .
                    $object->config('extension.json')
                ;
                $data_node = $object->data_read($url_node);
                if($data_node){
                    $data_node->set('#class', $options->to);
                    $data_node->write($url_node);
                    if($object->config(Config::POSIX_ID) === 0){
                        $command = 'chown www-data:www-data ' . $url_node;
                        exec($command);
                    }
                    if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                        $command = 'chmod 666 ' . $url_node;
                        exec($command);
                    }
                }
            }
        }
        $from_dir_filter = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Filter' .
            $object->config('ds') .
            $options->from .
            $object->config('ds')
        ;
        $to_dir_filter = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Filter' .
            $object->config('ds') .
            $options->to .
            $object->config('ds')
        ;
        $from_url_meta = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Meta' .
            $object->config('ds') .
            $options->from .
            $object->config('extension.json')
        ;
        $to_url_meta = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Meta' .
            $object->config('ds') .
            $options->to .
            $object->config('extension.json')
        ;
        $from_url_object = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Object' .
            $object->config('ds') .
            $options->from .
            $object->config('extension.json')
        ;
        $to_url_object = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Object' .
            $object->config('ds') .
            $options->to .
            $object->config('extension.json')
        ;
        $from_url_validate = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Validate' .
            $object->config('ds') .
            $options->from .
            $object->config('extension.json')
        ;
        $to_url_validate = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Validate' .
            $object->config('ds') .
            $options->to .
            $object->config('extension.json')
        ;
        $from_dir_where = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Where' .
            $object->config('ds') .
            $options->from .
            $object->config('ds')
        ;
        $to_dir_where = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Where' .
            $object->config('ds') .
            $options->to .
            $object->config('ds')
        ;
        File::move($from_dir_binary_tree, $to_dir_binary_tree, true);
        if($object->config(Config::POSIX_ID) === 0){
            $command = 'chown www-data:www-data ' . $to_dir_binary_tree;
            exec($command);
        }
        if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
            $command = 'chmod 777 ' . $to_dir_binary_tree;
            exec($command);
        }
        if(File::exist($from_url_expose)){
            $data = $object->data_read($from_url_expose);
            if($data){
                $expose = $data->get($options->from);
                if($expose){
                    $expose_data = new Storage();
                    $expose_data->set($options->to, $expose);
                    $expose_data->write($to_url_expose);
                    File::delete($from_url_expose);
                    if($object->config(Config::POSIX_ID) === 0){
                        $command = 'chown www-data:www-data ' . $to_url_expose;
                        exec($command);
                    }
                    if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                        $command = 'chmod 666 ' . $to_url_expose;
                        exec($command);
                    }
                }
            }
        }
        if(File::exist($from_dir_filter)){
            File::move($from_dir_filter, $to_dir_filter, true);
        }

        if($object->config(Config::POSIX_ID) === 0){
            if(File::exist($to_dir_filter)){
                $command = 'chown www-data:www-data ' . $to_dir_filter;
                exec($command);
            }
        }
        if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
            if(File::exist($to_dir_filter)){
                $command = 'chmod 777 ' . $to_dir_filter;
                exec($command);
            }
        }
        if(File::exist($from_url_meta)){
            $read = File::read($from_url_meta);
            $search = $object->config('project.dir.data') .
                'Node' .
                $object->config('ds') .
                'BinaryTree' .
                $object->config('ds') .
                $options->from .
                $object->config('ds')
            ;
            $replace = $object->config('project.dir.data') .
                'Node' .
                $object->config('ds') .
                'BinaryTree' .
                $object->config('ds') .
                $options->to .
                $object->config('ds')
            ;
            $search = str_replace('/', '\/', $search);
            $replace = str_replace('/', '\/', $replace);
            $read = str_replace($search, $replace, $read);
            $search = 'class": "' . $options->from . '"';
            $replace = 'class": "' . $options->to . '"';
            $read = str_replace($search, $replace, $read);
            $search = 'class":"' . $options->from . '"';
            $replace = 'class": "' . $options->to . '"';
            $read = str_replace($search, $replace, $read);
            $data = new Storage();
            $meta = new Storage();
            $data->data(Core::object($read, Core::OBJECT_OBJECT));
            $attributes = [
                'Sort',
                'Filter',
                'Where',
                'Count'
            ];
            foreach($attributes as $attribute){
                if($data->has($attribute . '.' . $options->from)){
                    $get = $data->get($attribute . '.' . $options->from);
                    if($get){
                        $meta->set($attribute . '.' . $options->to, $get);
                    }
                }
            }
            $meta->write($to_url_meta);
            if($object->config(Config::POSIX_ID) === 0){
                $command = 'chown www-data:www-data ' . $to_url_meta;
                exec($command);
            }
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $command = 'chmod 666 ' . $to_url_meta;
                exec($command);
            }
            File::delete($from_url_meta);
        }
        if(File::exist($from_url_object)) {
            $read = File::read($from_url_object);
            $search = 'class": "' . $options->from . '"';
            $replace = 'class": "' . $options->to . '"';
            $read = str_replace($search, $replace, $read);
            $search = 'class":"' . $options->from . '"';
            $replace = 'class": "' . $options->to . '"';
            $read = str_replace($search, $replace, $read);
            File::write($to_url_object, $read);
            File::delete($from_url_object);
            if($object->config(Config::POSIX_ID) === 0){
                $command = 'chown www-data:www-data ' . $to_url_object;
                exec($command);
            }
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $command = 'chmod 666 ' . $to_url_object;
                exec($command);
            }
        }
        if(File::exist($from_url_validate)){
            $read = File::read($from_url_validate);
            $search = 'Node' .
                $object->config('ds') .
                'BinaryTree' .
                $object->config('ds') .
                $options->from .
                $object->config('ds')
            ;
            $replace = 'Node' .
                $object->config('ds') .
                'BinaryTree' .
                $object->config('ds') .
                $options->to .
                $object->config('ds')
            ;
            $search = str_replace('/', '\/', $search);
            $replace = str_replace('/', '\/', $replace);
            $read = str_replace($search, $replace, $read);
            $search = 'class": "' . $options->from . '"';
            $replace = 'class": "' . $options->to . '"';
            $read = str_replace($search, $replace, $read);
            $search = 'class":"' . $options->from . '"';
            $replace = 'class": "' . $options->to . '"';
            $read = str_replace($search, $replace, $read);
            $data = new Storage();
            $storage = new Storage();
            $data->data(Core::object($read, Core::OBJECT_OBJECT));
            if($data->has($options->from)) {
                $storage->set($options->to, $data->get($options->from));
            }
            $storage->write($to_url_validate);
            File::delete($from_url_validate);
            if($object->config(Config::POSIX_ID) === 0){
                $command = 'chown www-data:www-data ' . $to_url_validate;
                exec($command);
            }
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $command = 'chmod 666 ' . $to_url_validate;
                exec($command);
            }
        }
        if(File::exist($from_dir_where)){
            File::move($from_dir_where, $to_dir_where, true);
            if($object->config(Config::POSIX_ID) === 0){
                $command = 'chown www-data:www-data ' . $to_dir_where;
                exec($command);
            }
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $command = 'chmod 777 ' . $to_dir_where;
                exec($command);
            }
        }
        */
}