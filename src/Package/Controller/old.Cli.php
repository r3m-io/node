<?php
namespace Package\R3m\Io\Node\Controller;

use Exception;
use R3m\Io\App;
use R3m\Io\Exception\LocateException;
use R3m\Io\Exception\ObjectException;
use R3m\Io\Exception\UrlEmptyException;
use R3m\Io\Exception\UrlNotExistException;
use R3m\Io\Module\Controller;
use R3m\Io\Module\Data;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;

class Cli extends Controller {
    const DIR = __DIR__ . '/';
    const MODULE_INFO = 'info';
    const INFO = [
        '{{binary()}} r3m_io/node                    | Node (Object store) options',
        '{{binary()}} r3m_io/node setup              | Node setup'
    ];

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function run(App $object){
        Cli::plugin(
            $object,
            $object->config('project.dir.package') .
            'R3m/Io' .
            $object->config('ds') .
            'Node' .
            $object->config('ds') .
            'Plugin' .
            $object->config('ds')
        );
        Cli::validator(
            $object,
            $object->config('project.dir.package') .
            'R3m/Io' .
            $object->config('ds') .
            'Node' .
            $object->config('ds') .
            'Validator' .
            $object->config('ds')
        );
        $url = false;
        $autoload = [];
        $data = new Data();
//        $data->set('prefix', 'Node');
//        $data->set('directory', $object->config('project.dir.root') . 'Node/');
//        $autoload[] = clone $data->data();
//        $data->clear();
//        $data->set('autoload', $autoload);
//        Cli::autoload($object, $data);
        $package = strtolower($object->request(0));
        $object->request('package', $package);
        $scan = Cli::scan($object, $package);
        $module = App::parameter($object, $package, 1);
        if(!in_array($module, $scan['module'])){
            $module = Cli::MODULE_INFO;
        }
        $submodule = App::parameter($object, $package, 2);
        if(
            !in_array(
                $submodule,
                $scan['submodule'],
                true
            )
        ){
            $submodule = false;
        }
        $command = App::parameter($object, $package, 3);
        if(
            !in_array(
                $command,
                $scan['command'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO
        ){
            $command = false;
        }
        $subcommand = App::parameter($object, $package, 4);
        if(
            !in_array(
                $subcommand,
                $scan['subcommand'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === CLI::MODULE_INFO
        ){
            $subcommand = false;
        }
        $action = App::parameter($object, $package, 5);
        if(
            !in_array(
                $action,
                $scan['action'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === CLI::MODULE_INFO ||
            $subcommand === CLI::MODULE_INFO
        ){
            $action = false;
        }
        $subaction = App::parameter($object, $package, 6);
        if(
            !in_array(
                $subaction,
                $scan['subaction'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === CLI::MODULE_INFO ||
            $subcommand === CLI::MODULE_INFO ||
            $action === CLI::MODULE_INFO
        ){
            $subaction = false;
        }
        $category = App::parameter($object, $package, 7);
        if(
            !in_array(
                $category,
                $scan['category'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === CLI::MODULE_INFO ||
            $subcommand === CLI::MODULE_INFO ||
            $action === CLI::MODULE_INFO ||
            $subaction === CLI::MODULE_INFO
        ){
            $category = false;
        }
        $subcategory = App::parameter($object, $package, 8);
        if(
            !in_array(
                $subcategory,
                $scan['subcategory'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === CLI::MODULE_INFO ||
            $subcommand === CLI::MODULE_INFO ||
            $action === CLI::MODULE_INFO ||
            $subaction === CLI::MODULE_INFO ||
            $category == CLI::MODULE_INFO
        ){
            $subcategory = false;
        }
        try {
            if(
                !empty($module) &&
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand) &&
                !empty($action) &&
                !empty($subaction) &&
                !empty($category) &&
                !empty($subcategory)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $object->request('command', $command);
                $object->request('subcommand', $subcommand);
                $object->request('action', $action);
                $object->request('subaction', $subaction);
                $object->request('category', $category);
                $object->request('subcategory', $subcategory);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand) .
                    '.' .
                    ucfirst($action) .
                    '.' .
                    ucfirst($subaction) .
                    '.' .
                    ucfirst($category) .
                    '.' .
                    ucfirst($subcategory)
                );
            }
            elseif(
                !empty($module) &&
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand) &&
                !empty($action) &&
                !empty($subaction) &&
                !empty($category)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $object->request('command', $command);
                $object->request('subcommand', $subcommand);
                $object->request('action', $action);
                $object->request('subaction', $subaction);
                $object->request('category', $category);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand) .
                    '.' .
                    ucfirst($action) .
                    '.' .
                    ucfirst($subaction) .
                    '.' .
                    ucfirst($category)
                );
            }
            elseif(
                !empty($module) &&
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand) &&
                !empty($action) &&
                !empty($subaction)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $object->request('command', $command);
                $object->request('subcommand', $subcommand);
                $object->request('action', $action);
                $object->request('subaction', $subaction);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand) .
                    '.' .
                    ucfirst($action) .
                    '.' .
                    ucfirst($subaction)
                );
            }
            elseif(
                !empty($module) &&
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand) &&
                !empty($action)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $object->request('command', $command);
                $object->request('subcommand', $subcommand);
                $object->request('action', $action);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand) .
                    '.' .
                    ucfirst($action)
                );
            }
            elseif(
                !empty($module) &&
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $object->request('command', $command);
                $object->request('subcommand', $subcommand);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand)
                );
            }
            elseif(
                !empty($module) &&
                !empty($submodule) &&
                !empty($command)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $object->request('command', $command);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command)
                );
            }
            elseif(
                !empty($module) &&
                !empty($submodule)
            ){
                $object->request('module', $module);
                $object->request('submodule', $submodule);
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule)
                );
            }
            elseif(
                !empty($module)
            ){
                $object->request('module', $module);
                $url = Cli::locate(
                    $object,
                    ucfirst($module)
                );
            }
            if($url){
                return Cli::response($object, $url);
            }
        } catch (Exception | UrlEmptyException | UrlNotExistException | LocateException $exception){
            return $exception;
        }
        return null;
    }

    private static function scan(App $object, $package=''): array
    {
        $scan = [
            'module' => [],
            'submodule' => [],
            'command' => [],
            'subcommand' => [],
            'action' => [],
            'subaction' => [],
            'category' => [],
            'subcategory' => []
        ];
        $url = $object->config('controller.dir.root') .
            'View' .
            $object->config('ds')
        ;
        if(!Dir::exist($url)){
            return $scan;
        }
        $dir = new Dir();
        $read = $dir->read($url, true);
        if(!$read){
            return $scan;
        }
        foreach($read as $nr => $file){
            if($file->type !== File::TYPE){
                continue;
            }
            $part = substr($file->url, strlen($url));
            $explode = explode('/', $part, 8);
            if(array_key_exists(0, $explode) === false){
                continue;
            }
            $module = false;
            $submodule = false;
            $command = false;
            $subcommand = false;
            $action = false;
            $subaction = false;
            $category = false;
            $subcategory = false;
            if(array_key_exists(0, $explode)){
                $module = strtolower(File::basename($explode[0], $object->config('extension.tpl')));
                $temp = explode('.', $module, 2);
                if(array_key_exists(1, $temp)){
                    $module = $temp[0];
                    $submodule = $temp[1];
                } else {
                    if(array_key_exists(1, $explode)){
                        $submodule = strtolower(File::basename($explode[1], $object->config('extension.tpl')));
                        $temp = explode('.', $submodule, 2);
                        if(array_key_exists(1, $temp)){
                            $submodule = $temp[0];
                            $command = $temp[1];
                        }
                    }
                }
            }
            if(array_key_exists(2, $explode)){
                $command = strtolower(File::basename($explode[2], $object->config('extension.tpl')));
                $temp = explode('.', $command, 2);
                if(array_key_exists(1, $temp)){
                    $command = $temp[0];
                    $subcommand = $temp[1];
                }
            }
            if(array_key_exists(3, $explode) && $subcommand === false){
                $subcommand = strtolower(File::basename($explode[3], $object->config('extension.tpl')));
                $temp = explode('.', $subcommand, 2);
                if(array_key_exists(1, $temp)){
                    $subcommand = $temp[0];
                    $action = $temp[1];
                }
            }
            if(array_key_exists(4, $explode) && $action === false){
                $action = strtolower(File::basename($explode[4], $object->config('extension.tpl')));
                $temp = explode('.', $subcommand, 2);
                if(array_key_exists(1, $temp)){
                    $action = $temp[0];
                    $subaction = $temp[1];
                }
            }
            if(array_key_exists(5, $explode) && $subaction === false){
                $subaction = strtolower(File::basename($explode[5], $object->config('extension.tpl')));
                $temp = explode('.', $subaction, 2);
                if(array_key_exists(1, $temp)){
                    $subaction = $temp[0];
                    $category = $temp[1];
                }
            }
            if(array_key_exists(6, $explode) && $category === false){
                $category = strtolower(File::basename($explode[6], $object->config('extension.tpl')));
                $temp = explode('.', $category, 2);
                if(array_key_exists(1, $temp)){
                    $category = $temp[0];
                    $subcategory = $temp[1];
                }
            }
            if(array_key_exists(7, $explode) && $subcategory === false){
                $subcategory = strtolower(File::basename($explode[7], $object->config('extension.tpl')));
            }
            if(
                !in_array(
                    $module,
                    $scan['module'],
                    true
                )
            ){
                $scan['module'][] = $module;
            }
            if(
                $submodule &&
                !in_array(
                    $submodule,
                    $scan['submodule'],
                    true
                )
            ){
                $scan['submodule'][] = $submodule;
            }
            if(
                $command  &&
                !in_array(
                    $command,
                    $scan['command'],
                    true
                )
            ){
                $scan['command'][] = $command;
            }
            if(
                $subcommand &&
                !in_array(
                    $subcommand,
                    $scan['subcommand'],
                    true
                )
            ){
                $scan['subcommand'][] = $subcommand;
            }
            if(
                $action &&
                !in_array(
                    $action,
                    $scan['action'],
                    true
                )
            ){
                $scan['action'][] = $subcommand;
            }
            if(
                $subaction &&
                !in_array(
                    $subaction,
                    $scan['subaction'],
                    true
                )
            ){
                $scan['subaction'][] = $subcommand;
            }
            if(
                $category &&
                !in_array(
                    $category,
                    $scan['category'],
                    true
                )
            ){
                $scan['category'][] = $category;
            }
            if(
                $subcategory &&
                !in_array(
                    $subcategory,
                    $scan['subcategory'],
                    true
                )
            ){
                $scan['subcategory'][] = $subcategory;
            }
        }
        return $scan;
    }
}