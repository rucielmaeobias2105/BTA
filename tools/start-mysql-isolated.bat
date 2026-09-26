@echo off
REM ============================================================
REM  Starts a clean, isolated MariaDB/MySQL instance for this
REM  project WITHOUT touching the XAMPP data folder.
REM
REM  Use this when the default XAMPP MySQL (port 3306) is
REM  damaged - it keeps its data in a separate folder and its
REM  own port, so your existing databases are never at risk.
REM
REM  Usage:  tools\start-mysql-isolated.bat [port]
REM  Default port: 3307
REM ============================================================

setlocal enabledelayedexpansion
cd /d "%~dp0\.."

set PORT=%~1
if "%PORT%"=="" set PORT=3307

if "%XAMPP%"=="" (
    if exist "C:\xampp" (
        set XAMPP=C:\xampp
    ) else (
        echo ERROR: XAMPP environment variable is not set and C:\xampp was not found.
        echo        Set XAMPP to your XAMPP install path, e.g.
        echo            set XAMPP=D:\xampp
        exit /b 1
    )
)

set DATA=%TEMP%\bta-mysql-data
set MYSQLD="%XAMPP%\mysql\bin\mysqld.exe"
set MYSQL="%XAMPP%\mysql\bin\mysql.exe"
set INSTALL="%XAMPP%\mysql\bin\mysql_install_db.exe"

echo.
echo  ==========================================================
echo   Isolated MySQL for Balai ti Arjud
echo  ==========================================================
echo.
echo   Port : %PORT%
echo   Data : %DATA%
echo.

REM --- already running? -------------------------------------------------
"%MYSQLD%" --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: mysqld.exe not found under "%XAMPP%\mysql\bin".
    exit /b 1
)

"%MYSQL%" -u root -P %PORT% -h 127.0.0.1 --connect-timeout=4 -e "SELECT 1;" >nul 2>&1
if not errorlevel 1 (
    echo MySQL is already running on port %PORT%.
    goto :done
)

REM --- first run: initialise the data folder ---------------------------
REM  NB: never put unescaped parentheses in an echo inside a (...) block.
if not exist "%DATA%\mysql" (
    echo [1/2] Initialising a fresh data folder - first run only...
    mkdir "%DATA%" 2>nul
    "%INSTALL%" --datadir="%DATA%" --password= >nul 2>&1
    if errorlevel 1 (
        echo ERROR: could not initialise the data folder at %DATA%
        exit /b 1
    )
    echo       Done.
) else (
    echo [1/2] Reusing the existing data folder.
)

REM --- start the server -------------------------------------------------
REM  Launched via PowerShell Start-Process so it is fully detached and keeps
REM  running after this script exits. Plain "start /b" ties the server's
REM  lifetime to this console and it dies when the window closes.
echo [2/2] Starting mysqld on port %PORT% ...
powershell -NoProfile -Command "Start-Process -FilePath '%MYSQLD%' -ArgumentList '--datadir=\"%DATA%\"','--port=%PORT%','--bind-address=127.0.0.1','--skip-name-resolve' -WindowStyle Minimized -RedirectStandardError '%TEMP%\bta-mysql-%PORT%.log'" >nul 2>&1

REM --- wait for it to accept connections --------------------------------
set /a TRIES=0
:wait
REM Portable ~1 second delay. Avoids "timeout", which is shadowed by the GNU
REM coreutils build when this script is launched from Git Bash, and avoids
REM %WINDIR%, which is not always inherited.
ping -n 2 127.0.0.1 >nul
set /a TRIES+=1
"%MYSQL%" -u root -P %PORT% -h 127.0.0.1 --connect-timeout=3 -e "SELECT 1;" >nul 2>&1
if not errorlevel 1 goto :done
if %TRIES% GEQ 30 (
    echo ERROR: MySQL did not come up within 30 seconds.
    echo        See "%TEMP%\bta-mysql-%PORT%.log" for details.
    exit /b 1
)
goto :wait

:done
echo.
echo  ==========================================================
echo   MySQL is up on port %PORT%
echo.
echo   Set this line in your .env:
echo.
echo       DB_PORT=%PORT%
echo.
echo   Then run:  start-dev.bat
echo  ==========================================================
echo.
exit /b 0
