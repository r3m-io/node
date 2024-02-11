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
        Core::interactive();
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
            $object->config('r3m.io.node.import.start', microtime(true));
            $options['function'] = __FUNCTION__;
            $options['relation'] = false;
            $response_list = [];
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
            $dir_validate = $object->config('project.dir.node') .
                'Validate' .
                $object->config('ds')
            ;
            $url = $dir_data .
                $name .
                $object->config('extension.json')
            ;
            $url_validate = $dir_validate .
                $name .
                $object->config('extension.json')
            ;
            $data_validate = $object->data_read($url_validate, sha1($url_validate));
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
                $object->config('r3m.io.node.import.list.count', $list_count);
                $list = array_chunk($list, $options['chunk-size']);
                foreach($list as $chunk_nr => $chunk){
                    $filter_value_1 = [];
                    $filter_value_2 = [];
                    $count = 0;
                    $explode = [];
                    $create_many = [];
                    $put_many = [];
                    $patch_many = [];
                    $skip = 0;
                    d($chunk);
                    foreach($chunk as $record_nr => $record){
                        $node = new Storage();
                        $node->data($record);
                        if (
                            $data_object &&
                            $data_object->has('is.unique') &&
                            !empty($data_object->get('is.unique'))
                        ) {
                            $unique = (array) $data_object->get('is.unique');
                            $unique = array_shift($unique);
                            $explode = explode(',', $unique);
                            $count = 0;
                            foreach ($explode as $nr => $value) {
                                $explode[$nr] = trim($value);
                                $count++;
                            }
                            $allow_empty = $this->allow_empty($name, $data_validate, $explode);

                            switch ($count) {
                                case 2:
                                    if(
                                        $allow_empty[0] !== false &&
                                        $allow_empty[1] !== false
                                    ){
                                        //2 attributes are allowed to be empty
                                        throw new Exception('Unique value cannot be empty...');
                                    }
                                    elseif(
                                        $allow_empty[0] !== false &&
                                        $allow_empty[1] === false &&
                                        $node->has($explode[1])
                                    ){
                                        //1 attribute is allowed to be empty
                                        $match_1 = $node->get($explode[0]);
                                        $match_2 = $node->get($explode[1]);
                                        if(
                                            $match_1 !== null &&
                                            $match_1 !== '' &&
                                            $match_2 !== null &&
                                            $match_2 !== ''
                                        ){
                                            $filter_value_1[$record_nr] = $match_1;
                                            $filter_value_2[$record_nr] = $match_2;
                                        }
                                        elseif(
                                            $match_1 === null &&
                                            $match_2 !== null &&
                                            $match_2 !== ''
                                        ){
                                            $explode[0] = $explode[1];
                                            $filter_value_1[$record_nr] = $match_2;
                                            $count = 1;
                                        } else {
                                            throw new Exception('Unique value cannot be empty...');
                                        }
                                    }
                                    elseif(
                                        $allow_empty[0] === false &&
                                        $allow_empty[1] !== false &&
                                        $node->has($explode[0])
                                    ){
                                        //1 attribute is allowed to be empty
                                        d($allow_empty);
                                        d($explode);
                                        ddd($node);
                                    }
                                    elseif(
                                        $allow_empty[0] === false &&
                                        $allow_empty[1] === false &&
                                        $node->has($explode[0]) &&
                                        $node->has($explode[1])
                                    ){
                                        //both attributes should not be empty
                                        $match_1 = $node->get($explode[0]);
                                        $match_2 = $node->get($explode[1]);
                                        if(
                                            $match_1 !== null &&
                                            $match_1 !== '' &&
                                            $match_2 !== null &&
                                            $match_2 !== ''
                                        ){
                                            $filter_value_1[$record_nr] = $match_1;
                                            $filter_value_2[$record_nr] = $match_2;
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
                                            $filter_value_1[$record_nr] = $match_1;
                                        } else {
                                            throw new Exception('Unique value cannot be empty...');
                                        }
                                        /*
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
                                        */
                                    } else {
                                        throw new Exception('Unique value cannot be empty...');
                                    }
                                    break;
                            }
                        }
                    }
                    ddd($count);
                    switch($count){
                        case 0 :
                            $create_many = $chunk;
                            ddd($create_many);
                            $response = $this->update(
                                $class,
                                $role,
                                $options,
                                $create_many,
                                $put_many,
                                $patch_many,
                                $skip
                            );
                            $response_list[] = $response;
                            break;
                        case 1 :
                            if(
                                !empty($explode[0]) &&
                                !empty($filter_value_1)
                            ) {
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
                                        'key' => [
                                            $explode[0]
                                        ],
                                        'transaction' => true,
                                        'limit' => $options['chunk-size'],
                                        'page' => 1
                                    ]
                                );
                                foreach ($filter_value_1 as $nr => $key) {
                                    if (
                                        is_array($select) &&
                                        array_key_exists('list', $select) &&
                                        array_key_exists($key, $select['list'])
                                    ) {
                                        if (
                                            array_key_exists('force', $options) &&
                                            $options['force'] === true
                                        ) {
                                            $node = new Storage($chunk[$nr]);
                                            $node->set('uuid', $select['list'][$key]->uuid);
                                            $put_many[] = $node->data();
                                        } elseif (
                                            array_key_exists('patch', $options) &&
                                            $options['patch'] === true
                                        ) {
                                            $node = new Storage($chunk[$nr]);
                                            $node->set('uuid', $select['list'][$key]->uuid);
                                            $patch_many[] = $node->data();
                                        } else {
                                            $skip++;
                                        }
                                    } else {
                                        $create_many[] = $chunk[$nr];
                                    }
                                }
                                $response = $this->update(
                                    $class,
                                    $role,
                                    $options,
                                    $create_many,
                                    $put_many,
                                    $patch_many,
                                    $skip
                                );
                                $response_list[] = $response;
                            }
                            break;
                        case 2:
                            if(
                                !empty($explode[0]) &&
                                !empty($explode[1]) &&
                                !empty($filter_value_1) &&
                                !empty($filter_value_2)
                            ){
//                                    d($filter_value_1); //id
//                                    ddd($filter_value_2); //class
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
                                        'key' => [
                                            $explode[0]
                                        ],
                                        'transaction' => true,
                                        'limit' => $options['chunk-size'],
                                        'page' => 1
                                    ]
                                );
                                $select_filter = [];
                                foreach($filter_value_1 as $nr => $key){
                                    if(
                                        is_array($select) &&
                                        array_key_exists('list', $select) &&
                                        array_key_exists($key, $select['list'])
                                    ){
                                        //do check with filter var 2
                                        //need record // chunk[chunk_nr]
                                        $node = new Storage($chunk[$nr]);
                                        if($node->get($explode[1]) === $filter_value_2[$nr]){
                                            if(
                                                array_key_exists('force', $options) &&
                                                $options['force'] === true
                                            ){
                                                $node->set('uuid', $select['list'][$key]->uuid);
                                                $put_many[] = $node->data();
                                            }
                                            elseif(
                                                array_key_exists('patch', $options) &&
                                                $options['patch'] === true
                                            ){
                                                $node->set('uuid', $select['list'][$key]->uuid);
                                                $patch_many[] = $node->data();
                                            }
                                            else {
                                                $skip++;
                                            }
                                        } else {
                                            $create_many[] = $chunk[$nr];
                                        }
                                    } else {
                                        $create_many[] = $chunk[$nr];
                                    }
                                }
                                $response = $this->update(
                                    $class,
                                    $role,
                                    $options,
                                    $create_many,
                                    $put_many,
                                    $patch_many,
                                    $skip
                                );
                                $response_list[] = $response;
                            }
                            break;
                    }
                }
                if(count($response_list) === 1){
                    return $response_list[0];
                } else {
                    return $response_list;
                }
            }
        }
        catch(Exception $exception){
            $this->unlock($name);
            $object->config('delete', 'node.transaction.' . $name);
            throw $exception;
        }
        return false;
    }

    private function allow_empty($class, $data_validate, $attribute_list=[]): array
    {
        $allow_empty = [];
        foreach($attribute_list as $nr => $attribute){
            $attribute_validate = $data_validate->get($class . '.create.validate.' . $attribute);
            if(empty($attribute_validate)){
                $allow_empty[$nr] = false;
                continue;
            }
            elseif(is_array($attribute_validate)){
                foreach($attribute_validate as $attribute_validate_nr => $attribute_validate_value){
                    if(
                        is_object($attribute_validate_value) &&
                        property_exists($attribute_validate_value, 'is.unique') &&
                        property_exists($attribute_validate_value->{'is.unique'}, 'allow_empty')
                    ){
                        $allow_empty[$nr] = $attribute_validate_value->{'is.unique'}->allow_empty;
                    }
                }
            }
        }
        return $allow_empty;
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    private function update($class, $role, $options=[], $create_many=[], $put_many=[], $patch_many=[], $skip=0): array
    {
        $name = Controller::name($class);
        $object = $this->object();
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $error = [];
        $dir_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds')
        ;
        $url = $dir_data .
            $name .
            $object->config('extension.json')
        ;
        $put = 0;
        $patch = 0;
        $create = 0;
        if(!empty($create_many)) {
            $response = $this->create_many($name, $role, $create_many, [
                'import' => true,
                'uuid' => $options['uuid'],
                'validation' => $options['validation'] ?? true
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
            $response = $this->put_many($name, $role, $put_many, [
                'import' => true,
                'validation' => $options['validation'] ?? true
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
            $response = $this->patch_many($name, $role, $patch_many, [
                'import' => true,
                'validation' => $options['validation'] ?? true
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
                'duration' => (microtime(true) - $object->config('r3m.io.node.import.start')) * 1000
            ];
        }
        $commit = [];
        if($create > 0 || $put > 0 || $patch > 0){
            $object->config('time.limit', 0);
            $commit = $this->commit($class, $role);
        } else {
            $this->unlock($name);
        }
        $duration = microtime(true) - $object->config('r3m.io.node.import.start');
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
}