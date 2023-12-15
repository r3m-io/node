{{R3M}}
### Setup
{{Package.R3m.Io.Node:Init:register()}}
/*
{{$request = request()}}
{{$class = 'System.Installation'}}
{{$options = [
'where' => 'name === "' + $request.package + '"',
]}}
{{$response = R3m.Io.Node:Data:record(
$class,
R3m.Io.Node:Role:role.system(),
$options
)}}
{{if(is.empty($response))}}
{{$output = execute(binary() + ' r3m_io/node create -class=System.Installation -name=' +  $request.package +' -ctime=' + time() + ' -mtime=' + time())}}
- {{$request.package}} installed...
{{else}}
- Skipping {{$request.package}} installation...
{{/if}}
*/