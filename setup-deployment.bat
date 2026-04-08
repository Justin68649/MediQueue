@echo off
REM Quick setup script for GitHub and Vercel deployment
REM Usage: Run this script from MediQueue root directory

echo.
echo ========================================
echo MediQueue - GitHub & Vercel Quick Setup
echo ========================================
echo.

REM Check if git is installed
git --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Git is not installed or not in PATH
    echo Download from: https://git-scm.com/download/win
    pause
    exit /b 1
)

echo [1/4] Initializing Git repository...
if not exist .git (
    git init
    echo Git initialized!
) else (
    echo Git repository already exists
)

echo.
echo [2/4] Creating .env file from template...
if not exist .env (
    copy .env.example .env
    echo .env created! Please edit it with your database credentials
    echo Location: %cd%\.env
) else (
    echo .env already exists
)

echo.
echo [3/4] Staging all files...
git add .
echo Files staged!

echo.
echo [4/4] Creating initial commit...
git commit -m "Initial commit: MediQueue application" || (
    echo Commit skipped (files may already be committed)
)

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Edit .env with your database credentials
echo 2. Create repository on GitHub (https://github.com/new)
echo 3. Run: git remote add origin https://github.com/YOUR_USERNAME/mediqueue.git
echo 4. Run: git push -u origin main
echo 5. Connect repository to Vercel
echo.
echo For detailed instructions, see DEPLOYMENT_GUIDE.md
echo.
pause
