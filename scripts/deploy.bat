@echo off
rem Deploys a GitHub branch to its server: staging or production.
rem Usage: scripts\deploy.bat staging^|production   (or double-click deploy-staging.bat / deploy-production.bat)
rem Needs the uphsl SSH alias, or UPHSL_SSH_HOST set to another one (docs\deployment\02-environments.md).
setlocal
set "ENV=%~1"
set "HOST=%UPHSL_SSH_HOST%"
if "%HOST%"=="" set "HOST=uphsl"

if /i not "%ENV%"=="staging" if /i not "%ENV%"=="production" (
  echo Usage: %~nx0 staging^|production
  pause
  exit /b 1
)

cd /d "%~dp0.."
where git >nul 2>nul && git fetch --quiet origin %ENV% 2>nul && git log -1 --format="Deploying to %ENV%: %%h %%s (%%an, %%cr)" origin/%ENV%

if /i "%ENV%"=="production" (
  set /p "ANSWER=This updates the live system. Type production to continue: "
)
if /i "%ENV%"=="production" if /i not "%ANSWER%"=="production" (
  echo Cancelled.
  pause
  exit /b 1
)

ssh -t %HOST% "uphsl-deploy %ENV%"
if errorlevel 1 (
  echo.
  echo Deploy to %ENV% FAILED. Read the messages above.
  pause
  exit /b 1
)
echo.
echo Deploy to %ENV% finished.
pause
