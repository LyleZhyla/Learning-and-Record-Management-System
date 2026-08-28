$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$dataPath = Join-Path $projectRoot '.mysql-data'
$configPath = Join-Path $dataPath 'my.ini'
$mysqlBinary = 'C:\xampp\mysql\bin\mysqld.exe'
$mysqlAdmin = 'C:\xampp\mysql\bin\mysqladmin.exe'

if (-not (Test-Path -LiteralPath $configPath)) {
    throw 'The isolated MariaDB data directory has not been initialized.'
}

$listener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if ($listener) {
    Write-Output 'The Smart NSTP MariaDB instance is already running on port 3307.'
    exit 0
}

$arguments = @(
    "--defaults-file=$configPath",
    "--pid-file=$(Join-Path $dataPath 'mysqld.pid')",
    "--log-error=$(Join-Path $dataPath 'mysql_error.log')"
)

$process = Start-Process -FilePath $mysqlBinary -ArgumentList $arguments -WorkingDirectory (Split-Path $mysqlBinary) -WindowStyle Hidden -PassThru
Start-Sleep -Seconds 3

if ($process.HasExited) {
    throw "MariaDB failed to start. Check $dataPath\mysql_error.log."
}

& $mysqlAdmin -u root -P 3307 -h 127.0.0.1 ping
Write-Output "Smart NSTP MariaDB started on port 3307 (PID $($process.Id))."
