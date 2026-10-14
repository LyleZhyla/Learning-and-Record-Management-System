$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $projectRoot '.mysql-data\my.ini'
$mysqlBinary = 'C:\xampp\mysql\bin\mysqld.exe'
$mysqlAdmin = 'C:\xampp\mysql\bin\mysqladmin.exe'
$npx = (Get-Command npx.cmd -ErrorAction Stop).Source
$startedDatabase = $false

if (-not (Test-Path -LiteralPath $mysqlBinary)) {
    throw "MariaDB was not found at $mysqlBinary."
}

if (-not (Test-Path -LiteralPath $configPath)) {
    throw "The isolated MariaDB configuration was not found at $configPath."
}

Set-Location -LiteralPath $projectRoot

# Keep every child process on the project's isolated MariaDB instance even if
# the local .env file was copied again from the default 3306 configuration.
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3307'
$env:DB_DATABASE = 'smart_nstp'

& php artisan config:clear --ansi

if ($LASTEXITCODE -ne 0) {
    throw 'Laravel failed to clear its cached configuration.'
}

$commands = [System.Collections.Generic.List[string]]::new()
$names = [System.Collections.Generic.List[string]]::new()
$colors = [System.Collections.Generic.List[string]]::new()

$databaseListener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue

if (-not $databaseListener) {
    $commands.Add("`"$mysqlBinary`" --defaults-file=`"$configPath`" --console")
    $names.Add('database')
    $colors.Add('yellow')
    $startedDatabase = $true
}

$commands.Add('php artisan serve --port=8765')
$commands.Add('php artisan queue:listen --tries=1')
$commands.Add('npm run dev')
$names.Add('laravel')
$names.Add('queue')
$names.Add('vite')
$colors.Add('cyan')
$colors.Add('green')
$colors.Add('magenta')

try {
    & $npx concurrently `
        --kill-others-on-fail `
        --names ($names -join ',') `
        --prefix-colors ($colors -join ',') `
        @commands

    $exitCode = $LASTEXITCODE
}
finally {
    if ($startedDatabase) {
        $databaseListener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue

        if ($databaseListener) {
            & $mysqlAdmin -u root -P 3307 -h 127.0.0.1 shutdown 2>$null
        }
    }
}

exit $exitCode
