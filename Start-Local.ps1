param(
    [int]$Port = 8002,
    [string]$PhpPath = 'php'
)

$ErrorActionPreference = 'Stop'
$phpExecutable = (Get-Command $PhpPath -ErrorAction Stop).Source
$router = Join-Path $PSScriptRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'

Push-Location (Join-Path $PSScriptRoot 'public')
try {
    & $phpExecutable -d upload_max_filesize=32M -d post_max_size=36M -d memory_limit=512M -S "127.0.0.1:$Port" $router
}
finally {
    Pop-Location
}
