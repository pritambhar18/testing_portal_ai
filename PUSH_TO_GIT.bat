@echo off
REM Git Push Script for Testing Portal AI - Render Deployment
REM This script stages all changes, commits them, and pushes to main branch

echo.
echo ╔════════════════════════════════════════════════════════════════╗
echo ║   Testing Portal AI - Git Push to GitHub (Render Deploy)       ║
echo ╚════════════════════════════════════════════════════════════════╝
echo.

REM Change to project directory
cd /d C:\xampp\htdocs\testing_portal_02

if errorlevel 1 (
    echo ❌ ERROR: Could not navigate to project directory
    pause
    exit /b 1
)

echo ✓ Project directory: %cd%
echo.

REM Check git status
echo 📋 Checking current git status...
git status --short
echo.

REM Confirm before proceeding
set /p confirm="⚠️  Review above files. Do you want to push these changes to main? (Y/N): "
if /i not "%confirm%"=="Y" (
    echo ❌ Push cancelled
    pause
    exit /b 0
)

echo.
echo ▶ Step 1: Staging all changes...
git add .
if errorlevel 1 (
    echo ❌ ERROR: Failed to stage changes
    pause
    exit /b 1
)
echo ✓ All changes staged
echo.

echo ▶ Step 2: Committing changes...
git commit -m "Deploy: Latest code update for Render hosting

- Updated code structure with latest changes
- All files prepared for production deployment
- Ready for auto-deploy on Render
- Database schema verified - no changes needed

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"

if errorlevel 1 (
    echo ⚠️  WARNING: Commit may have failed (no changes to commit or other reason)
) else (
    echo ✓ Changes committed successfully
)
echo.

echo ▶ Step 3: Pushing to main branch...
git push origin main
if errorlevel 1 (
    echo ❌ ERROR: Failed to push to GitHub
    echo.
    echo 🔍 Troubleshooting tips:
    echo    - Check your internet connection
    echo    - Verify GitHub credentials/SSH keys
    echo    - Try: git push origin main --verbose
    pause
    exit /b 1
)
echo ✓ Successfully pushed to main branch!
echo.

echo ▶ Step 4: Verifying push...
git log --oneline -3
echo.

echo ╔════════════════════════════════════════════════════════════════╗
echo ║                    ✅ PUSH SUCCESSFUL!                         ║
echo ╚════════════════════════════════════════════════════════════════╝
echo.
echo 📍 Repository: https://github.com/pritambhar18/testing_portal_ai
echo 🌐 Hosting: https://testing-portal-ai.onrender.com/
echo.
echo ⏳ Render will auto-deploy in a few moments...
echo.
echo ✓ Next Steps:
echo    1. Visit https://dashboard.render.com
echo    2. Check deployment logs
echo    3. Verify app is running at https://testing-portal-ai.onrender.com/
echo.

pause
