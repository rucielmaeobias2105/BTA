#Requires -Version 5.1
<#
    Keeps the local dev site reachable at http://localhost:8000.

    Started detached by dev-up.bat, so it survives closing the console
    window. Every few seconds it checks that:

      1. MySQL is answering on the DB_PORT from .env
      2. something is listening on the app port
      3. the app actually answers an HTTP request

    If any of those fail it repairs it, which is what stops Firefox from
    reporting "can't connect to the server at localhost:8000".

    Logs to storage/logs/dev-supervisor.log and storage/logs/serve.log.
#>
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [int]$CheckSeconds = 5
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

$logFile = Join-Path $root 'storage\logs\dev-supervisor.log'
$serveLog = Join-Path $root 'storage\logs\serve.log'
$pidFile = Join-Path $root 'storage\dev-supervisor.pid'
$stopFile = Join-Path $root 'storage\dev-supervisor.stop'
$readyFile = Join-Path $root 'storage\dev-server.ready'

New-Item -ItemType Directory -Force -Path (Join-Path $root 'storage\logs') | Out-Null

# ---------------------------------------------------------------- helpers --

function Write-Log {
    param([string]$Message)
    $line = '[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8
}

function Get-EnvValue {
    param([string]$Key, [string]$Default = '')

    $envFile = Join-Path $root '.env'
    if (-not (Test-Path -LiteralPath $envFile)) { return $Default }

    foreach ($raw in [System.IO.File]::ReadAllLines($envFile)) {
        $line = $raw.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { continue }

        $split = $line.IndexOf('=')
        if ($split -lt 1) { continue }
        if ($line.Substring(0, $split).Trim() -ne $Key) { continue }

        $value = $line.Substring($split + 1).Trim().Trim('"').Trim("'")
        if ($value -eq '') { return $Default }
        return $value
    }

    return $Default
}

function Get-XamppBin {
    if ($env:XAMPP -and (Test-Path -LiteralPath $env:XAMPP)) { return $env:XAMPP }
    if (Test-Path -LiteralPath 'C:\xampp') { return 'C:\xampp' }
    return $null
}

function Get-MySqlClient {
    $bin = Get-XamppBin
    if (-not $bin) { return $null }

    $exe = Join-Path $bin 'mysql\bin\mysql.exe'
    if (Test-Path -LiteralPath $exe) { return $exe }

    return $null
}

function Get-PhpBinary {
    $onPath = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }

    $bin = Get-XamppBin
    if ($bin) {
        $exe = Join-Path $bin 'php\php.exe'
        if (Test-Path -LiteralPath $exe) { return $exe }
    }

    return $null
}

function Test-MySql {
    param([int]$DbPort, [string]$User, [string]$Password)

    $client = Get-MySqlClient
    if (-not $client) { return $false }

    # --connect-timeout matters: the damaged XAMPP instance on 3306 accepts
    # the TCP connection and then never sends its handshake, so a plain
    # connect test would call that "up".
    $arguments = @('-u', $User, '-P', "$DbPort", '-h', '127.0.0.1', '--connect-timeout=4', '-N', '-e', 'SELECT 1')
    if ($Password) { $arguments += "-p$Password" }

    $null = & $client @arguments 2>&1
    return ($LASTEXITCODE -eq 0)
}

