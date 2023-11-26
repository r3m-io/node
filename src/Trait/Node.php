<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Module\Controller;

trait Node {

    public function list($class, $role, $options=[]){
        $name = Controller::name($class);
        d($name);
        d($role);
        d($options);


    }


}