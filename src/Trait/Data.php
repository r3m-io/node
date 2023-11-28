<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\App;
use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Module\Validate;

trait Data {

    use Expose;
    use Filter;
    use Tree;
    use Validate;
    use Where;
    use Data\Count;
    use Data\Create;
    use Data\Delete;
    use Data\NodeList;
    use Data\Read;
    use Data\Record;


}