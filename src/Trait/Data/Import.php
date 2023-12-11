<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;

use R3m\Io\Module\Cli;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Sort;

use Exception;

use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Node\Service\Security;

Trait Import {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function import($class, $role, $options=[]): array
    {
        $name = Controller::name($class);
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('url', $options)){
            return [];
        }
        if(!File::exist($options['url'])){
            return [];
        }
        set_time_limit(0);
        $start = microtime(true);
        $options['function'] = __FUNCTION__;
        $options['relation'] = false;
        if(!Security::is_granted(
            $name,
            $role,
            $options
        )){
            return [];
        }
        $object = $this->object();
        $app_options = App::options($object);
        if(property_exists($app_options, 'force')){
            $options['force'] = $app_options->force;
        }
        $data = $object->data_read($options['url']);

        /**
         * route imports
         */

        if($data){
            $list = $data->data($name);
            $priority = $this->record(
                $name,
                $role,
                [
                    'sort' =>
                        [
                            'priority' => Sort::DESC
                        ]
                ]
            );
            if(
                array_key_exists('node', $priority) &&
                property_exists($priority['node'], 'priority')
            ) {
                $priority = $priority['node']->priority + 1;
            } else {
                $priority = 2001;
            }
            foreach($list as $record){
                if(property_exists($record, 'resource')){
                    continue;
                }
                $node = new Storage();
                $node->data($record);
                $node->delete('uuid');
                $node->set('priority', $priority);
                $options_create = [];
                $response = $this->create($class, $role, $node, $options_create);
                $priority++;
                d($response);
            }
        }
        ddd('end');
        return [];
    }
}
