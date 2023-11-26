<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Module\Controller;

use R3m\Io\Node\Service\Security;

use Exception;

trait Node {

    /**
     * @throws Exception
     */
    public function list($class, $role, $options=[]){
        $name = Controller::name($class);

        if(!array_key_exists('relation', $options)){
            $options['relation'] = true;
        }
        if(!array_key_exists('parse', $options)){
            $options['parse'] = false;
        }
        if(!Security::is_granted(
            $class,
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
        d($name);
        d($role);
        d($options);


    }


}