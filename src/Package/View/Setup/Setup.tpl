{{R3M}}
### Setup
{{$request = request()}}
{{dd($request)}}
{{$class = 'System.Installation'}}
{{$options = [
'where' => 'name === r3m_io/node'
]}}
{{$response = R3m.Io.Node:Data:record(
$class,
R3m.Io.Node:Role:role_system(),
$options
)}}
{{if(is.empty($response))}}
{{$output = execute(binary() + ' r3m_io/node create -class=System.Installation -name=r3m_io/node -ctime=' + time() + ' -mtime=' + time())}}
{{else}}
- Skipping r3m_io/node installation

{{/if}}