function Test-Port {
    param([string]$Address = '127.0.0.1', [int]$TargetPort)

    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $pending = $client.BeginConnect($Address, $TargetPort, $null, $null)
        if (-not $pending.AsyncWaitHandle.WaitOne(2000)) { return $false }
        $client.EndConnect($pending)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Test-Http {
    param([string]$Url, [int]$TimeoutSeconds = 30)

    try {
        $request = [System.Net.HttpWebRequest]::Create($Url)
        $request.Method = 'GET'
        $request.Timeout = $TimeoutSeconds * 1000
        $request.ReadWriteTimeout = $TimeoutSeconds * 1000
        $request.UserAgent = 'bta-dev-supervisor'
        $request.AllowAutoRedirect = $true

        $response = $request.GetResponse()
        $code = [int]$response.StatusCode
        $response.Close()

        return ($code -ge 200 -and $code -lt 400)
    } catch {
        return $false
    }
}

function Start-MySql {
    param([int]$DbPort)

    $bat = Join-Path $root 'tools\start-mysql-isolated.bat'
    if (-not (Test-Path -LiteralPath $bat)) {
        Write-Log "MySQL is down on $DbPort and tools\start-mysql-isolated.bat is missing."
        return $false
    }

    Write-Log "MySQL is not answering on port $DbPort - starting the isolated instance."
    $process = Start-Process -FilePath 'cmd.exe' `
        -ArgumentList '/c', ('"{0}" {1}' -f $bat, $DbPort) `
        -WorkingDirectory $root -WindowStyle Hidden -Wait -PassThru

    return ($process.ExitCode -eq 0)
}

function Ensure-Database {
    param([int]$DbPort, [string]$User, [string]$Password, [string]$Name)

    $client = Get-MySqlClient
    if (-not $client) {
        Write-Log 'mysql.exe not found - skipping the create-database step.'
        return
    }

    $arguments = @('-u', $User, '-P', "$DbPort", '-h', '127.0.0.1', '-e',
        "CREATE DATABASE IF NOT EXISTS $Name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    if ($Password) { $arguments += "-p$Password" }

    $null = & $client @arguments 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Log "Could not create the database '$Name'." }
}

function Invoke-Artisan {
    param([string[]]$ArtisanArgs)

    $php = Get-PhpBinary
    if (-not $php) {
        Write-Log 'php.exe not found on PATH or under XAMPP.'
        return 1
    }

    $output = & $php @ArtisanArgs 2>&1 | Out-String
    if ($output.Trim()) { Write-Log ($output.Trim()) }

    return $LASTEXITCODE
}

function Stop-StaleAppServers {
    param([int]$TargetPort)

    # Matches both `php artisan serve` and the `php -S 127.0.0.1:8000` child
    # it spawns. Anything else holding the port is left alone but reported.
    $pattern = 'artisan serve|:{0}(?!\d)' -f $TargetPort
    $stale = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -and $_.CommandLine -match $pattern }

    foreach ($process in $stale) {
        Write-Log "Stopping stale dev server (PID $($process.ProcessId))."
        Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    }

    Start-Sleep -Milliseconds 700

    $holders = Get-NetTCPConnection -LocalPort $TargetPort -State Listen -ErrorAction SilentlyContinue
    foreach ($holder in $holders) {
        Write-Log "Port $TargetPort is still held by PID $($holder.OwningProcess), which is not a Laravel dev server."
    }
}

function Start-AppServer {
    $php = Get-PhpBinary
    if (-not $php) {
        Write-Log 'php.exe not found on PATH or under XAMPP - cannot serve the app.'
        return
    }

    # --port is passed explicitly on purpose: without it `artisan serve`
    # silently retries on 8001, 8002, ... when the port is busy, which leaves
    # http://localhost:8000 dead while the app runs somewhere else.
    $payload = '"{0}" artisan serve --host=127.0.0.1 --port={1} >> "{2}" 2>&1' -f $php, $Port, $serveLog
    Add-Content -LiteralPath $serveLog `
        -Value ('{0} ===== dev server starting =====' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')) -Encoding UTF8

    Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', ('"{0}"' -f $payload) `
        -WorkingDirectory $root -WindowStyle Hidden | Out-Null

    # Booting Laravel's dev server is slow on this machine - it can take the
    # better part of a minute before it accepts its first connection.
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Seconds 1
        if (Test-Port -TargetPort $Port) {
            Write-Log "Dev server is listening on http://localhost:$Port"
            return
        }
    }

    Write-Log "Dev server did not start listening on port $Port within 60s - see storage/logs/serve.log."
}

# ------------------------------------------------------------------ start --

if (Test-Path -LiteralPath $stopFile) { Remove-Item -LiteralPath $stopFile -Force }
Set-Content -LiteralPath $pidFile -Value $PID -Encoding ASCII

# A ready marker from a previous run is not evidence about this run. It is only
# written again after a successful HTTP check below, and dev-up.bat clears it
# before it waits, so a stale one can never be mistaken for a live site.
Remove-Item -LiteralPath $readyFile -Force -ErrorAction SilentlyContinue

Write-Log "Supervisor started (PID $PID), watching http://localhost:$Port"

$dbPort = [int](Get-EnvValue -Key 'DB_PORT' -Default '3306')
$dbUser = Get-EnvValue -Key 'DB_USERNAME' -Default 'root'
$dbPassword = Get-EnvValue -Key 'DB_PASSWORD' -Default ''
$dbName = Get-EnvValue -Key 'DB_DATABASE' -Default 'bta'

