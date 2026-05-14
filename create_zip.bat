@echo off
setlocal enabledelayedexpansion

set SOURCE=C:\xampp\htdocs\testing_portal
set STAGING=C:\xampp\htdocs\testing_portal_for_hosting
set OUTPUT=C:\xampp\htdocs\testing_portal_hosting.zip

echo.
echo ========================================
echo Creating cPanel Hosting Package
echo ========================================
echo.

REM Remove existing
if exist "%STAGING%" rmdir /s /q "%STAGING%"
if exist "%OUTPUT%" del /q "%OUTPUT%"

REM Create staging directory
mkdir "%STAGING%"

REM Copy production folders
echo Copying production folders...
for %%F in (admin api actions assets config helpers reports lib security frontend) do (
    if exist "%SOURCE%\%%F\" (
        xcopy "%SOURCE%\%%F\*" "%STAGING%\%%F\" /e /i /y >nul
        echo   + %%F
    )
)

REM Copy root files
echo Copying root files...
if exist "%SOURCE%\index.php" copy "%SOURCE%\index.php" "%STAGING%\" /y >nul && echo   + index.php
if exist "%SOURCE%\database_schema.sql" copy "%SOURCE%\database_schema.sql" "%STAGING%\" /y >nul && echo   + database_schema.sql

REM Create uploads directories
echo Creating uploads directories...
mkdir "%STAGING%\uploads\reports" 2>nul
mkdir "%STAGING%\uploads\order_flow_reports" 2>nul
mkdir "%STAGING%\uploads\order_flow_stops" 2>nul
echo   + uploads/reports
echo   + uploads/order_flow_reports
echo   + uploads/order_flow_stops

REM Create .htaccess
echo Creating .htaccess...
(
echo # Disable directory listing
echo Options -Indexes
echo.
echo # Enable mod_rewrite
echo ^<IfModule mod_rewrite.c^>
echo     RewriteEngine On
echo     RewriteBase /
echo ^</IfModule^>
) > "%STAGING%\.htaccess"
echo   + .htaccess

REM Create README
echo Creating README_DEPLOYMENT.md...
(
echo # Testing Portal - cPanel Deployment Guide
echo.
echo ## Quick Setup:
echo.
echo ### 1. Upload ^& Extract
echo - Extract this zip to your cPanel public_html directory
echo.
echo ### 2. Database Setup
echo - Create database: testing_portal
echo - Import database_schema.sql via phpMyAdmin in cPanel
echo.
echo ### 3. Configure Database Connection
echo - Edit: config/db.php
echo - Update variables with your cPanel credentials:
echo   - $db_host = your database host
echo   - $db_user = your database username
echo   - $db_pass = your database password
echo   - $db_name = 'testing_portal'
echo.
echo ### 4. Set Permissions
echo - chmod 755 uploads/
echo - chmod 755 uploads/reports/
echo.
echo ### 5. Access the Application
echo - Navigate to: yourdomain.com/admin/login.php
echo.
echo ## File Structure:
echo - admin/ - Admin dashboard and login
echo - api/ - API endpoints
echo - actions/ - Form action handlers
echo - config/ - Configuration files
echo - helpers/ - PHP utility classes
echo - assets/ - CSS, JavaScript, Images
echo - reports/ - Report generation
echo - uploads/ - Generated reports storage
echo.
echo ## Troubleshooting:
echo - Database Connection Error: Check config/db.php credentials
echo - Permission Denied: Run chmod commands on uploads/ directories
echo - Table Creation Failed: Run database_schema.sql in phpMyAdmin
echo.
) > "%STAGING%\README_DEPLOYMENT.md"
echo   + README_DEPLOYMENT.md

REM Create setup helper
echo Creating setup-helper.php...
(
echo ^<?php
echo /**
echo  * cPanel Setup Helper
echo  */
echo echo '^<h1^>Testing Portal - Setup Complete^</h1^>';
echo echo '^<p^>Database configured. ^<a href="admin/login.php"^>Go to Login^</a^>^</p^>';
echo ?^>
) > "%STAGING%\setup-helper.php"
echo   + setup-helper.php

echo.
echo Creating ZIP file...
echo   Location: %OUTPUT%

REM Use PowerShell to create zip (more reliable)
powershell -NoProfile -Command "Add-Type -AssemblyName 'System.IO.Compression.FileSystem'; [System.IO.Compression.ZipFile]::CreateFromDirectory('%STAGING%', '%OUTPUT%')" 2>nul

if exist "%OUTPUT%" (
    echo.
    echo ========================================
    echo Success!
    echo ========================================
    for /f %%A in ('powershell -NoProfile -Command "(Get-Item '%OUTPUT%').Length / 1MB"') do set SIZE=%%A
    echo ZIP File: %OUTPUT%
    echo Size: approximately !SIZE! MB
    echo.
    echo Ready for cPanel upload!
    echo.
    REM Cleanup
    rmdir /s /q "%STAGING%"
    echo Staging directory cleaned up.
) else (
    echo ERROR: ZIP file creation failed
    pause
)

endlocal
