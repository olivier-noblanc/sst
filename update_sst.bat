@echo off
REM ============================================================
REM update_sst.bat — Mise à jour de l'application SST
REM 
REM Ce script effectue un git pull pour récupérer les dernières
REM modifications depuis le dépôt GitHub, puis relance IIS.
REM
REM Emplacement : C:\inetpub\sst\update_sst.bat
REM Utilisation : clic droit "Exécuter en tant qu'administrateur"
REM
REM Prérequis :
REM   - Git configuré avec le proxy Kerberos :
REM     git config --global http.proxy http://PROXY:PORT
REM     git config --global http.proxyAuthMethod negotiate
REM   - Remote configurée avec un PAT valide :
REM     git remote set-url origin https://LOGIN:PAT@github.com/olivier-noblanc/sst.git
REM ============================================================

echo ============================================
echo  Mise a jour SST - %date% %time%
echo ============================================
echo.

cd /d C:\inetpub\sst

echo [1/4] Verification du proxy Git...
git config --global http.proxyAuthMethod negotiate >nul 2>&1
if errorlevel 1 (
    echo  ERREUR : proxyAuthMethod non configure.
    echo  Executer : git config --global http.proxyAuthMethod negotiate
    pause
    exit /b 1
)

echo [2/4] Telechargement des mises a jour...
git pull origin main
if errorlevel 1 (
    echo.
    echo  ERREUR : git pull a echoue.
    echo  Causes possibles :
    echo   - Token GitHub expire (password auth not supported)
    echo     ^> git remote set-url origin https://LOGIN:NOUVEAU_PAT@github.com/olivier-noblanc/sst.git
    echo   - Proxy non configure (failed to connect port 443)
    echo     ^> git config --global http.proxy http://PROXY:PORT
    echo   - Repo local diverge (conflits)
    echo     ^> git stash puis relancer ce script
    pause
    exit /b 1
)

echo [3/4] Installation des dependances Composer...
if exist composer.json (
    php composer.phar install --no-dev --optimize-autoloader 2>nul
    if errorlevel 1 (
        composer install --no-dev --optimize-autoloader 2>nul
    )
)

echo [4/4] Redemarrage IIS...
iisreset /restart

echo.
echo ============================================
echo  Mise a jour terminee avec succes !
echo ============================================
pause
