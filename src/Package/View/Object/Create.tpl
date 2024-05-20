{{$request = request()}}
{{$options = options()}}
{{$class = data.extract('options.class')}}
{{$force = data.extract('options.force')}}
{{dd($force)}}
{{if(is.empty($class))}}
You need to provide the option class for the new class name.
{{else}}
{{R3m.Io.Node:Data:object.create(
$class,
R3m.Io.Node:Role:role_system(),
$options,
[
'force' => $force
])}}
{{/if}}