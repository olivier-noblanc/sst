@echo off
REM ============================================================
REM update_sst.bat — Mise a jour de l'application SST
REM
REM Telecharge la derniere version depuis GitHub et ecrase les
REM fichiers locaux (pas de conflits possibles).
REM La base SQLite dans data\ est preservee (.gitignore).
REM
REM Emplacement : C:\inetpub\sst\update_sst.bat
REM Utilisation : clic droit "Executer en tant qu'administrateur"
REM
REM Prerequis :
REM   - Git configure avec le proxy Kerberos :
REM     git config --global http.proxy http://PROXY:PORT
REM     git config --global http.proxyAuthMethod negotiate
REM   - Remote configuree avec un PAT valide :
REM     git remote set-url origin https://LOGIN:PAT@github.com/olivier-noblanc/sst.git
REM ============================================================

echo ============================================
echo  Mise a jour SST - %date% %time%
echo ============================================
echo.

cd /d C:\inetpub\sst

echo [1/3] Verification du proxy Git...
git config --global http.proxyAuthMethod negotiate >nul 2>&1
if errorlevel 1 (
    echo  ERREUR : proxyAuthMethod non configure.
    echo  Executer : git config --global http.proxyAuthMethod negotiate
    pause
    exit /b 1
)

echo [2/3] Telechargement et synchronisation (force)...
git fetch origin main
if errorlevel 1 (
    echo.
    echo  ERREUR : git fetch a echoue.
    echo  Causes possibles :
    echo   - Token GitHub expire (password auth not supported)
    echo     ^> git remote set-url origin https://LOGIN:NOUVEAU_PAT@github.com/olivier-noblanc/sst.git
    echo   - Proxy non configure (failed to connect port 443)
    echo     ^> git config --global http.proxy http://PROXY:PORT
    pause
    exit /b 1
)

git reset --hard origin/main
git clean -fd

echo [3/3] Redemarrage IIS...
iisreset /restart

echo.
echo ============================================
echo  Mise a jour terminee avec succes !
echo ============================================
pause
