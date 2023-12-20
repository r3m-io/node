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

Trait Import {

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function import($class, $role, $options=[]): array
    {
        $name = Controller::name($class);
        $object = $this->object();
        $dir_cache = $object->config('framework.dir.temp') .
            'Node' .
            $object->config('ds')
        ;
        $dir_lock = $dir_cache .
            'Lock' .
            $object->config('ds')
        ;
        $url_lock = $dir_lock .
            $name .
            $object->config('extension.lock');
        try {
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
                return [];
            }
            $dir_data = $object->config('project.dir.node') .
                'Data' .
                $object->config('ds')
            ;
            $url = $dir_data .
                $name .
                $object->config('extension.json')
            ;
            if(File::exist($url_lock)){
                while(File::exist($url_lock)){
                    sleep(1);
                }
                //wait and retry
                // LOCK_WAIT_TIMEOUT
                ddd('make retry mechanism');
            } else {
                Dir::create($dir_lock, Dir::CHMOD);
                $command = 'chown www-data:www-data ' . $dir_lock;
                exec($command);
                File::touch($url_lock);
                $command = 'chown www-data:www-data ' . $url_lock;
                exec($command);
                if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                    $command = 'chmod 777 ' . $dir_lock;
                    exec($command);
                    $command = 'chmod 666 ' . $url_lock;
                    exec($command);
                }
                sleep(10);
            }
            $app_options = App::options($object);
            if(property_exists($app_options, 'force')){
                $options['force'] = $app_options->force;
            }
            $data = $object->data_read($options['url']);
            if($data){
                $list = $data->data($name);
                $url_object = $object->config('project.dir.node') .
                    'Object' .
                    $object->config('ds') .
                    $name .
                    $object->config('extension.json')
                ;
                $data_object = $object->data_read($url_object, sha1($url_object));
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
                        $node->delete('uuid');
                        $create_many[] = $node->data();
                    }
                }
            }
            if(!empty($create_many)) {
                $response = $this->create_many($class, $role, $create_many, [
                    'transaction' => true,
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
                    'transaction' => true
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
                    'transaction' => true
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
                File::delete($url_lock);
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
            }
            $duration = microtime(true) - $start;
            $total = $put + $patch + $create;
            $item_per_second = round($total / $duration, 2);
            File::delete($url_lock);
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
            File::delete($url_lock);
            throw $exception;
        }
    }
}