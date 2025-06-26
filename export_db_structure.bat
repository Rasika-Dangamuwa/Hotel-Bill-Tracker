@echo off
REM ================================================================
REM Database Structure Export Script
REM Hotel Bill Tracking System - Nestle Lanka Limited
REM 
REM This script exports the MySQL database structure (schema only)
REM without data for backup and deployment purposes.
REM ================================================================

setlocal enabledelayedexpansion

REM Color codes for better output
set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "BLUE=[94m"
set "NC=[0m"

echo %BLUE%================================================================%NC%
echo %BLUE%          Hotel Bill Tracking System%NC%
echo %BLUE%          Database Structure Export Tool%NC%
echo %BLUE%================================================================%NC%
echo.

REM Database configuration (matching your db.php file)
set DB_HOST=localhost
set DB_NAME=hotel_tracking_system
set DB_USER=root
set DB_PASS=

REM Output directory and filename
set OUTPUT_DIR=database_backups
set TIMESTAMP=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%-%time:~6,2%
set TIMESTAMP=!TIMESTAMP: =0!
set OUTPUT_FILE=!OUTPUT_DIR!\hotel_tracking_structure_!TIMESTAMP!.sql

echo %YELLOW%Database Configuration:%NC%
echo   Host: %DB_HOST%
echo   Database: %DB_NAME%
echo   User: %DB_USER%
echo   Password: %DB_PASS%
echo.

REM Create output directory if it doesn't exist
if not exist "%OUTPUT_DIR%" (
    echo %YELLOW%Creating output directory: %OUTPUT_DIR%%NC%
    mkdir "%OUTPUT_DIR%"
)

REM Check for common MySQL installation paths and set MYSQL_PATH
set MYSQL_PATH=
set MYSQLDUMP_EXE=

echo %YELLOW%Searching for MySQL installation...%NC%

REM Check if mysqldump is in PATH
where mysqldump >nul 2>nul
if %errorlevel% equ 0 (
    set MYSQLDUMP_EXE=mysqldump
    echo %GREEN%Found mysqldump in system PATH%NC%
    goto :mysql_found
)

REM Check common installation paths
set SEARCH_PATHS[0]="C:\Program Files\MySQL\MySQL Server 8.0\bin"
set SEARCH_PATHS[1]="C:\Program Files\MySQL\MySQL Server 8.4\bin"
set SEARCH_PATHS[2]="C:\Program Files\MySQL\MySQL Server 5.7\bin"
set SEARCH_PATHS[3]="C:\xampp\mysql\bin"
set SEARCH_PATHS[4]="C:\wamp64\bin\mysql\mysql8.0.39\bin"
set SEARCH_PATHS[5]="C:\wamp64\bin\mysql\mysql8.0.31\bin"
set SEARCH_PATHS[6]="C:\wamp\bin\mysql\mysql8.0.31\bin"
set SEARCH_PATHS[7]="C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin"

for /L %%i in (0,1,7) do (
    call set "current_path=%%SEARCH_PATHS[%%i]%%"
    if exist !current_path!\mysqldump.exe (
        set MYSQL_PATH=!current_path!
        set MYSQLDUMP_EXE=!current_path!\mysqldump.exe
        echo %GREEN%Found MySQL at: !current_path!%NC%
        goto :mysql_found
    )
)

REM If not found, show manual instructions
echo %RED%ERROR: mysqldump not found in common locations%NC%
echo.
echo %YELLOW%Please locate your MySQL installation and:%NC%
echo %YELLOW%Option 1: Add MySQL bin directory to your Windows PATH%NC%
echo %YELLOW%Option 2: Copy this batch file to your MySQL bin directory%NC%
echo %YELLOW%Option 3: Edit this batch file and set MYSQL_PATH manually%NC%
echo.
echo %YELLOW%Common MySQL locations to check:%NC%
for /L %%i in (0,1,7) do (
    call set "current_path=%%SEARCH_PATHS[%%i]%%"
    echo   !current_path!
)
echo.
echo %YELLOW%To find MySQL installation:%NC%
echo   1. Check Windows Services for MySQL
echo   2. Look in Program Files
echo   3. Check if you're using XAMPP/WAMP/Laragon
echo.
pause
exit /b 1

:mysql_found

echo %YELLOW%Testing database connection...%NC%

REM Test MySQL connection first
if "%DB_PASS%"=="" (
    "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --version >nul 2>connection_test.log
) else (
    "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --password=%DB_PASS% --version >nul 2>connection_test.log
)

if %errorlevel% neq 0 (
    echo %RED%ERROR: Cannot connect to MySQL server%NC%
    echo.
    if exist connection_test.log (
        echo %RED%Connection error details:%NC%
        type connection_test.log
        echo.
    )
    
    echo %YELLOW%Please check:%NC%
    echo   1. MySQL server is running
    echo   2. Database credentials are correct
    echo   3. Database '%DB_NAME%' exists
    echo.
    echo %YELLOW%You can test manually with:%NC%
    if "%MYSQL_PATH%"=="" (
        echo   mysql -u %DB_USER% -p -h %DB_HOST% %DB_NAME%
    ) else (
        echo   "%MYSQL_PATH%\mysql.exe" -u %DB_USER% -p -h %DB_HOST% %DB_NAME%
    )
    echo.
    if exist connection_test.log del connection_test.log
    pause
    exit /b 1
)

echo %GREEN%Database connection successful!%NC%
if exist connection_test.log del connection_test.log

