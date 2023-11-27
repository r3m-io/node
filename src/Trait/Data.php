<?php
namespace R3m\Io\Node\Trait;

use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;

use R3m\Io\Node\Service\Security;

use Exception;

trait Data {

    use Expose;
    use Filter;
    use Tree;
    use Where;
    use Data\NodeList;
}