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
