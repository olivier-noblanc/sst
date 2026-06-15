# ============================================================
# update_sst.ps1 — Mise à jour de l'application SST
#
# Ce script effectue :
#   1. Vérification des prérequis (Git, PHP)
#   2. Git pull (via proxy Kerberos)
#   3. Copie des captures d'écran (docs/screenshots/ → public/screenshots/)
#   4. Création des dossiers + permissions IIS
#   5. Redémarrage IIS
#
# Note : Plus besoin de Composer — FPDF est inclus directement.
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

# --- 1. Git sync (force le contenu du remote, écrase les modifs locales) ---
Write-Host ""
Write-Host "[1/5] Telechargement des mises a jour..." -ForegroundColor Yellow

try {
    # Récupérer TOUTES les branches du remote, pas seulement celle trackée.
    # Un clone --single-branch (ou ancien clone master) ne fetch que sa branche
    # par défaut. Ce refspec explicite force la récupération de TOUTES les branches.
    $savedPref = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $fetchOutput = git fetch origin "+refs/heads/*:refs/remotes/origin/*" 2>&1
    $fetchExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref

    if ($fetchExit -ne 0) {
        throw "git fetch a echoue (code $fetchExit)`n$fetchOutput"
    }

    # Détecter la branche par défaut du remote
    $remoteBranch = $null

    # Méthode 1 : origin/HEAD (symref)
    $headRef = git symbolic-ref refs/remotes/origin/HEAD 2>$null
    if ($headRef -match 'refs/remotes/origin/(.+)') {
        $remoteBranch = $Matches[1]
    }

    # Méthode 2 : créer origin/HEAD si elle n'existe pas
    if (-not $remoteBranch) {
        $null = git remote set-head origin --auto 2>$null
        $headRef = git symbolic-ref refs/remotes/origin/HEAD 2>$null
        if ($headRef -match 'refs/remotes/origin/(.+)') {
            $remoteBranch = $Matches[1]
        }
    }

    # Méthode 3 : lister les branches remote (main puis master)
    if (-not $remoteBranch) {
        $remoteBranches = git branch -r 2>$null
        if ($remoteBranches -match 'origin/main') {
            $remoteBranch = 'main'
        } elseif ($remoteBranches -match 'origin/master') {
            $remoteBranch = 'master'
        }
    }

    if (-not $remoteBranch) {
        throw "Impossible de detecter la branche par defaut du remote"
    }

    Write-Host "  Branche distante : $remoteBranch" -ForegroundColor Gray

    # reset --hard fonctionne depuis n'importe quel état (y compris detached HEAD)
    # et n'a pas besoin que la branche locale existe.
    $ErrorActionPreference = "Continue"
    $null = git reset --hard "origin/$remoteBranch" 2>&1
    $resetExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref

    if ($resetExit -ne 0) {
        throw "git reset --hard origin/$remoteBranch a echoue (code $resetExit)"
    }

    # Maintenant que HEAD pointe sur le bon commit, créer/basculer la branche locale
    $ErrorActionPreference = "Continue"
    $null = git checkout -B $remoteBranch 2>&1
    $checkoutExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref

    if ($checkoutExit -ne 0) {
        # checkout -B a échoué mais reset --hard a marché → les fichiers sont à jour
        # On est en detached HEAD, c'est fonctionnel même si pas idéal
        Write-Host "  AVERTISSEMENT : checkout -B $remoteBranch echoue (detached HEAD)" -ForegroundColor DarkYellow
        Write-Host "  Les fichiers sont a jour mais la branche locale n'est pas attachee." -ForegroundColor DarkYellow
    }

    # Nettoyer les fichiers non suivis (orphelins d'anciennes versions)
    git clean -fd 2>&1 | Out-Null

    # Supprimer l'ancienne branche locale si migration master→main
    if ($remoteBranch -eq 'main') {
        $localBranches = git branch 2>$null
        if ($localBranches -match 'master') {
            $null = git branch -D master 2>$null
            Write-Host "  Ancienne branche 'master' supprimee" -ForegroundColor DarkYellow
        }
    }

    # Afficher le commit déployé
    $gitLog = git log -1 --format="%h %s" 2>$null
    Write-Host "  OK : Code synchronise sur origin/$remoteBranch ($gitLog)" -ForegroundColor Green
}
catch {
    Write-Host ""
    Write-Host " ERREUR : la synchronisation Git a echoue." -ForegroundColor Red
    Write-Host "  $_" -ForegroundColor Red
    Write-Host ""
    Write-Host " Causes possibles :" -ForegroundColor Yellow
    Write-Host "   - Token GitHub expire (password auth not supported)" -ForegroundColor White
    Write-Host "     > git remote set-url origin https://LOGIN:NOUVEAU_PAT@github.com/olivier-noblanc/sst.git" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Proxy non configure (failed to connect port 443)" -ForegroundColor White
    Write-Host "     > git config --global http.proxy http://PROXY:PORT" -ForegroundColor Gray
    Write-Host "     > git config --global http.proxyAuthMethod negotiate" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Clone single-branch bloque la branche" -ForegroundColor White
    Write-Host "     > git config remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'" -ForegroundColor Gray
    Write-Host "     > git fetch origin" -ForegroundColor Gray
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- 2. Vérification FPDF ---
Write-Host ""
Write-Host "[2/5] Copie des captures d'ecran..." -ForegroundColor Yellow

$srcScreenshots = "$AppDir\docs\screenshots"
$dstScreenshots = "$AppDir\public\screenshots"

if (Test-Path $srcScreenshots) {
    if (-not (Test-Path $dstScreenshots)) {
        New-Item -ItemType Directory -Path $dstScreenshots -Force | Out-Null
    }
    # Copier tous les .png (captures annotées, écraser si existant)
    Copy-Item -Path "$srcScreenshots\*.png" -Destination $dstScreenshots -Force
    $count = (Get-ChildItem "$dstScreenshots\*.png").Count
    Write-Host "  OK : $count capture(s) copiee(s) vers public\screenshots\" -ForegroundColor Green
} else {
    Write-Host "  AVERTISSEMENT : dossier docs\screenshots\ introuvable" -ForegroundColor DarkYellow
    Write-Host "  Les captures d'ecran ne seront pas disponibles dans la page Documentation." -ForegroundColor DarkYellow
}

# --- 3. Vérification FPDF ---
Write-Host ""
Write-Host "[3/5] Verification FPDF..." -ForegroundColor Yellow

$fpdfPath = "$AppDir\src\lib\fpdf\fpdf.php"
$fontPath = "$AppDir\src\lib\fpdf\font\DejaVuSans.json"

if ((Test-Path $fpdfPath) -and (Test-Path $fontPath)) {
    Write-Host "  OK : FPDF et polices presents" -ForegroundColor Green
} else {
    Write-Host "  AVERTISSEMENT : FPDF ou polices manquants." -ForegroundColor DarkYellow
    Write-Host "  Verifiez que src\lib\fpdf\ existe apres le git pull." -ForegroundColor DarkYellow
}

# --- 4. Création des dossiers + permissions ---
Write-Host ""
Write-Host "[4/5] Configuration des dossiers et permissions..." -ForegroundColor Yellow

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

# --- Version déployée ---
Write-Host ""
# Try reading version from SQLite database (authoritative source)
$dbFile = "$AppDir\data\sst.db"
$version = $null
if (Test-Path $dbFile) {
    try {
        $version = & sqlite3 $dbFile "SELECT valeur FROM config_app WHERE cle = 'app_version';" 2>$null
    } catch {
        # sqlite3 not available — fall back to config.php
    }
}
# Fallback: read from config.php (the hardcoded fallback constant)
if (-not $version) {
    $configFile = "$AppDir\src\config.php"
    if (Test-Path $configFile) {
        $version = Select-String -Path $configFile -Pattern "APP_VERSION'\s*,\s*'([^']+)'" | ForEach-Object { $_.Matches[0].Groups[1].Value }
    }
}
if ($version) {
    Write-Host "  Version : $version" -ForegroundColor Green
}

$gitLog = git log -1 --format="%h - %s (%cr)" 2>$null
if ($gitLog) {
    Write-Host "  Dernier commit : $gitLog" -ForegroundColor Gray
}

# --- Résumé ---
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " Mise a jour terminee avec succes !" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Read-Host "Appuyez sur Entree pour quitter"
