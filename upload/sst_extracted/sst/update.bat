@echo off
REM ============================================================
REM  Mise a jour de l'application SST depuis GitHub
REM  A executer sur le serveur IIS (Windows Server)
REM
REM  Usage : update.bat
REM  Le script git pull le code, sauvegarde la base et relance
REM ============================================================

setlocal enabledelayedexpansion

REM --- Configuration ---
set "APP_DIR=C:\inetpub\wwwroot\sst"
set "GIT_REPO=https://github.com/olivier-noblanc/sst.git"
set "BACKUP_DIR=C:\inetpub\wwwroot\sst\backups"
set "DB_FILE=%APP_DIR%\data\sst.db"

echo.
echo ============================================
echo   Mise a jour Application SST
echo   %date% %time%
echo ============================================
echo.

REM --- Verifier que git est installe ---
where git >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERREUR] Git n'est pas installe ou pas dans le PATH.
    echo Installez Git pour Windows : https://git-scm.com/download/win
    pause
    exit /b 1
)

REM --- Verifier que le repertoire existe ---
if not exist "%APP_DIR%" (
    echo [INFO] Repertoire %APP_DIR% introuvable.
    echo [INFO] Clonage initial depuis GitHub...
    git clone "%GIT_REPO%" "%APP_DIR%"
    if %ERRORLEVEL% neq 0 (
        echo [ERREUR] Echec du clone. Verifiez l'acces reseau et le depot.
        pause
        exit /b 1
    )
    echo [OK] Clone termine avec succes.
    echo.
    echo [IMPORTANT] Pensez a configurer :
    echo   1. src\config.php  =^> APP_ENV = 'prod'
    echo   2. Permissions IIS_IUSRS sur data\
    echo   3. Site IIS pointant vers %APP_DIR%\public\
    echo.
    pause
    exit /b 0
)

REM --- Se placer dans le repertoire ---
cd /d "%APP_DIR%"

REM --- Verifier que c'est un depot git ---
if not exist ".git" (
    echo [ERREUR] Le repertoire %APP_DIR% n'est pas un depot git.
    echo [INFO] Initialisation et lien avec GitHub...
    git init
    git remote add origin "%GIT_REPO%"
    git fetch origin
    git reset --hard origin/main
    if %ERRORLEVEL% neq 0 (
        echo [ERREUR] Echec de l'initialisation.
        pause
        exit /b 1
    )
    echo [OK] Depot initialise et synchronise.
    pause
    exit /b 0
)

REM --- Sauvegarder la base de donnees ---
if exist "%DB_FILE%" (
    if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
    for /f "tokens=2 delims==" %%a in ('wmic os get localdatetime /value ^| find "="') do set "DT=%%a"
    set "TIMESTAMP=!DT:~0,8!_!DT:~8,6!"
    set "BACKUP_FILE=%BACKUP_DIR%\sst_!TIMESTAMP!.db"
    echo [SAUVEGARDE] Base de donnees...
    copy "%DB_FILE%" "!BACKUP_FILE!" >nul 2>&1
    if !ERRORLEVEL! equ 0 (
        echo [OK] Sauvegarde : !BACKUP_FILE!
    ) else (
        echo [ATTENTION] Sauvegarde echouee - poursuite quand meme.
    )
) else (
    echo [INFO] Aucune base de donnees existante a sauvegarder.
)

REM --- Afficher la version actuelle ---
echo.
echo [VERSION ACTUELLE]
git log --oneline -1 2>nul
echo.

REM --- Recuperer les modifications ---
echo [MISE A JOUR] Telechargement depuis GitHub...
git fetch origin main
if %ERRORLEVEL% neq 0 (
    echo [ERREUR] Impossible de contacter GitHub. Verifiez la connexion reseau.
    pause
    exit /b 1
)

REM --- Verifier s'il y a des changements ---
git diff --quiet HEAD origin/main 2>nul
if %ERRORLEVEL% equ 0 (
    echo.
    echo [OK] L'application est deja a jour. Aucune modification a appliquer.
    pause
    exit /b 0
)

REM --- Afficher les changements a venir ---
echo.
echo [CHANGEMENTS A APPLIQUER]
git log --oneline HEAD..origin/main
echo.

REM --- Demander confirmation ---
set /p "CONFIRM=Appliquer la mise a jour ? (O/n) : "
if /i "!CONFIRM!"=="n" (
    echo [ANNULE] Mise a jour annulee par l'utilisateur.
    pause
    exit /b 0
)

REM --- Stasher les eventuelles modifications locales (config.php etc.) ---
git stash --include-untracked 2>nul

REM --- Appliquer la mise a jour ---
echo [MISE A JOUR] Application des modifications...
git reset --hard origin/main
if %ERRORLEVEL% neq 0 (
    echo [ERREUR] Echec de la mise a jour. Restauration...
    git reset --hard HEAD
    git stash pop 2>nul
    pause
    exit /b 1
)

REM --- Restaurer les modifications locales ---
git stash pop 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ATTENTION] Conflit de fusion avec vos modifications locales.
    echo              Verifiez manuellement les fichiers en conflit.
    echo              Votre sauvegarde de base est disponible dans backups\
)

echo.
echo [OK] Mise a jour appliquee avec succes !
echo.
echo [NOUVELLE VERSION]
git log --oneline -1
echo.

REM --- Verifier les permissions sur data\ ---
if exist "%APP_DIR%\data" (
    echo [VERIFICATION] Permissions data\...
    icacls "%APP_DIR%\data" | findstr /i "IIS_IUSRS" >nul 2>&1
    if !ERRORLEVEL! neq 0 (
        echo [ATTENTION] IIS_IUSRS n'a pas les permissions sur data\
        echo              Executez : icacls "%APP_DIR%\data" /grant IIS_IUSRS:^(OI^)^(CI^)M
    )
)

REM --- Proposer de relancer IIS ---
echo.
set /p "IIS_RESET=Relancer IIS pour appliquer les changements ? (O/n) : "
if /i not "!IIS_RESET!"=="n" (
    echo [IIS] Redemarrage de IIS...
    iisreset
    echo [OK] IIS redemarre.
)

echo.
echo ============================================
echo   Mise a jour terminee
echo ============================================
echo.
pause
exit /b 0
