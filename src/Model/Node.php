<?php
namespace R3m\Io\Node\Model;

use R3m\Io\App;

use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Template\Main;

use R3m\Io\Node\Trait\Data;

class Node extends Main {
    use Data;

    public function __construct(App $object){
        $this->object($object);
        $this->storage(new Storage());
    }
}