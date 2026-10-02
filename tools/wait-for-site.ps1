#Requires -Version 5.1
<#
    Blocks until the dev site on $Port really answers an HTTP request.

    The ready marker on disk (storage\dev-server.ready) is only a hint: it can
    survive a crash, a reboot or a force-kill, and a stale marker used to make
    the launcher open the browser onto a dead port - Firefox then showed
    "can't connect to the server at 127.0.0.1:8000". This script ignores the
    marker and checks the real thing, so the browser only ever opens onto a
    site that is up.

    Usage:
        powershell -NoProfile -ExecutionPolicy Bypass -File tools\wait-for-site.ps1 -Port 8000
        powershell -NoProfile -ExecutionPolicy Bypass -File tools\wait-for-site.ps1 -Port 8000 -TimeoutSeconds 240
        powershell -NoProfile -ExecutionPolicy Bypass -File tools\wait-for-site.ps1 -Port 8000 -OpenBrowser

    Exits 0 when the site answered, 1 on timeout. Progress is written to
    storage\logs\wait-for-site.log so a failed launch can be diagnosed after
    the window has closed.
#>
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [int]$TimeoutSeconds = 240,
    [string]$Path = '/',
    [switch]$OpenBrowser
)

$ErrorActionPreference = 'Continue'

$root = Split-Path -Parent $PSScriptRoot
$logFile = Join-Path $root 'storage\logs\wait-for-site.log'
New-Item -ItemType Directory -Force -Path (Join-Path $root 'storage\logs') | Out-Null

function Write-Log {
    param([string]$Message)
    $line = '[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8
}

function Test-Site {
    param([string]$Url, [int]$TimeoutSeconds)

    try {
        [System.Net.ServicePointManager]::Expect100Continue = $false
        $request = [System.Net.HttpWebRequest]::Create($Url)
        $request.Method = 'GET'
        $request.Timeout = $TimeoutSeconds * 1000
        $request.ReadWriteTimeout = $TimeoutSeconds * 1000
        $request.UserAgent = 'bta-wait-for-site'
        $request.AllowAutoRedirect = $true
        $request.KeepAlive = $false

        $response = $request.GetResponse()
        $code = [int]$response.StatusCode
        $response.Close()

        return ($code -ge 200 -and $code -lt 500)
    } catch [System.Net.WebException] {
        # A 4xx/5xx still proves something is serving HTTP on that port, which
        # is all the launcher needs to know. Only a connection-level failure
        # means "not up yet".
        if ($_.Exception.Response) {
            $_.Exception.Response.Close()
            return $true
        }
        return $false
    } catch {
        return $false
    }
}

$url = 'http://127.0.0.1:{0}{1}' -f $Port, $Path

if ($OpenBrowser) {
    Write-Log "Waiting for $url (timeout ${TimeoutSeconds}s), then opening the browser."
} else {
    Write-Log "Waiting for $url (timeout ${TimeoutSeconds}s)."
}

$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
$attempt = 0
$lastReport = 0

while ((Get-Date) -lt $deadline) {
    $attempt++

    # The first request after a boot compiles every view and config on this
    # machine, so allow a generous per-attempt timeout instead of declaring
    # failure on a slow-but-alive server.
    if (Test-Site -Url $url -TimeoutSeconds 25) {
        Write-Log "$url answered after $attempt attempt(s)."

        if ($OpenBrowser) {
            Start-Process ('http://localhost:{0}{1}' -f $Port, $Path)
            Write-Log 'Browser opened.'
        }

        exit 0
    }

    if (($attempt - $lastReport) -ge 10) {
        $lastReport = $attempt
        Write-Log "Still no answer after ~${attempt}s."
        Write-Host ("    ...still booting, {0}s elapsed" -f $attempt) -ForegroundColor DarkGray
    }

    Start-Sleep -Seconds 1
}

Write-Log "GAVE UP: $url did not answer within $TimeoutSeconds s."

if ($OpenBrowser) {
    Write-Host ''
    Write-Host "  The site never came up, so the browser was not opened." -ForegroundColor Yellow
    Write-Host "  See storage\logs\wait-for-site.log and storage\logs\serve.log." -ForegroundColor Yellow
}

exit 1