REM Check if database exists
echo %YELLOW%Checking if database exists...%NC%
if "%DB_PASS%"=="" (
    "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --no-data %DB_NAME% >nul 2>db_check.log
) else (
    "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --password=%DB_PASS% --no-data %DB_NAME% >nul 2>db_check.log
)

if %errorlevel% neq 0 (
    echo %RED%ERROR: Database '%DB_NAME%' not found or inaccessible%NC%
    echo.
    if exist db_check.log (
        echo %RED%Error details:%NC%
        type db_check.log
        echo.
    )
    
    echo %YELLOW%Available databases:%NC%
    if "%DB_PASS%"=="" (
        "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% -e "SHOW DATABASES;" 2>nul
    ) else (
        "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --password=%DB_PASS% -e "SHOW DATABASES;" 2>nul
    )
    echo.
    if exist db_check.log del db_check.log
    pause
    exit /b 1
)

echo %GREEN%Database '%DB_NAME%' found!%NC%
if exist db_check.log del db_check.log

echo %YELLOW%Starting database structure export...%NC%
echo.

REM Export database structure only (no data)
if "%DB_PASS%"=="" (
    REM Export without password
    "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --no-data --routines --triggers --single-transaction --lock-tables=false --add-drop-table --create-options --disable-keys --extended-insert --quick --set-charset %DB_NAME% > "%OUTPUT_FILE%" 2>error.log
) else (
    REM Export with password
    "%MYSQLDUMP_EXE%" --host=%DB_HOST% --user=%DB_USER% --password=%DB_PASS% --no-data --routines --triggers --single-transaction --lock-tables=false --add-drop-table --create-options --disable-keys --extended-insert --quick --set-charset %DB_NAME% > "%OUTPUT_FILE%" 2>error.log
)

REM Check if export was successful
if %errorlevel% equ 0 (
    if exist "%OUTPUT_FILE%" (
        echo %GREEN%SUCCESS: Database structure exported successfully!%NC%
        echo %GREEN%Output file: %OUTPUT_FILE%%NC%
        
        REM Get file size
        for %%A in ("%OUTPUT_FILE%") do set FILE_SIZE=%%~zA
        echo %GREEN%File size: !FILE_SIZE! bytes%NC%
        echo.
        
        REM Show first few lines of the exported file
        echo %YELLOW%First few lines of exported file:%NC%
        echo %BLUE%----------------------------------------%NC%
        
        REM Use PowerShell to show first 10 lines (more reliable than head)
        powershell -command "Get-Content '%OUTPUT_FILE%' | Select-Object -First 10"
        
        echo %BLUE%----------------------------------------%NC%
        
        REM Clean up error log if empty
        if exist error.log (
            for %%A in (error.log) do if %%~zA equ 0 del error.log
        )
    ) else (
        echo %RED%ERROR: Export file was not created%NC%
        goto :show_error
    )
) else (
    echo %RED%ERROR: Export failed with error code %errorlevel%%NC%
    goto :show_error
)

echo.
echo %YELLOW%Export Options Used:%NC%
echo   --no-data          : Export structure only (no table data)
echo   --routines         : Include stored procedures and functions
echo   --triggers         : Include triggers
echo   --add-drop-table   : Add DROP TABLE statements
echo   --create-options   : Include table options
echo.

echo %YELLOW%Additional Export Options (create separate bat files if needed):%NC%
echo.
echo %BLUE%For FULL backup (structure + data):%NC%
echo   Remove --no-data flag
echo.
echo %BLUE%For DATA ONLY backup:%NC%
echo   Use --no-create-info --skip-triggers flags
echo.
echo %BLUE%For specific tables only:%NC%
echo   Add table names at end: mysqldump ... database_name table1 table2
echo.

goto :end

:show_error
if exist error.log (
    echo %RED%Error details:%NC%
    type error.log
    echo.
    
    REM Check for common errors and provide solutions
    findstr /i "access denied" error.log >nul
    if !errorlevel! equ 0 (
        echo %YELLOW%SOLUTION: Check your database credentials%NC%
        echo   - Verify username and password
        echo   - Make sure user has SELECT privileges
    )
    
    findstr /i "unknown database" error.log >nul
    if !errorlevel! equ 0 (
        echo %YELLOW%SOLUTION: Database '%DB_NAME%' not found%NC%
        echo   - Check if database name is correct
        echo   - Verify database exists on the server
    )
    
    findstr /i "can't connect" error.log >nul
    if !errorlevel! equ 0 (
        echo %YELLOW%SOLUTION: Cannot connect to MySQL server%NC%
        echo   - Check if MySQL server is running
        echo   - Verify host and port settings
    )
)

echo.
echo %YELLOW%Troubleshooting Steps:%NC%
echo 1. Verify MySQL server is running
echo 2. Check database credentials
echo 3. Ensure mysqldump is in PATH
echo 4. Try running MySQL client first: mysql -u %DB_USER% -p -h %DB_HOST%
echo.

:end
echo %BLUE%================================================================%NC%
echo %BLUE%Export process completed.%NC%
echo %BLUE%================================================================%NC%
echo.

REM Clean up
if exist error.log del error.log

REM Ask if user wants to view the exported file
echo %YELLOW%Would you like to open the output directory? (Y/N)%NC%
set /p OPEN_DIR=
if /i "!OPEN_DIR!"=="Y" (
    if exist "%OUTPUT_DIR%" (
        explorer "%OUTPUT_DIR%"
    )
)

echo.
echo %YELLOW%Press any key to exit...%NC%
pause >nul

endlocal