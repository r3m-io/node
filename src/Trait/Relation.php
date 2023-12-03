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
use R3m\Io\Module\Route;

trait Relation {

    private function relation($record, $data, $role, $options=[]){
        $object = $this->object();
        if(!$role){
            return $record;
        }
        if($data){
            $node = new Storage($record);
            $relations = $data->data('relation');
            if(!$relations){
                return $record;
            }
            if(
                array_key_exists('relation', $options) &&
                is_bool($options['relation']) &&
                $options['relation'] === false
            ){
                return $record;
            }
            if(!is_array($relations)){
                return $record;
            }
            foreach($relations as $relation){
                if(
                    property_exists($relation, 'type') &&
                    property_exists($relation, 'class') &&
                    property_exists($relation, 'attribute')
                ){
                    $is_allowed = false;
                    $output_filter = false;
                    $options_relation = $options['relation'] ?? [];
                    if(is_bool($options_relation) && $options_relation === true){
                        $is_allowed = true;
                    }
                    elseif(is_bool($options_relation) && $options_relation === false){
                        $is_allowed = false;
                    }
                    elseif(is_array($options_relation)){
                        foreach($options_relation as $option){
                            if(strtolower($option) === strtolower($relation->class)){
                                $is_allowed = true;
                                break;
                            }
                        }
                    }
                    switch(strtolower($relation->type)){
                        case 'one-one':
                            if(
                                $is_allowed &&
                                $node->has($relation->attribute)
                            ){
                                $uuid = $node->get($relation->attribute);
                                if(!is_string($uuid)){
                                    break;
                                }
                                if($uuid === '*'){
                                    $one_one = [
                                        'sort' => [
                                            'uuid' => 'ASC'
                                        ],
                                    ];
                                    $response = $this->record(
                                        $relation->class,
                                        $this->role_system(),
                                        $one_one
                                    );
                                    if(
                                        !empty($response) &&
                                        array_key_exists('node', $response)
                                    ){
                                        d($relation);
                                        ddd($response['node']);
                                        $node->set($relation->attribute, $response['node']);
                                    } else {
                                        $node->set($relation->attribute, false);
                                    }
                                } else {
                                    $relation_url = $object->config('project.dir.data') .
                                        'Node' .
                                        $object->config('ds') .
                                        'Storage' .
                                        $object->config('ds') .
                                        substr($uuid, 0, 2) .
                                        $object->config('ds') .
                                        $uuid .
                                        $object->config('extension.json')
                                    ;
                                    $relation_data = $object->data_read($relation_url, sha1($relation_url));
                                    if($relation_data) {
                                        $relation_object_url = $object->config('project.dir.data') .
                                            'Node' .
                                            $object->config('ds') .
                                            'Object' .
                                            $object->config('ds') .
                                            $relation_data->get('#class') .
                                            $object->config('extension.json');
                                        $relation_object_data = $object->data_read($relation_object_url, sha1($relation_object_url));
                                        if (
                                            $relation_object_data &&
                                            $relation_object_data->has('relation')
                                        ) {
                                            $relation_object_relation = $relation_object_data->get('relation');
                                            if (is_array($relation_object_relation)) {
                                                foreach ($relation_object_relation as $relation_nr => $relation_relation) {
                                                    if (
                                                        property_exists($relation_relation, 'type') &&
                                                        property_exists($relation_relation, 'class') &&
                                                        property_exists($record, '#class') &&
                                                        $relation_relation->type === 'many-one' &&
                                                        $relation_relation->class === $record->{'#class'}
                                                    ) {
                                                        //don't need cross-reference, parent is this.
                                                        continue;
                                                    }
                                                    if (
                                                        property_exists($relation_relation, 'type') &&
                                                        property_exists($relation_relation, 'class') &&
                                                        property_exists($record, '#class') &&
                                                        $relation_relation->type === 'one-one' &&
                                                        $relation_relation->class === $record->{'#class'}
                                                    ) {
                                                        //don't need cross-reference, parent is this.
                                                        continue;
                                                    }
                                                    if (
                                                        property_exists($relation_relation, 'attribute')
                                                    ) {
                                                        $relation_data_data = $relation_data->get($relation_relation->attribute);
                                                        $relation_data_data = $this->relation_inner($relation_relation, $relation_data_data, $options);
                                                        $relation_data->set($relation_relation->attribute, $relation_data_data);
                                                    }
                                                }
                                            }
                                            if ($relation_data) {
                                                $node->set($relation->attribute, $relation_data->data());
                                            }
                                        } else {
                                            if ($relation_data) {
                                                $node->set($relation->attribute, $relation_data->data());
                                            }
                                        }
                                    }
                                }
                            }
                            $record = $node->data();
                            break;
                        case 'one-many':
                            if(
                                $is_allowed &&
                                $node->has($relation->attribute)
                            ){
                                $one_many = $node->get($relation->attribute);
                                ddd($one_many);
                                if(is_object($one_many)){
                                    if(
                                        property_exists($relation, 'class') &&
                                        property_exists($relation, 'attribute') &&
                                        property_exists($relation, 'type')
                                    ){
                                        if(!property_exists($one_many, 'limit')){
                                            throw new Exception('Relation: ' . $relation->attribute . ' has no limit');
                                        }
                                        if(!property_exists($one_many, 'page')){
                                            $one_many->page = 1;
                                        }
                                        if($one_many->limit === '*'){
                                            $one_many->page = 1;
                                        }
                                        if(!property_exists($one_many, 'sort')){
                                            if(property_exists($relation, 'sort')){
                                                $one_many->sort = $relation->sort;
                                            } else {
                                                $one_many->sort = [
                                                    'uuid' => 'ASC'
                                                ];
                                            }
                                        }
                                        if(
                                            property_exists($one_many, 'output') &&
                                            !empty($one_many->output) &&
                                            is_object($one_many->output) &&
                                            property_exists($one_many->output, 'filter') &&
                                            !empty($one_many->output->filter) &&
                                            is_array($one_many->output->filter)
                                        ){
                                            $output_filter = $one_many->output->filter;
                                        }
                                        d($one_many);
                                        ddd($output_filter);
                                        if($one_many->limit === '*'){
                                            $list = $this->list_select_all($object, $relation, $one_many);
                                            $node->set($relation->attribute, $list);
                                        } else {
                                            $response = $this->list(
                                                $relation->class,
                                                $this->role_system(),
                                                $one_many
                                            );
                                            if(
                                                !empty($response) &&
                                                array_key_exists('list', $response)
                                            ){
                                                $node->set($relation->attribute, $response['list']);
                                            } else {
                                                $node->set($relation->attribute, []);
                                            }
                                            d($response);
                                        }
                                        $record = $node->data();
                                        break;
                                    }
                                }
                                elseif(
                                    is_string($one_many) &&
                                    $one_many === '*'
                                ){
                                    $one_many = (object) [
                                        'limit' => '*',
                                        'page' => 1,
                                    ];
                                    if(property_exists($relation, 'sort')){
                                        $one_many->sort = $relation->sort;
                                    } else {
                                        $one_many->sort = [
                                            'uuid' => 'ASC'
                                        ];
                                    }
                                    $list = $this->list_select_all($object, $relation, $one_many);
                                    $node->set($relation->attribute, $list);
                                    $record = $node->data();
                                    break;
                                }
                                elseif($one_many === '' || $one_many === false){
                                    if(
                                        property_exists($relation, 'limit') &&
                                        !empty($relation->limit) &&
                                        (
                                            $relation->limit === '*' ||
                                            (
                                                is_int($relation->limit) ||
                                                is_float($relation->limit)
                                            )
                                        )
                                    ){
                                        if(
                                            property_exists($relation, 'page') &&
                                            (
                                                is_int($relation->page) ||
                                                is_float($relation->page)
                                            )
                                        ){
                                            $page = $relation->page;
                                        } else {
                                            $page = 1;
                                        }
                                        $one_many = (object) [
                                            'limit' => $relation->limit,
                                            'page' => $page,
                                        ];
                                        if($one_many->limit === '*'){
                                            $one_many->page = 1;
                                        }
                                        if(
                                            property_exists($relation, 'sort') &&
                                            !empty($relation->sort)
                                        ){
                                            $one_many->sort = $relation->sort;
                                        } else {
                                            $one_many->sort = [
                                                'uuid' => 'ASC'
                                            ];
                                        }
                                        if(
                                            property_exists($relation, 'where') &&
                                            !empty($relation->where)
                                        ){
                                            $one_many->where = $relation->where;
                                        }
                                        if(
                                            property_exists($relation, 'filter') &&
                                            !empty($relation->filter) &&
                                            is_array($relation->filter)
                                        ){
                                            $one_many->filter = $relation->filter;
                                        }
                                        if($one_many->limit === '*'){
                                            $list = $this->list_select_all($object, $relation, $one_many);
                                            $node->set($relation->attribute, $list);
                                        } else {
                                            $response = $this->list(
                                                $relation->class,
                                                $this->role_system(),
                                                $one_many
                                            );
                                            if(
                                                !empty($response) &&
                                                array_key_exists('list', $response)
                                            ){
                                                $node->set($relation->attribute, $response['list']);
                                            } else {
                                                $node->set($relation->attribute, []);
                                            }
                                        }
                                        $record = $node->data();
                                        break;
                                    }
                                }
                                if(!is_array($one_many)){
                                    break;
                                }
                                foreach($one_many as $nr => $uuid){
                                    if(!is_string($uuid)){
                                        continue;
                                    }
                                    $relation_url = $object->config('project.dir.data') .
                                        'Node' .
                                        $object->config('ds') .
                                        'Storage' .
                                        $object->config('ds') .
                                        substr($uuid, 0, 2) .
                                        $object->config('ds') .
                                        $uuid .
                                        $object->config('extension.json')
                                    ;
                                    $relation_data = $object->data_read($relation_url, sha1($relation_url));
                                    if($relation_data){
                                        if(
                                            $relation_data->has('#class')
                                        ){
                                            $relation_object_url = $object->config('project.dir.data') .
                                                'Node' .
                                                $object->config('ds') .
                                                'Object' .
                                                $object->config('ds') .
                                                $relation_data->get('#class') .
                                                $object->config('extension.json')
                                            ;
                                            $relation_object_data = $object->data_read($relation_object_url, sha1($relation_object_url));
                                            if(
                                                $relation_object_data &&
                                                $relation_object_data->has('relation')
                                            ){
                                                $relation_object_relation = $relation_object_data->get('relation');
                                                if(is_array($relation_object_relation)){
                                                    foreach($relation_object_relation as $relation_nr => $relation_relation){
                                                        if(
                                                            property_exists($relation_relation, 'type') &&
                                                            property_exists($relation_relation, 'class') &&
                                                            property_exists($record, '#class') &&
                                                            $relation_relation->type === 'many-one' &&
                                                            $relation_relation->class === $record->{'#class'}
                                                        ){
                                                            //don't need cross-reference, parent is this.
                                                            continue;
                                                        }
                                                        if(
                                                            property_exists($relation_relation, 'type') &&
                                                            property_exists($relation_relation, 'class') &&
                                                            property_exists($record, '#class') &&
                                                            $relation_relation->type === 'one-one' &&
                                                            $relation_relation->class === $record->{'#class'}
                                                        ){
                                                            //don't need cross-reference, parent is this.
                                                            continue;
                                                        }
                                                        if(
                                                            property_exists($relation_relation, 'attribute')
                                                        ){
                                                            $relation_data_data = $relation_data->get($relation_relation->attribute);
                                                            $relation_data_data = $this->relation_inner($relation_relation, $relation_data_data, $options);
                                                            $relation_data->set($relation_relation->attribute, $relation_data_data);
                                                        }
                                                    }
                                                }
                                                if($relation_data){
                                                    $one_many[$nr] = $relation_data->data();
                                                }
                                            } else {
                                                if($relation_data){
                                                    $one_many[$nr] = $relation_data->data();
                                                }
                                            }
                                        }
                                    }
                                }
                                $node->set($relation->attribute, $one_many);
                            }
                            $record = $node->data();
                            break;
                        case 'many-one':
                            if(
                                $is_allowed &&
                                $node->has($relation->attribute)
                                //add is_uuid
                            ){
                                $uuid = $node->get($relation->attribute);
                                if(!is_string($uuid)){
                                    break;
                                }
                                $relation_url = $object->config('project.dir.data') .
                                    'Node' .
                                    $object->config('ds') .
                                    'Storage' .
                                    $object->config('ds') .
                                    substr($uuid, 0, 2) .
                                    $object->config('ds') .
                                    $uuid .
                                    $object->config('extension.json')
                                ;
                                $relation_data = $object->data_read($relation_url, sha1($relation_url));
                                if($relation_data){
                                    if(
                                        $relation_data->has('#class')
                                    ) {
                                        $relation_object_url = $object->config('project.dir.data') .
                                            'Node' .
                                            $object->config('ds') .
                                            'Object' .
                                            $object->config('ds') .
                                            $relation_data->get('#class') .
                                            $object->config('extension.json')
                                        ;
                                        $relation_object_data = $object->data_read($relation_object_url, sha1($relation_object_url));
                                        if($relation_object_data){
                                            foreach($relation_object_data->get('relation') as $relation_nr => $relation_relation){
                                                if(
                                                    property_exists($relation_relation, 'type') &&
                                                    property_exists($relation_relation, 'class') &&
                                                    property_exists($record, '#class') &&
                                                    $relation_relation->type === 'many-one' &&
                                                    $relation_relation->class === $record->{'#class'}
                                                ){
                                                    //don't need cross-reference, parent is this.
                                                    continue;
                                                }
                                                elseif(
                                                    property_exists($relation_relation, 'type') &&
                                                    property_exists($relation_relation, 'class') &&
                                                    property_exists($record, '#class') &&
                                                    $relation_relation->type === 'one-one' &&
                                                    $relation_relation->class === $record->{'#class'}
                                                ){
                                                    //don't need cross-reference, parent is this.
                                                    continue;
                                                }
                                                elseif(
                                                    property_exists($relation_relation, 'type') &&
                                                    property_exists($relation_relation, 'class') &&
                                                    property_exists($record, '#class') &&
                                                    $relation_relation->type === 'one-many' &&
                                                    $relation_relation->class === $record->{'#class'}
                                                ){
                                                    //don't need cross-reference, parent is this.
                                                    continue;
                                                }
                                                if(
                                                    property_exists($relation_relation, 'attribute')
                                                ){
                                                    $relation_data_data = $relation_data->get($relation_relation->attribute);
                                                    $relation_data_data = $this->relation_inner($relation_relation, $relation_data_data, $options);
                                                    $relation_data->set($relation_relation->attribute, $relation_data_data);
                                                }
                                            }
                                        }
                                        if($relation_data){
                                            $node->set($relation->attribute, $relation_data->data());
                                        }
                                    }
                                }
                            }
                            $record = $node->data();
                            break;
                    }
                    if(
                        empty($output_filter) &&
                        property_exists($relation, 'output') &&
                        !empty($relation->output) &&
                        is_object($relation->output) &&
                        property_exists($relation->output, 'filter') &&
                        !empty($relation->output->filter) &&
                        is_array($relation->output->filter)
                    ){
                        $output_filter = $relation->output->filter;
                    }
                    if($output_filter){
                        foreach($output_filter as $output_filter_nr => $output_filter_data){
                            $route = (object) [
                                'controller' => $output_filter_data
                            ];
                            $route = Route::controller($route);
                            if(
                                property_exists($route, 'controller') &&
                                property_exists($route, 'function') &&
                                property_exists($record, $relation->attribute)
                            ){
                                $record->{$relation->attribute} = $route->controller::{$route->function}($object, $record->{$relation->attribute});
                            }
                        }
                    }
                }
            }
        }
        return $record;
    }

