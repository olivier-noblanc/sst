# ============================================================
# update_sst.ps1 — Mise à jour de l'application SST
#
# Ce script effectue :
#   1. Vérification des prérequis (Git, PHP)
#   2. Git pull (via proxy Kerberos)
#   3. Composer install
#   4. Création des dossiers cache + permissions IIS
#   5. Redémarrage IIS
#
# Emplacement : C:\inetpub\sst\update_sst.ps1
# Utilisation : clic droit "Exécuter en tant qu'administrateur"
#   ou : powershell -ExecutionPolicy Bypass -File C:\inetpub\sst\update_sst.ps1
# ============================================================

$ErrorActionPreference = "Stop"
$AppDir = "C:\inetpub\sst"

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Mise a jour SST - $(Get-Date -Format 'dd/MM/yyyy HH:mm')" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# --- Vérifier qu'on est admin ---
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host " ERREUR : Ce script doit etre execute en tant qu'administrateur." -ForegroundColor Red
    Write-Host " Clic droit sur le script > Executer en tant qu'administrateur" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- Vérifier les prérequis ---
Write-Host "[0/5] Verification des prerequis..." -ForegroundColor Yellow

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Host " ERREUR : Git n'est pas installe ou pas dans le PATH." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host " ERREUR : PHP n'est pas installe ou pas dans le PATH." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

if (-not (Test-Path $AppDir)) {
    Write-Host " ERREUR : Le dossier $AppDir n'existe pas." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

Set-Location $AppDir
Write-Host "  OK : Git, PHP, dossier $AppDir" -ForegroundColor Green

# --- 1. Git pull ---
Write-Host ""
Write-Host "[1/5] Telechargement des mises a jour (git pull)..." -ForegroundColor Yellow

try {
    git pull origin main 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git pull a echoue (code $LASTEXITCODE)"
    }
    Write-Host "  OK : Code a jour" -ForegroundColor Green
}
catch {
    Write-Host ""
    Write-Host " ERREUR : git pull a echoue." -ForegroundColor Red
    Write-Host ""
    Write-Host " Causes possibles :" -ForegroundColor Yellow
    Write-Host "   - Token GitHub expire (password auth not supported)" -ForegroundColor White
    Write-Host "     > git remote set-url origin https://LOGIN:NOUVEAU_PAT@github.com/olivier-noblanc/sst.git" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Proxy non configure (failed to connect port 443)" -ForegroundColor White
    Write-Host "     > git config --global http.proxy http://PROXY:PORT" -ForegroundColor Gray
    Write-Host "     > git config --global http.proxyAuthMethod negotiate" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Repo local diverge (conflits)" -ForegroundColor White
    Write-Host "     > git stash puis relancer ce script" -ForegroundColor Gray
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- 2. Composer install ---
Write-Host ""
Write-Host "[2/5] Installation des dependances (Composer)..." -ForegroundColor Yellow

if (Test-Path "$AppDir\composer.json") {
    # Essayer php composer.phar d'abord, puis composer
    $composerCmd = $null
    if (Test-Path "$AppDir\composer.phar") {
        $composerCmd = "php composer.phar install --no-dev --optimize-autoloader"
    }
    elseif (Get-Command composer -ErrorAction SilentlyContinue) {
        $composerCmd = "composer install --no-dev --optimize-autoloader"
    }
    else {
        Write-Host "  AVERTISSEMENT : Composer non trouve. Dependances non installees." -ForegroundColor DarkYellow
        Write-Host "  Installer Composer : https://getcomposer.org/download/" -ForegroundColor DarkYellow
    }

    if ($composerCmd) {
        try {
            Invoke-Expression $composerCmd
            if ($LASTEXITCODE -ne 0) {
                throw "Composer a echoue (code $LASTEXITCODE)"
            }
            Write-Host "  OK : Dependances a jour" -ForegroundColor Green
        }
        catch {
            Write-Host "  AVERTISSEMENT : Composer a echoue. Les dependances existantes seront utilisees." -ForegroundColor DarkYellow
            Write-Host "  Verifiez que le dossier vendor\ existe." -ForegroundColor DarkYellow
        }
    }
}
else {
    Write-Host "  Saute : pas de composer.json" -ForegroundColor DarkGray
}

# --- 3. Création des dossiers + permissions ---
Write-Host ""
Write-Host "[3/5] Configuration des dossiers et permissions..." -ForegroundColor Yellow

$dirsToCreate = @(
    "$AppDir\data"
)

foreach ($dir in $dirsToCreate) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "  Cree : $dir" -ForegroundColor Gray
    }
}

# Permissions IIS_IUSRS sur data\ (lecture + ecriture)
$aclDir = "$AppDir\data"
$acl = Get-Acl $aclDir
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
    "IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
)
$acl.SetAccessRule($rule)
Set-Acl $aclDir $acl
Write-Host "  OK : Permissions IIS_IUSRS sur $aclDir" -ForegroundColor Green

# --- 4. Version deployée ---
Write-Host ""
Write-Host "[4/5] Version deployee..." -ForegroundColor Yellow

$configFile = "$AppDir\src\config.php"
if (Test-Path $configFile) {
    $version = Select-String -Path $configFile -Pattern "APP_VERSION.*'([^']+)'" | ForEach-Object { $_.Matches[0].Groups[1].Value }
    if ($version) {
        Write-Host "  Version : $version" -ForegroundColor Green
    }
}

$gitLog = git log -1 --format="%h - %s (%cr)" 2>$null
if ($gitLog) {
    Write-Host "  Dernier commit : $gitLog" -ForegroundColor Gray
}

# --- 5. Redémarrage IIS ---
Write-Host ""
Write-Host "[5/5] Redemarrage IIS..." -ForegroundColor Yellow

try {
    iisreset /restart 2>&1 | Out-Null
    Write-Host "  OK : IIS redemarre" -ForegroundColor Green
}
catch {
    Write-Host "  AVERTISSEMENT : iisreset a echoue. Redemarrez IIS manuellement." -ForegroundColor DarkYellow
}

# --- Résumé ---
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " Mise a jour terminee avec succes !" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Read-Host "Appuyez sur Entree pour quitter"
