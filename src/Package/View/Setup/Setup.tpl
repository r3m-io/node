{{R3M}}
### Setup

{{$request = request()}}
{{$class = 'System.Installation'}}
{{$options = [
'where' => 'name === "' + $request.package + '"',
]}}
{{$response = R3m.Io.Node:Data:record(
$class,
R3m.Io.Node:Role:role_system(),
$options
)}}
{{if(is.empty($response))}}
{{$output = execute(binary() + ' ' + $request.package + ' create -class=System.Installation -name=' +  $request.package +' -ctime=' + time() + ' -mtime=' + time())}}
{{implode("\n", $output)}}

- {{$request.package}} installed...

{{else}}
- Skipping {{$request.package}} installation...

{{/if}}