    private function relation_inner($relation, $data=[], $options=[], &$counter=0): false|array|stdClass
    {
        $object = $this->object();
        $counter++;
        if($counter > 1024){
            $is_loaded = $object->data('R3m.Io.Node.BinaryTree.relation');
            d($is_loaded);
            d($relation);
            ddd($data);
        }
        if(!property_exists($relation, 'type')){
            return false;
        }
        $is_allowed = false;
        $options_relation = $options['relation'] ?? [];
        if(is_bool($options_relation) && $options_relation === true){
            $is_allowed = true;
        }
        elseif(is_bool($options_relation) && $options_relation === false){
            $is_allowed = false;
        }
        elseif(is_array($options_relation)){
            foreach($options_relation as $option){
                if(strtolower($option) === strtolower($relation->class)){
                    $is_allowed = true;
                    break;
                }
            }
        }
        switch($relation->type){
            case 'one-many':
                if(!is_array($data)){
                    return false;
                }
                foreach($data as $relation_data_nr => $relation_data_uuid){
                    if(
                        $is_allowed &&
                        is_string($relation_data_uuid) &&
                        Core::is_uuid($relation_data_uuid)
                    ){
                        $relation_data_url = $object->config('project.dir.data') .
                            'Node' .
                            $object->config('ds') .
                            'Storage' .
                            $object->config('ds') .
                            substr($relation_data_uuid, 0, 2) .
                            $object->config('ds') .
                            $relation_data_uuid .
                            $object->config('extension.json')
                        ;
                        $relation_data = $object->data_read($relation_data_url, sha1($relation_data_url));
                        if($relation_data){
//                            $record = $relation_data->data();

                            $relation_object_url = $object->config('project.dir.data') .
                                'Node' .
                                $object->config('ds') .
                                'Object' .
                                $object->config('ds') .
                                $relation->class .
                                $object->config('extension.json')
                            ;
                            $relation_object_data = $object->data_read($relation_object_url, sha1($relation_object_url));
                            $relation_object_relation = $relation_object_data->data('relation');

                            $is_loaded = $object->data('R3m.Io.Node.BinaryTree.relation');
                            if(empty($is_loaded)){
                                $is_loaded = [];
                            }
                            if($relation_data->has('#class')){
                                $is_loaded[] = $relation_data->get('#class');
                                $object->data('R3m.Io.Node.BinaryTree.relation', $is_loaded);
                            }
                            if(is_array($relation_object_relation)){
                                foreach($relation_object_relation as $relation_object_relation_nr => $relation_object_relation_data){
                                    if(
                                        property_exists($relation_object_relation_data, 'class') &&
                                        property_exists($relation_object_relation_data, 'attribute')
                                    ){
                                        if(
                                            in_array(
                                                $relation_object_relation_data->class,
                                                $is_loaded,
                                                true
                                            )
                                        ){
                                            //already loaded
                                            continue;
                                        }
                                    }
                                    $selected = $relation_data->get($relation_object_relation_data->attribute);
                                    $selected = $this->relation_inner($relation_object_relation_data, $selected, $options, $counter);
                                    $relation_data->set($relation_object_relation_data->attribute, $selected);
                                }
                            }
                            $data[$relation_data_nr] = $relation_data->data();
                        } else {
                            //old data, remove from list
                            unset($data[$relation_data_nr]);
                        }
                    }
                }
                break;
            case 'many-one':
                if(
                    $is_allowed &&
                    is_string($data) &&
                    Core::is_uuid($data)
                ){
                    $relation_data_url = $object->config('project.dir.data') .
                        'Node' .
                        $object->config('ds') .
                        'Storage' .
                        $object->config('ds') .
                        substr($data, 0, 2) .
                        $object->config('ds') .
                        $data .
                        $object->config('extension.json')
                    ;
                    $relation_data = $object->data_read($relation_data_url, sha1($relation_data_url));
                    if($relation_data) {
//                        $record = $relation_data->data();

                        $relation_object_url = $object->config('project.dir.data') .
                            'Node' .
                            $object->config('ds') .
                            'Object' .
                            $object->config('ds') .
                            $relation->class .
                            $object->config('extension.json')
                        ;
                        $relation_object_data = $object->data_read($relation_object_url, sha1($relation_object_url));
                        $relation_object_relation = $relation_object_data->data('relation');

                        $is_loaded = $object->data('R3m.Io.Node.BinaryTree.relation');
                        if(empty($is_loaded)){
                            $is_loaded = [];
                        }
                        if($relation_data->has('#class')){
                            $is_loaded[] = $relation_data->get('#class');
                            $object->data('R3m.Io.Node.BinaryTree.relation', $is_loaded);
                        }
                        if(is_array($relation_object_relation)){
                            foreach($relation_object_relation as $relation_object_relation_nr => $relation_object_relation_data){
                                if(
                                    property_exists($relation_object_relation_data, 'class') &&
                                    property_exists($relation_object_relation_data, 'attribute')
                                ){

                                    if(
                                        in_array(
                                            $relation_object_relation_data->class,
                                            $is_loaded,
                                            true
                                        )
                                    ){
                                        //already loaded
                                        continue;
                                    }
                                }
                                $selected = $relation_data->get($relation_object_relation_data->attribute);
                                $selected = $this->relation_inner($relation_object_relation_data, $selected, $options, $counter);
                                $relation_data->set($relation_object_relation_data->attribute, $selected);
                            }
                        }
                        $data = $relation_data->data();
                    }
                }
                break;
            case 'one-one':
                if(
                    $is_allowed &&
                    is_string($data) &&
                    Core::is_uuid($data)
                ){
                    $relation_data_url = $object->config('project.dir.data') .
                        'Node' .
                        $object->config('ds') .
                        'Storage' .
                        $object->config('ds') .
                        substr($data, 0, 2) .
                        $object->config('ds') .
                        $data .
                        $object->config('extension.json')
                    ;
                    $relation_data = $object->data_read($relation_data_url, sha1($relation_data_url));
                    if($relation_data) {
//                        $record = $relation_data->data();

                        $relation_object_url = $object->config('project.dir.data') .
                            'Node' .
                            $object->config('ds') .
                            'Object' .
                            $object->config('ds') .
                            $relation->class .
                            $object->config('extension.json')
                        ;
                        $relation_object_data = $object->data_read($relation_object_url, sha1($relation_object_url));
                        $relation_object_relation = $relation_object_data->data('relation');

                        $is_loaded = $object->data('R3m.Io.Node.BinaryTree.relation');
                        if(empty($is_loaded)){
                            $is_loaded = [];
                        }
                        if($relation_data->has('#class')){
                            $is_loaded[] = $relation_data->get('#class');
                            $object->data('R3m.Io.Node.BinaryTree.relation', $is_loaded);
                        }
                        if(is_array($relation_object_relation)){
                            foreach($relation_object_relation as $relation_object_relation_nr => $relation_object_relation_data){
                                if(
                                    property_exists($relation_object_relation_data, 'class') &&
                                    property_exists($relation_object_relation_data, 'attribute')
                                ){

                                    if(
                                        in_array(
                                            $relation_object_relation_data->class,
                                            $is_loaded,
                                            true
                                        )
                                    ){
                                        //already loaded
                                        continue;
                                    }
                                }
                                $selected = $relation_data->get($relation_object_relation_data->attribute);
                                $selected = $this->relation_inner($relation_object_relation_data, $selected, $options, $counter);
                                $relation_data->set($relation_object_relation_data->attribute, $selected);
                            }
                        }
                        $data = $relation_data->data();
                    }
                }
                break;
        }
        return $data;
    }
}