if (-not (Test-MySql -DbPort $dbPort -User $dbUser -Password $dbPassword)) {
    if (Start-MySql -DbPort $dbPort) {
        Write-Log "MySQL is up on port $dbPort."
    }
}

if (Test-MySql -DbPort $dbPort -User $dbUser -Password $dbPassword) {
    Ensure-Database -DbPort $dbPort -User $dbUser -Password $dbPassword -Name $dbName
    $null = Invoke-Artisan -ArtisanArgs @('artisan', 'optimize:clear', '-q')
    $null = Invoke-Artisan -ArtisanArgs @('artisan', 'migrate', '--force')
} else {
    Write-Log "MySQL is still unreachable on port $dbPort. Pages will fail until it is up."
}

if (-not (Test-Port -TargetPort $Port)) {
    Start-AppServer
}

# ------------------------------------------------------------------- loop --

$healthFailures = 0
$databaseCheckedAt = Get-Date
$serverStartedAt = Get-Date
$serverHasAnswered = $false

while ($true) {
    if (Test-Path -LiteralPath $stopFile) {
        Write-Log 'Stop flag found - supervisor exiting.'
        break
    }

    if (((Get-Date) - $databaseCheckedAt).TotalSeconds -ge 60) {
        $databaseCheckedAt = Get-Date
        if (-not (Test-MySql -DbPort $dbPort -User $dbUser -Password $dbPassword)) {
            Write-Log "Lost the connection to MySQL on port $dbPort."
            $null = Start-MySql -DbPort $dbPort
        }
    }

    if (-not (Test-Port -TargetPort $Port)) {
        Write-Log "Nothing is listening on port $Port - starting the dev server."
        Remove-Item -LiteralPath $readyFile -Force -ErrorAction SilentlyContinue
        Stop-StaleAppServers -TargetPort $Port
        Start-AppServer
        $serverStartedAt = Get-Date
        $serverHasAnswered = $false
        $healthFailures = 0
        Start-Sleep -Seconds $CheckSeconds
        continue
    }

    # Probing starts immediately so the ready marker and the health state stay
    # truthful. The boot grace period only gates *restarts*: the first request
    # after a boot compiles every view and config, so it can take the best part
    # of a minute, and judging the server on that would kill a healthy boot.
    $bootGraceOver = ((Get-Date) - $serverStartedAt).TotalSeconds -ge 120

    if (Test-Http -Url ("http://127.0.0.1:{0}/" -f $Port)) {
        $healthFailures = 0
        $serverHasAnswered = $true

        # dev-up.bat waits for this file, so it only opens the browser once
        # the site really answers.
        if (-not (Test-Path -LiteralPath $readyFile)) {
            Set-Content -LiteralPath $readyFile -Value (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') -Encoding ASCII
            Write-Log "http://localhost:$Port is answering - ready."
        }
    } else {
        $healthFailures++

        if ($healthFailures -ge 2 -and ($serverHasAnswered -or $bootGraceOver)) {
            # A wedged request blocks PHP's single-threaded dev server, so
            # every later request - including Firefox's - just hangs.
            if (-not (Test-MySql -DbPort $dbPort -User $dbUser -Password $dbPassword)) {
                Write-Log 'The app is not answering and MySQL is down - restarting MySQL only.'
                $null = Start-MySql -DbPort $dbPort
            } else {
                Write-Log 'The app did not answer twice in a row - restarting the dev server.'
                Remove-Item -LiteralPath $readyFile -Force -ErrorAction SilentlyContinue
                Stop-StaleAppServers -TargetPort $Port
                Start-AppServer
                $serverStartedAt = Get-Date
                $serverHasAnswered = $false
            }

            $healthFailures = 0
            Start-Sleep -Seconds $CheckSeconds
        } elseif ($healthFailures -eq 2) {
            Write-Log 'The app is not answering yet, but it is still inside the boot grace period - not restarting.'
        }
    }

    Start-Sleep -Seconds $CheckSeconds
}

if (Test-Path -LiteralPath $pidFile) { Remove-Item -LiteralPath $pidFile -Force }
if (Test-Path -LiteralPath $readyFile) { Remove-Item -LiteralPath $readyFile -Force }
