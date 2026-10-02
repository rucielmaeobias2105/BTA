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
set WAITER=%CD%\tools\wait-for-site.ps1
set PIDFILE=%CD%\storage\dev-supervisor.pid
set READYFILE=%CD%\storage\dev-server.ready

echo.
echo  ==========================================================
echo   Balai ti Arjud - starting the always-on dev server
echo  ==========================================================
echo.

REM The ready marker is a hint, not proof. One left behind by a crash, a
REM force-kill or a reboot survives, and trusting it is exactly what used to
REM open the browser onto a dead port - Firefox would then report "can't
REM connect to 127.0.0.1:8000". It is cleared unconditionally on every launch,
REM so the wait below can only ever be satisfied by a real answer.
del /q "%READYFILE%" >nul 2>&1

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
REM  3. Wait until the site really answers
REM ------------------------------------------------------------
:wait
echo [2/3] Waiting for http://localhost:%PORT% to answer ...

REM This asks the site itself rather than trusting a file on disk, so the
REM browser is only ever opened onto a page that is genuinely up. Run with
REM -File so its exit code reaches errorlevel below; all its detail goes to
REM storage\logs\wait-for-site.log.
powershell -NoProfile -ExecutionPolicy Bypass -File "%WAITER%" -Port %PORT% -TimeoutSeconds 240

if errorlevel 1 (
    echo.
    echo WARNING: http://localhost:%PORT% is still not answering after 4 minutes.
    echo          Something is failing during boot. Read, in this order:
    echo            storage\logs\wait-for-site.log
    echo            storage\logs\dev-supervisor.log
    echo            storage\logs\serve.log
    echo.
    echo          Your browser has deliberately NOT been opened, so it cannot
    echo          land on an error page. MySQL running is the usual suspect -
    echo          check the XAMPP control panel.
    echo.
    exit /b 1
)
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
