$ErrorActionPreference = 'Stop'

$mysqlAdmin = 'C:\xampp\mysql\bin\mysqladmin.exe'
$listener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue

if (-not $listener) {
    Write-Output 'The Smart NSTP MariaDB instance is not running.'
    exit 0
}

& $mysqlAdmin -u root -P 3307 -h 127.0.0.1 shutdown

if ($LASTEXITCODE -ne 0) {
    throw 'MariaDB did not shut down cleanly.'
}

Write-Output 'Smart NSTP MariaDB stopped safely.'
