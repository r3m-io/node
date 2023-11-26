<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\File;

use R3m\Io\Node\Service\Security;

use Exception;

trait Node {

    /**
     * @throws Exception
     */
    public function list($class, $role, $options=[]){
        $mtime = time();
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
        $object_url = $object->config('project.dir.node') .
            'Object' .
            $object->config('ds') .
            $name .
            $object->config('extension.json')
        ;
        if(!File::exist($object_url)){
            throw new Exception('Object ' . $name . ' not found');
        }
        $object_data = $object->data_read($object_url);
        ddd($object_data);
    }


}