<#
  SIES backup: the database plus the private files (student photos), in one zip.
  Old backups are deleted after -KeepDays days.

  Run it by hand to test:
    powershell -ExecutionPolicy Bypass -File scripts\backup.ps1

  Then schedule it every night with Windows Task Scheduler (see docs\DEPLOYMENT.md).
  Copy the zip files to a second drive or another computer too. A backup that sits on the same disk
  as the system is lost with it.
#>
param(
    [string]$ProjectDir = (Split-Path -Parent $PSScriptRoot),
    [string]$OutDir = 'C:\sies-backups',
    [int]$KeepDays = 30,
    [string]$MysqlDump = 'mysqldump'
)

$ErrorActionPreference = 'Stop'

function Read-DotEnv([string]$path) {
    $map = @{}
    foreach ($line in Get-Content $path) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$') {
            $value = $Matches[2]
            if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $map[$Matches[1]] = $value
        }
    }
    return $map
}

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$log = Join-Path $OutDir 'backup.log'
function Write-Log([string]$text) { Add-Content -Path $log -Value ("{0}  {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $text) }

$work = Join-Path $env:TEMP ("sies-backup-" + [guid]::NewGuid().ToString('N'))
try {
    $envFile = Join-Path $ProjectDir '.env'
    if (-not (Test-Path $envFile)) { throw "Cannot find $envFile" }
    $cfg = Read-DotEnv $envFile
    if ($cfg['DB_CONNECTION'] -ne 'mysql' -and $cfg['DB_CONNECTION'] -ne 'mariadb') { throw "DB_CONNECTION is '$($cfg['DB_CONNECTION'])'. This script backs up MySQL only." }

    New-Item -ItemType Directory -Force -Path $work | Out-Null
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
    $sql = Join-Path $work "sies-database-$stamp.sql"

    # The password goes in a temporary options file, not on the command line.
    $options = Join-Path $work 'client.cnf'
    $port = if ($cfg['DB_PORT']) { $cfg['DB_PORT'] } else { '3306' }
    $hostName = if ($cfg['DB_HOST']) { $cfg['DB_HOST'] } else { '127.0.0.1' }
    # The admin account can read views and triggers; the app account may only touch rows.
    $dumpUser = if ($cfg['DB_ADMIN_USERNAME']) { $cfg['DB_ADMIN_USERNAME'] } else { $cfg['DB_USERNAME'] }
    $dumpPass = if ($cfg['DB_ADMIN_USERNAME']) { $cfg['DB_ADMIN_PASSWORD'] } else { $cfg['DB_PASSWORD'] }
    Set-Content -Path $options -Encoding ASCII -Value @(
        '[client]',
        "user=$dumpUser",
        "password=""$dumpPass""",
        "host=$hostName",
        "port=$port"
    )

    & $MysqlDump "--defaults-extra-file=$options" --single-transaction --routines --no-tablespaces --default-character-set=utf8mb4 "--result-file=$sql" $cfg['DB_DATABASE']
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }
    if (-not (Test-Path $sql) -or (Get-Item $sql).Length -lt 1024) { throw 'The database dump is missing or too small.' }

    $items = @($sql)
    $private = Join-Path $ProjectDir 'storage\app\private'
    if (Test-Path $private) { $items += $private }

    $zip = Join-Path $OutDir "sies-backup-$stamp.zip"
    Compress-Archive -Path $items -DestinationPath $zip -Force

    Get-ChildItem -Path $OutDir -Filter 'sies-backup-*.zip' |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) } |
        Remove-Item -Force

    Write-Log ("OK    {0} ({1:N1} MB)" -f $zip, ((Get-Item $zip).Length / 1MB))
    Write-Output "Backup saved: $zip"
}
catch {
    Write-Log ("FAILED  " + $_.Exception.Message)
    Write-Error $_.Exception.Message
    exit 1
}
finally {
    if (Test-Path $work) { Remove-Item -Recurse -Force $work }
}
