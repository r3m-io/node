{{R3M}}
### Setup
{{$request = request()}}
{{$class = 'System.Installation'}}
{{$options = [
'where' => 'name === ' + $request.package
]}}
{{$response = R3m.Io.Node:Data:record(
$class,
R3m.Io.Node:Role:role_system(),
$options
)}}
{{if(is.empty($response))}}
{{$output = execute(binary() + ' ' + $request.package + ' create -class=System.Installation -name=r3m_io/node -ctime=' + time() + ' -mtime=' + time())}}
{{else}}
- Skipping {{$request.package}} installation

{{/if}}