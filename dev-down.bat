@echo off
REM ============================================================
REM  Stops the always-on dev server started by dev-up.bat.
REM
REM  Usage:  dev-down.bat
REM
REM  Only the port-8000 dev server is touched. The MySQL
REM  instance on port 3307 keeps running, and Apache is not
REM  affected, so http://localhost/BTA/public/ stays online.
REM ============================================================

setlocal enabledelayedexpansion
cd /d "%~dp0"

set PORT=8000
set PIDFILE=%CD%\storage\dev-supervisor.pid
set STOPFILE=%CD%\storage\dev-supervisor.stop
set READYFILE=%CD%\storage\dev-server.ready

echo.
echo  ==========================================================
echo   Balai ti Arjud - stopping the dev server on port %PORT%
echo  ==========================================================
echo.

REM Ask the supervisor to exit on its own first, so it tidies up after itself
REM instead of us pulling it out from under itself.
echo [1/3] Asking the supervisor to stop ...
> "%STOPFILE%" echo stop

set RUNPID=
if exist "%PIDFILE%" set /p RUNPID=<"%PIDFILE%"
if not defined RUNPID goto :no_supervisor

REM findstr, not find: Git Bash ships a GNU find.exe that shadows the Windows
REM find.exe on the PATH, which breaks "tasklist | find" when this script is
REM launched from a Git Bash / MSYS shell.
tasklist /fi "PID eq %RUNPID%" 2>nul | findstr /c:"%RUNPID%" >nul
if errorlevel 1 goto :no_supervisor

echo       waiting for the supervisor ^(PID %RUNPID%^) to exit ...
set /a WAITED=0
:wait_supervisor
ping -n 2 127.0.0.1 >nul
tasklist /fi "PID eq %RUNPID%" 2>nul | findstr /c:"%RUNPID%" >nul
if not errorlevel 1 (
    set /a WAITED+=1
    if !WAITED! LSS 15 goto :wait_supervisor
    echo       WARNING: the supervisor did not exit - forcing it.
    taskkill /pid %RUNPID% /f >nul 2>&1
)

:no_supervisor
del /q "%PIDFILE%" >nul 2>&1
del /q "%READYFILE%" >nul 2>&1

REM Kill any dev server that outlived its supervisor. "artisan serve" runs
REM php -S as a child process, so there can be two of them to clean up.
REM
REM The filters use -like wildcards rather than -match regex on purpose: this
REM script runs with delayed expansion enabled, which eats any "!" in the
REM command line. A regex such as ':8000(?!\d)' silently became ':8000(?\d)'
REM and matched nothing.
echo [2/3] Stopping the app server ...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -and ($_.CommandLine -like '*:8000*' -or $_.CommandLine -like '*artisan serve*') } | ForEach-Object { Write-Host ('      stopped PID ' + $_.ProcessId); Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" 2>nul

ping -n 2 127.0.0.1 >nul

echo [3/3] Checking port %PORT% ...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$c = New-Object System.Net.Sockets.TcpClient; try { $c.Connect('127.0.0.1', %PORT%); Write-Host '      WARNING: something is still listening on port %PORT%.' } catch { Write-Host '      port %PORT% is free.' } finally { $c.Close() }"

echo.
echo  Done. Apache is untouched, so http://localhost/BTA/public/ still works.
echo  Start the port-%PORT% server again with:  dev-up.bat
echo.
exit /b 0
