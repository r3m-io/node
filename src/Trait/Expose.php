<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Config;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;
use R3m\Io\Module\Parse;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Exception\AuthorizationException;

trait Expose {

    /**
     * @throws ObjectException
     * @throws Exception
     * @throws AuthorizationException
     */
    public function expose($node, $expose=[], $class='', $function='', $internalRole=false, $parentRole=false): Storage
    {
        $object = $this->object();
        d($expose);
        if (!is_array($expose)) {
            return new Storage();
        }
        $roles = [];
        if ($internalRole) {
            $roles[] = $internalRole; //same as parent
        } else {
//            $roles = Permission::getAccessControl($object, $class, $function);
            try {
                /*
                $user = User::getByAuthorization($object);
                if ($user) {
                    $roles = $user->getRolesByRank('asc');
                }
                */
            } catch (Exception $exception) {

            }
        }
        if (empty($roles)) {
            throw new Exception('Roles failed...');
        }
        $record = [];
        $is_expose = false;
        foreach ($roles as $role) {
            if (
                property_exists($role, 'uuid') &&
                property_exists($role, 'name') &&
                $role->name === 'ROLE_SYSTEM' &&
                !property_exists($role, 'permission')
            ) {
                $permission = [];
                $permission['uuid'] = Core::uuid();
                $permission['name'] = str_replace('.', ':', Controller::name($class)) . '.' . str_replace('_', '.', $function);
                $permission['property'] = [];
                $permission['role'] = $role->uuid;
                $role->permission = [];
                $role->permission[] = (object) $permission;
            }
            if (
                property_exists($role, 'name') &&
                property_exists($role, 'permission') &&
                is_array($role->permission)
            ) {
                foreach ($role->permission as $permission) {
                    if (is_array($permission)) {
                        ddd($permission);
                    }
                    foreach ($expose as $action) {
                        if (
                            (
                                property_exists($permission, 'name') &&
                                $permission->name === str_replace('.', ':', Controller::name($class)) . ':' . str_replace('_', '.', $function) &&
                                property_exists($action, 'role') &&
                                $action->role === $role->name
                            )
                            ||
                            (
                                in_array(
                                    $function,
                                    ['child', 'children'],
                                    true
                                ) &&
                                property_exists($action, 'role') &&
                                $action->role === $parentRole
                            )
                        ) {
                            $is_expose = true;
                            if (
                                property_exists($action, 'property') &&
                                is_array($action->property)
                            ) {

                                foreach ($action->property as $property) {
                                    $is_optional = false;
                                    if(substr($property, 0, 1) === '?'){
                                        $is_optional = true;
                                        $property = substr($property, 1);
                                    }
                                    $assertion = $property;
                                    $explode = explode(':', $property, 2);
                                    $compare = null;
                                    if (array_key_exists(1, $explode)) {
                                        $record_property = $node->get($explode[0]);
                                        $compare = $explode[1];
                                        $attribute = $explode[0];
                                        if ($compare) {
                                            $parse = new Parse($object, $object->data());
                                            $compare = $parse->compile($compare, $object->data());
                                            d($node);
                                            ddd($compare);
                                            if ($record_property !== $compare) {
                                                throw new Exception('Assertion failed: ' . $assertion . ' values [' . $record_property . ', ' . $compare . ']');
                                            }
                                        }
                                    }
                                    if (
                                        property_exists($action, 'object') &&
                                        property_exists($action->object, $property) &&
                                        property_exists($action->object->$property, 'expose')
                                    ) {
                                        if (
                                            property_exists($action->object->$property, 'multiple') &&
                                            $action->object->$property->multiple === true &&
                                            $node->has($property)
                                        ) {
                                            $array = $node->get($property);

                                            if(is_array($array) || is_object($array)){
                                                $record[$property] = [];
                                                foreach ($array as $child) {
                                                    $child = new Storage($child);
                                                    $child_expose =[];
                                                    if(
                                                        property_exists($action->object->$property, 'object')
                                                    ){
                                                        $child_expose[] = (object) [
                                                            'property' => $action->object->$property->expose,
                                                            'object' => $action->object->$property->object,
                                                            'role' => $action->role,
                                                        ];
                                                    }  else {
                                                        $child_expose[] = (object) [
                                                            'property' => $action->object->$property->expose,
                                                            'role' => $action->role,
                                                        ];
                                                    }
                                                    $child = $this->expose(
                                                        $child,
                                                        $child_expose,
                                                        $property,
                                                        'child',
                                                        $role,
                                                        $action->role
                                                    );
                                                    $record[$property][] = $child->data();
                                                }
                                            } else {
                                                //leave intact for read without parse
                                                $record[$property] = $array;
                                            }
                                        } elseif (
                                            $node->has($property)
                                        ) {
                                            $child = $node->get($property);
                                            if (!empty($child)) {
                                                $record[$property] = null;
                                                $child = new Storage($child);
                                                $child_expose =[];
                                                if(
                                                    property_exists($action->object->$property, 'objects')
                                                ){
                                                    $child_expose[] = (object) [
                                                        'property' => $action->object->$property->expose,
                                                        'object' => $action->object->$property->objects,
                                                        'role' => $action->role,
                                                    ];
                                                }  else {
                                                    $child_expose[] = (object) [
                                                        'property' => $action->object->$property->expose,
                                                        'role' => $action->role,
                                                    ];
                                                }
                                                $child = $this->expose(
                                                    $child,
                                                    $child_expose,
                                                    $property,
                                                    'child',
                                                    $role,
                                                    $action->role
                                                );
                                                $record[$property] = $child->data();
                                            }
                                            if (empty($record[$property])) {
                                                $record[$property] = null;
                                            }
                                        }
                                    } else {
                                        if ($node->has($property)) {
                                            $record[$property] = $node->get($property);
                                        }
                                    }
                                }
                                if(!empty($record)){
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }
        if($is_expose === false){
            throw new Exception('No permission found for ' . str_replace('.', ':', Controller::name($class)) . ':' . str_replace('_', '.', $function));
        }
        return new Storage((object) $record);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function expose_get(App $object, $name='', $attribute=''): mixed
    {
        $dir_expose = $object->config('project.dir.node') .
            'Expose' .
            $object->config('ds')
        ;
        $url = $dir_expose .
            $name .
            $object->config('extension.json')
        ;
        if(!File::exist($url)){
            throw new Exception('Expose: url (' . $url . ') not found for class: ' . $name);
        }
        $data = $object->data_read($url);
        $get = false;
        if($data){
            $get = $data->get($attribute);
        }
        if(empty($get)){
            throw new Exception('Expose: cannot find attribute (' . $attribute .') in class: ' . $name);
        }
        return $get;
    }

    /**
     * @throws ObjectException
     */
    private function expose_object_create_cli($depth=0): array
    {

        $result = [];
        $attribute = Cli::read('input', 'Object name (depth (' . $depth . ')): ');
        $attributes = [];
        while(!empty($attribute)){
            $multiple = Cli::read('input', 'Multiple (boolean): ');
            if(
                in_array(
                    $multiple,
                    [
                        'true',
                        1
                    ],
                    true
                )
            ) {
                $multiple = true;
            }
            if(
                in_array(
                    $multiple ,
                    [
                        'false',
                        0
                    ],
                    true
                 )
            ) {
                $multiple = false;
            }
            $expose = Cli::read('input', 'Expose (property): ');
            while(!empty($expose)){
                $attributes[] = $expose;
                $expose = Cli::read('input', 'Expose (property): ');
            }
            $object = [];
            $object['multiple'] = $multiple;
            $object['expose'] = $attributes;
            $object['object'] = $this->expose_object_create_cli(++$depth);
            if(empty($object['object'])){
                unset($object['object']);
            }
            $result[$attribute] = $object;
            $attribute = Cli::read('input', 'Object name (depth (' . --$depth . ')): ');
        }
        return $result;
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function expose_create_cli(): void
    {
        $object = $this->object();
        if($object->config(Config::POSIX_ID) !== 0){
            return;
        }
        $class = Cli::read('input', 'Class: ');
        $url = $object->config('project.dir.data') .
            'Node' .
            $object->config('ds') .
            'Expose' .
            $object->config('ds') .
            $class .
            $object->config('extension.json')
        ;
        $expose = $object->data_read($url);
        $action = Cli::read('input', 'Action: ');
        $role = Cli::read('input', 'Role: ');
        $property = Cli::read('input', 'Property: ');
        $properties = [];
        while(!empty($property)){
            $properties[] = $property;
            $property = Cli::read('input', 'Property: ');
        }
        $objects = $this->expose_object_create_cli();
        if(!$expose){
            $expose = new Storage();
        }
        $list = (array) $expose->get($class . '.' . $action . '.expose');
        if(empty($list)){
            $list = [];
        } else {
            foreach ($list as $nr => $record){
                if(
                    is_array($record) &&
                    array_key_exists('role', $record)
                ){
                    if($record['role'] === $role){
                        unset($list[$nr]);
                    }
                }
                elseif(
                    is_object($record) &&
                    property_exists($record, 'role')
                ){
                    if($record->role === $role){
                        unset($list[$nr]);
                    }
                }
            }
        }
        $record = [];
        $record['role'] = $role;
        $record['property'] = $properties;
        $record['object'] = $objects;
        $list[] = $record;
        $result = [];
        foreach ($list as $record){
            $result[] = $record;
        }
        $expose->set($class . '.' . $action . '.expose', $result);
        $expose->write($url);
        $command = 'chown www-data:www-data ' . $url;
        exec($command);
        if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
            $command = 'chmod 666 ' . $url;
            exec($command);
        }
    }
}