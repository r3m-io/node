{{R3M}}
{{$request = request()}}
{{$options = options()}}
{{$class = data.extract('options.class')}}
{{if(is.empty($class))}}
You need to provide the option class.
Available classes:
{{$read = dir.read(config('project.dir.data') + 'Node/Object/')}}
{{if(is.array($read))}}
{{$read = data.sort($read, ['name' => 'ASC'])}}
{{for.each($read as $file)}}
- {{file.basename($file.name, config('extension.json'))}}

{{/for.each}}
{{/if}}
{{else}}
{{$response = R3m.Io.Node:Data:create(
$class,
R3m.Io.Node:Role:role_system(),
$options
)}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}


{{/if}}