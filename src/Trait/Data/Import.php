<?php

namespace R3m\Io\Node\Trait\Data;

use R3m\Io\App;
use R3m\Io\Config;

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

trait Import {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function import($class, $role, $options=[]): false | array
    {
        /**
         * need virtual system which manage read operations and read them on the fly
         * with an input directory and an output directory
         * then you get 2 polling scripts
         * - one polls for the input directory for new files
         * - if a file is placed in the input directory wait for it in the output directory with a time-out
         * - create an index of all unique values
         *
         */
        $name = Controller::name($class);
        $object = $this->object();
        try {
            $options = Core::object($options, Core::OBJECT_ARRAY);
            if(!array_key_exists('url', $options)){
                return false;
            }
            if(!File::exist($options['url'])){
                return false;
            }
            if(!array_key_exists('uuid', $options)){
                $options['uuid'] = false;
            }
            if(!array_key_exists('chunk-size', $options)){
                $options['chunk-size'] = 1000;
            }
            $options['import'] = true;
            set_time_limit(0);
            $start = microtime(true);
            $options['function'] = __FUNCTION__;
            $options['relation'] = false;
            $skip = 0;
            $put = 0;
            $patch = 0;
            $create = 0;
            $put_many = [];
            $patch_many = [];
            $create_many = [];
            $error = [];
            if(!Security::is_granted(
                $name,
                $role,
                $options
            )){
                return false;
            }
            $dir_data = $object->config('project.dir.node') .
                'Data' .
                $object->config('ds')
            ;
            $url = $dir_data .
                $name .
                $object->config('extension.json')
            ;
            $this->startTransaction($name, $options);
            $data = $object->data_read($options['url']);
            if($data){
                $list = $data->data($name);
                if(!is_array($list)){
                    $list = [];
                }
                $url_object = $object->config('project.dir.node') .
                    'Object' .
                    $object->config('ds') .
                    $name .
                    $object->config('extension.json')
                ;
                $data_object = $object->data_read($url_object, sha1($url_object));
                $list_count = count($list);
                if($list_count > $options['chunk-size']){
                    $list = array_chunk($list, $options['chunk-size']);
                    d(array_keys($list));
                    foreach($list as $chunk){
                        $filter_value_1 = [];
                        $filter_value_2 = [];
                        $count = 0;
                        $count_record = 0;
                        $explode = [];
                        foreach($chunk as $chunk_nr => $record){
                            $node = new Storage();
                            $node->data($record);
                            $count_record++;
                            if($count_record > 750){
                                $node->data('#class', 'RaXon.Php.Word.Embedding');
                            } else {
                                $node->data('#class', 'test');
                            }
                            if (
                                $data_object &&
                                $data_object->has('is.unique')
                            ) {
                                $unique = (array)$data_object->get('is.unique');
                                $unique = array_shift($unique);
                                $explode = explode(',', $unique);
                                $count = 0;
                                foreach ($explode as $nr => $value) {
                                    $explode[$nr] = trim($value);
                                    $count++;
                                }
                                $explode[1] = '#class';
                                $count = 2;
                                switch ($count) {
                                    case 2:
                                        if (
                                            $node->has($explode[0]) &&
                                            $node->has($explode[1])
                                        ) {
                                            $match_1 = $node->get($explode[0]);
                                            $match_2 = $node->get($explode[1]);
                                            if(
                                                $match_1 !== null &&
                                                $match_1 !== '' &&
                                                $match_2 !== null &&
                                                $match_2 !== ''
                                            ){
                                                $filter_value_1[] = $match_1;
                                                $filter_value_2[] = $match_2;
                                            } else {
                                                throw new Exception('Unique value cannot be empty...');
                                            }
                                        } else {
                                            throw new Exception('Unique value cannot be empty...');
                                        }
                                        break;
                                    case 1:
                                        if ($node->has($explode[0])) {
                                            $match_1 = $node->get($explode[0]);
                                            if(
                                                $match_1 !== null &&
                                                $match_1 !== ''
                                            ){
                                                $filter_value_1[] = $match_1;
                                            } else {
                                                throw new Exception('Unique value cannot be empty...');
                                            }
                                        } else {
                                            throw new Exception('Unique value cannot be empty...');
                                        }
                                        break;
                                }
                            }
                        }
                        $select = null;
                        switch($count){
                            case 1 :
                                if(
                                    !empty($explode[0]) &&
                                    !empty($filter_value_1)
                                ){
                                    $select = $this->list(
                                        $name,
                                        $role,
                                        [
                                            'filter' => [
                                                $explode[0] => [
                                                    'value' => $filter_value_1,
                                                    'operator' => 'in'
                                                ]
                                            ],
                                            'transaction' => true,
                                            'limit' => $options['chunk-size']
                                        ]
                                    );
                                }
                                break;
                            case 2:
                                if(
                                    !empty($explode[0]) &&
                                    !empty($explode[1]) &&
                                    !empty($filter_value_1) &&
                                    !empty($filter_value_2)
                                ){
                                    // a where is to slow because it needs to use nested where
                                    if(
                                        !empty($explode[0]) &&
                                        !empty($filter_value_1)
                                    ){
                                        $select = $this->list(
                                            $name,
                                            $role,
                                            [
                                                'filter' => [
                                                    $explode[0] => [
                                                        'value' => $filter_value_1,
                                                        'operator' => 'in'
                                                    ]
                                                ],
                                                'transaction' => true,
                                                'limit' => $options['chunk-size'],
                                                'with_null' => true
                                            ]
                                        );
                                        if(
                                            $select &&
                                            array_key_exists('list', $select)
                                        ){
                                            foreach($filter_value_2 as $nr => $value){
                                                if(
                                                    array_key_exists($nr, $select['list']) &&
                                                    is_object($select['list'][$nr])
                                                ){
                                                    $node = new Storage();
                                                    $node->data($select['list'][$nr]);
                                                    if($node->get($explode[1]) !== $value) {
                                                        $select['list'][$nr] = null;
                                                        $select['count']--;
                                                    }
                                                } else {
                                                    $select['list'][$nr] = null;
                                                    $select['count']--;
                                                }
                                                /*
                                                $patch = new Storage();
                                                $patch->data($record);
                                                $patch->set('uuid', $select['list'][$nr]->uuid);
                                                //patch || put
                                                $put_many[] = $patch->data();
                                                */
                                            }
                                        }
                                    }
                                }
                                break;
                        }
                        if(
                            $chunk &&
                            is_array($chunk)
                        ){
//                            ddd($select);
//                            $start = (($chunk_nr + 1) * $options['chunk-size']) - $options['chunk-size'];
                            d($start);
                            $keys = [];
                            if(array_key_exists('list', $select)){
                                $keys = array_keys($select['list']);
                            }
                            foreach($chunk as $nr => $record){
                                $node = new Storage();
                                $node->data($record);
                                if(array_key_exists($nr, $keys)){
                                    $select_nr = $keys[$nr];
                                } else {
                                    $select_nr = null;
                                }
                                d($select_nr);
                                if(
                                    array_key_exists('force', $options) &&
                                    $options['force'] === true &&
                                    !empty($select_nr) &&
                                    array_key_exists($select_nr, $select['list']) &&
                                    is_object($select['list'][$select_nr]) &&
                                    property_exists($select['list'][$select_nr], 'uuid') &&
                                    !empty($select['list'][$select_nr]->uuid)
                                ){
                                    $node->set('uuid', $select['list'][$select_nr]->uuid);
                                    $put_many[] = $node->data();
                                }
                                elseif(
                                    array_key_exists('patch', $options) &&
                                    $options['patch'] === true &&
                                    !empty($select_nr) &&
                                    array_key_exists($select_nr, $select['list']) &&
                                    is_object($select['list'][$select_nr]) &&
                                    property_exists($select['list'][$select_nr], 'uuid') &&
                                    !empty($select['list'][$select_nr]->uuid)
                                ){
                                    $node->set('uuid', $select['list'][$select_nr]->uuid);
                                    $patch_many[] = $node->data();
                                }
                                elseif(!array_key_exists($select_nr, $select['list'])){
                                    $create_many[] = $node->data();
                                }
                                elseif(
                                    $select['list'][$select_nr] === null
                                ){
                                    $create_many[] = $node->data();
                                } else {
                                    $skip++;
                                }
                            }
                            d('create: ' . count($create_many));
                            d('patch: ' . count($patch_many));
                            d('put: ' . count($put_many));
                            d('skip: ' . $skip);
                            /*
                            if(!empty($create_many)) {
                                $response = $this->create_many($class, $role, $create_many, [
                                    'import' => true,
                                    'uuid' => $options['uuid']
                                ]);
                                if (
                                    array_key_exists('list', $response) &&
                                    is_array($response['list'])
                                ) {
                                    $create = count($response['list']);
                                } elseif (
                                    array_key_exists('error', $response)
                                ) {
                                    $error = $response['error'];
                                }
                            }
                            if(!empty($put_many)){
                                $response = $this->put_many($class, $role, $put_many, [
                                    'import' => true
                                ]);
                                if(
                                    array_key_exists('list', $response) &&
                                    is_array($response['list'])
                                ) {
                                    $put = count($response['list']);
                                }
                                elseif(
                                    array_key_exists('error', $response)
                                ){
                                    $error = array_merge($error, $response['error']);
                                }
                            }
                            if(!empty($patch_many)){
                                $response = $this->patch_many($class, $role, $patch_many, [
                                    'import' => true
                                ]);
                                if(
                                    array_key_exists('list', $response) &&
                                    is_array($response['list'])
                                ) {
                                    $patch = count($response['list']);
                                }
                                elseif(
                                    array_key_exists('error', $response)
                                ){
                                    $error = array_merge($error, $response['error']);
                                }
                            }
                            if(!empty($error)){
                                $this->unlock($name);
                                return [
                                    'error' => $error,
                                    'transaction' => true,
                                    'duration' => (microtime(true) - $start) * 1000
                                ];
                            }
                            */
                        }
                    }
                    $commit = [];
                    if($create > 0 || $put > 0 || $patch > 0){
                        $object->config('time.limit', 0);
                        $commit = $this->commit($class, $role);
                    } else {
                        $this->unlock($name);
                    }
                    $duration = microtime(true) - $start;
                    $total = $put + $patch + $create;
                    $item_per_second = round($total / $duration, 2);

                    $object->config('delete', 'node.transaction.' . $name);
                    return [
                        'skip' => $skip,
                        'put' => $put,
                        'patch' => $patch,
                        'create' => $create,
                        'commit' => $commit,
                        'mtime' => File::mtime($url),
                        'duration' => $duration * 1000,
                        'item_per_second' => $item_per_second,
                        'transaction' => true
                    ];
                } else {
                    foreach($list as $record){
                        $node = new Storage();
                        $node->data($record);
                        if(
                            $data_object &&
                            $data_object->has('is.unique')
                        ){
                            $record = false;
                            $unique = (array) $data_object->get('is.unique');
                            $unique = array_shift($unique);
                            $explode = explode(',', $unique);
                            $count = 0;
                            foreach($explode as $nr => $value){
                                $explode[$nr] = trim($value);
                                $count++;
                            }
                            switch ($count){
                                case 2:
                                    if(
                                        $node->has($explode[0]) &&
                                        $node->has($explode[1])
                                    ){
                                        $record = $this->record(
                                            $name,
                                            $role,
                                            [
                                                'filter' => [
                                                    $explode[0] => [
                                                        'value' => $node->get($explode[0]),
                                                        'operator' => '==='
                                                    ],
                                                    $explode[1] => [
                                                        'value' => $node->get($explode[1]),
                                                        'operator' => '==='
                                                    ]
                                                ],
                                                'transaction' => true,
                                            ]
                                        );
                                    }
                                    break;
                                case 1:
                                    if($node->has($explode[0])){
                                        $record = $this->record(
                                            $name,
                                            $role,
                                            [
                                                'filter' => [
                                                    $explode[0] => [
                                                        'value' => $node->get($explode[0]),
                                                        'operator' => '==='
                                                    ]
                                                ],
                                                'transaction' => true
                                            ]
                                        );
                                    }
                                    break;
                            }
                        } else {
                            $record = ['node' => $record];
                        }
                        if($record){
                            if(
                                array_key_exists('force', $options) &&
                                $options['force'] === true &&
                                array_key_exists('node', $record) &&
                                property_exists($record['node'], 'uuid') &&
                                !empty($record['node']->uuid)
                            ){
                                $node->set('uuid', $record['node']->uuid);
                                $put_many[] = $node->data();
                            }
                            elseif(
                                array_key_exists('patch', $options) &&
                                $options['patch'] === true &&
                                array_key_exists('node', $record) &&
                                property_exists($record['node'], 'uuid') &&
                                !empty($record['node']->uuid)
                            ){
                                $node->set('uuid', $record['node']->uuid);
                                $patch_many[] = $node->data();
                            } else {
                                $skip++;
                            }
                        } else {
                            if(!$options['uuid'] === true){
                                $node->delete('uuid');
                            }
                            $create_many[] = $node->data();
                        }
                    }
                }
            }
            if(!empty($create_many)) {
                $response = $this->create_many($class, $role, $create_many, [
                    'import' => true,
                    'uuid' => $options['uuid']
                ]);
                if (
                    array_key_exists('list', $response) &&
                    is_array($response['list'])
                ) {
                    $create = count($response['list']);
                } elseif (
                    array_key_exists('error', $response)
                ) {
                    $error = $response['error'];
                }
            }
            if(!empty($put_many)){
                $response = $this->put_many($class, $role, $put_many, [
                    'import' => true
                ]);
                if(
                    array_key_exists('list', $response) &&
                    is_array($response['list'])
                ) {
                    $put = count($response['list']);
                }
                elseif(
                    array_key_exists('error', $response)
                ){
                    $error = array_merge($error, $response['error']);
                }
            }
            if(!empty($patch_many)){
                $response = $this->patch_many($class, $role, $patch_many, [
                    'import' => true
                ]);
                if(
                    array_key_exists('list', $response) &&
                    is_array($response['list'])
                ) {
                    $patch = count($response['list']);
                }
                elseif(
                    array_key_exists('error', $response)
                ){
                    $error = array_merge($error, $response['error']);
                }
            }
            if(!empty($error)){
                $this->unlock($name);
                return [
                    'error' => $error,
                    'transaction' => true,
                    'duration' => (microtime(true) - $start) * 1000
                ];
            }
            $commit = [];
            if($create > 0 || $put > 0 || $patch > 0){
                $object->config('time.limit', 0);
                $commit = $this->commit($class, $role);
            } else {
                $this->unlock($name);
            }
            $duration = microtime(true) - $start;
            $total = $put + $patch + $create;
            $item_per_second = round($total / $duration, 2);

            $object->config('delete', 'node.transaction.' . $name);
            return [
                'skip' => $skip,
                'put' => $put,
                'patch' => $patch,
                'create' => $create,
                'commit' => $commit,
                'mtime' => File::mtime($url),
                'duration' => $duration * 1000,
                'item_per_second' => $item_per_second,
                'transaction' => true
            ];
        }
        catch(Exception $exception){
            $this->unlock($name);
            $object->config('delete', 'node.transaction.' . $name);
            throw $exception;
        }
    }
}