<?php
namespace Package\R3m\Io\Node\Trait;

use R3m\Io\App;

use R3m\Io\Module\Core;
use R3m\Io\Module\File;

use R3m\Io\Node\Model\Node;
trait Init {


    public function register (){
        $object = $this->object();
        $options = App::options($object);
        echo 'register' . PHP_EOL;
//        ddd('register');
        /*
        $url_package = $object->config('project.dir.vendor') . 'r3m_io/boot/Data/Package.json';
        $class = File::basename($url_package, $object->config('extension.json'));
        $packages = $object->data_read($url_package);
        $node = new Node($object);
        if($packages){
            foreach($packages->data($class) as $nr => $package){
                $options = [
                    'where' => 'name === "' . $package  . '"'
                ];
                $response = $node->record(
                    'System.Installation',
                    $node->role_system(),
                    $options
                );
                if(!$response){
                    $command = Core::binary($object) . ' install ' . $package;
                    Core::execute($object, $command);
                } else {
                    echo 'Skipping ' . $package . ' installation...' . PHP_EOL;
                }
            }
        }
        */
    }

    /*
{{$installation = 'System.Installation'}}
{{$packages = array.read(config('project.dir.vendor') + 'r3m_io/boot/Data/Package.json' )}}
{{if(!is.empty($packages))}}
{{for.each($packages as $nr => $package)}}
{{$options = [
    'where' => 'name === "' + $package  + '"'
]}}
{{$response = R3m.Io.Node:Data:record(
$installation,
R3m.Io.Node:Role:role.system(),
$options
)}}
{{if(is.empty($response))}}
{{$command = binary() + ' install ' + $package}}
{{$command}}

- Installing {{$package}} ...

{{$output = execute($command)}}
{{if(is.array($output))}}
{{implode("\n", $output)}}
{{/if}}

{{else}}
- Skipping {{$package}} installation
{{/if}}
{{/for.each}}
{{/if}}
*/
}