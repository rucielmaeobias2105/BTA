@echo off
REM ============================================================
REM  Starts the always-on dev server for Balai ti Arjud.
REM
REM  The supervisor runs detached from this window, so closing
REM  this console does NOT take the site down - http://localhost:8000
REM  stays reachable, and it is restarted automatically if it dies.
REM
REM  Usage:  dev-up.bat
REM  Stop it with:  dev-down.bat
REM ============================================================

setlocal enabledelayedexpansion
cd /d "%~dp0"

set PORT=8000
set SUPERVISOR=%CD%\tools\dev-supervisor.ps1
set PIDFILE=%CD%\storage\dev-supervisor.pid
set READYFILE=%CD%\storage\dev-server.ready

echo.
echo  ==========================================================
echo   Balai ti Arjud - starting the always-on dev server
echo  ==========================================================
echo.

REM ------------------------------------------------------------
REM  1. Already running?
REM ------------------------------------------------------------
if exist "%PIDFILE%" (
    set /p RUNPID=<"%PIDFILE%"
    REM findstr, not find: Git Bash ships a GNU find.exe that shadows the
    REM Windows find.exe on the PATH, which breaks "tasklist | find" when
    REM this script is launched from a Git Bash / MSYS shell.
    tasklist /fi "PID eq !RUNPID!" 2>nul | findstr /c:"!RUNPID!" >nul
    if not errorlevel 1 (
        echo  The supervisor is already running ^(PID !RUNPID!^).
        goto :wait
    )
    echo  Removing a stale PID file ...
    del /q "%PIDFILE%" >nul 2>&1
)

REM ------------------------------------------------------------
REM  2. Launch the supervisor, detached and hidden
REM ------------------------------------------------------------
echo [1/3] Starting the supervisor ...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Start-Process -FilePath 'powershell.exe' -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File','%SUPERVISOR%' -WorkingDirectory '%CD%' -WindowStyle Hidden" >nul 2>&1

if errorlevel 1 (
    echo.
    echo ERROR: could not launch the supervisor. Check that PowerShell works.
    exit /b 1
)

REM ------------------------------------------------------------
REM  3. Wait until the site answers
REM ------------------------------------------------------------
:wait
echo [2/3] Waiting for http://localhost:%PORT% ...

set /a TRIES=0
:waitloop
REM Portable ~1 second delay. Avoids "timeout", which is shadowed by the GNU
REM coreutils build when this script is launched from Git Bash, and avoids
REM %WINDIR%, which is not always inherited.
ping -n 2 127.0.0.1 >nul
set /a TRIES+=1

REM The supervisor drops this marker as soon as the site really answers, so
REM the browser is never opened onto a "can't connect" page.
if exist "%READYFILE%" goto :ready

if !TRIES! GEQ 180 (
    echo.
    echo WARNING: the site is not answering after 3 minutes.
    echo          Read storage\logs\dev-supervisor.log and storage\logs\serve.log.
    echo.
    exit /b 1
)
goto :waitloop

:ready
echo       The site is up.
goto :open

REM ------------------------------------------------------------
REM  4. Open the browser
REM ------------------------------------------------------------
:open
echo.
echo [3/3] Opening your browser ...
start "" "http://localhost:%PORT%"

echo.
echo  ==========================================================
echo   Running in the background. You can close this window.
echo.
echo   Port %PORT% server   http://localhost:%PORT%
echo   XAMPP / Apache      http://localhost/BTA/public/
echo.
echo   Admin panel         http://localhost:%PORT%/admin/login
echo                       http://localhost/BTA/public/admin/login
echo.
echo   Admin login     username: admin   password: password
echo   Customer login  email: juan@example.test   password: password
echo.
echo   Stop the server with:  dev-down.bat
echo   Follow the logs with:  storage\logs\dev-supervisor.log
echo  ==========================================================
echo.
exit /b 0
