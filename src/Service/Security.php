<?php
namespace R3m\Io\Node\Service;

use R3m\Io\App;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Data;

use Exception;

class Security extends Main
{

    /**
     * @throws Exception
     */
    public static function is_granted($class, $role, $options): bool
    {
        if(!array_key_exists('function', $options)){
            throw new Exception('Function is missing in options');
        }
        $name = Controller::name($class);
        $role = new Data($role);
        $is_permission = false;
        $is_permission_relation = false;
        $is_permission_parse = false;
        $permissions = [];
        $permissions[] = $name . '.' . $options['function'];
        if(
            array_key_exists('relation', $options) &&
            $options['relation'] === true
        ){
            $permissions[] = $name . '.' . $options['function'] . '.' . 'relation';
        }
        if(
            array_key_exists('parse', $options) &&
            $options['parse'] === true
        ){
            $permissions[] = $name . '.' . $options['function'] . '.' . 'parse';
        }
        $role_permissions = $role->get('permission');
        if(is_array($role_permissions)){
            foreach($role->get('permission') as $permission){
                $permission = new Data($permission);
                if($permission->get('name') === $name . '.' . $options['function']){
                    $is_permission = true;
                }
                if(
                    array_key_exists('relation', $options) &&
                    $options['relation'] === true
                ){
                    if($permission->get('name') === $name . '.' . $options['function'] . '.' . 'relation'){
                        $is_permission_relation = true;
                    }
                } else {
                    $is_permission_relation = true;
                }
                if(
                    array_key_exists('parse', $options) &&
                    $options['parse'] === true
                ) {
                    if($permission->get('name') === $name . '.' . $options['function'] . '.' . 'parse'){
                        $is_permission_parse = true;
                    }
                } else {
                    $is_permission_parse = true;
                }
                if(
                    $is_permission === true &&
                    $is_permission_parse === true &&
                    $is_permission_relation === true
                ){
                    return true;
                }
            }
        } else {
            $debug = debug_backtrace(true);
            d($debug[0]['file'] . ' (' . $debug[0]['line'] . ')' . ' ' . $debug[0]['function'] . ' ' . $debug[0]['class']);
            d($debug[1]['file'] . ' (' . $debug[1]['line'] . ')' . ' ' . $debug[1]['function'] . ' ' . $debug[1]['class']);
            d($debug[2]['file'] . ' (' . $debug[2]['line'] . ')' . ' ' . $debug[2]['function'] . ' ' . $debug[2]['class']);
            d($debug[3]['file'] . ' (' . $debug[3]['line'] . ')' . ' ' . $debug[3]['function'] . ' ' . $debug[3]['class']);
            d($name);
            d($role);
            d($permissions);
            ddd($role_permissions);
        }

        throw new Exception('Security: permission denied... (' . implode(', ', $permissions) . ')');
    }
}