{{R3M}}
{{$request = request()}}
{{$options = options()}}
{{$class = data.extract('options.class')}}
{{$response = R3m.Io.Node:Data:put(
$class,
R3m.Io.Node:Role:role_system(),
$options
)}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

