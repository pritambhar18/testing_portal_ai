@echo off
cd /d "C:\xampp\htdocs\testing_portal"

echo Running Code Structure Verification...
echo.

node verify-structure.js

echo.
echo.
echo Cleanup phase:
echo.

REM Remove Java artifacts
if exist "build" (
  echo Removing Java build artifacts...
  rmdir /s /q build
  echo ✓ Removed: build directory
) else (
  echo - build directory not found
)

if exist "target" (
  echo Removing Maven target artifacts...
  rmdir /s /q target
  echo ✓ Removed: target directory
) else (
  echo - target directory not found
)

echo.
echo ✅ Cleanup Complete!
echo All active code (Node.js/PHP) remains intact.
echo.
pause
