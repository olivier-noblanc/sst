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

# --- Détecter la branche par défaut du remote ---
Write-Host ""
Write-Host " Detection de la branche par defaut..." -ForegroundColor Yellow

# Récupérer toutes les refs du remote pour détecter la branche par défaut
# GitHub peut utiliser 'main' ou 'master' selon l'âge du dépôt
try {
    git fetch origin 2>&1 | Out-Null
}
catch {
    # Si fetch échoue ici, on continuera — l'erreur sera interceptée à l'étape 1
}

$remoteBranch = $null

# Méthode 1 : lire origin/HEAD (symref vers la branche par défaut)
$headRef = git symbolic-ref refs/remotes/origin/HEAD 2>$null
if ($headRef -match 'refs/remotes/origin/(.+)') {
    $remoteBranch = $Matches[1]
    Write-Host "  Branche par defaut (origin/HEAD) : $remoteBranch" -ForegroundColor Green
}

# Méthode 2 : si origin/HEAD n'existe pas, essayer main puis master
if (-not $remoteBranch) {
    $remoteBranches = git branch -r 2>$null
    if ($remoteBranches -match 'origin/main') {
        $remoteBranch = 'main'
    } elseif ($remoteBranches -match 'origin/master') {
        $remoteBranch = 'master'
    }
    if ($remoteBranch) {
        Write-Host "  Branche detectee (branch -r) : $remoteBranch" -ForegroundColor Green
    }
}

# Méthode 3 : dernier recours — lire la branche locale courante
if (-not $remoteBranch) {
    $localBranch = git rev-parse --abbrev-ref HEAD 2>$null
    if ($localBranch -and $localBranch -ne 'HEAD') {
        $remoteBranch = $localBranch
        Write-Host "  Branche locale utilisee : $remoteBranch" -ForegroundColor DarkYellow
    }
}

if (-not $remoteBranch) {
    Write-Host " ERREUR : Impossible de detecter la branche par defaut." -ForegroundColor Red
    Write-Host " Verifiez que le depot Git est bien clone et que le remote 'origin' existe." -ForegroundColor Yellow
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- 1. Git sync (force le contenu du remote, écrase les modifs locales) ---
Write-Host ""
Write-Host "[1/5] Telechargement des mises a jour (git fetch + reset --hard)..." -ForegroundColor Yellow

try {
    # Récupérer les objets du remote sans fusionner
    git fetch origin $remoteBranch 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git fetch a echoue (code $LASTEXITCODE)"
    }

    # Forcer le répertoire de travail à correspondre exactement au remote
    # Cela écrase toute modification locale (conflits, fichiers modifiés, etc.)
    # La base SQLite dans data/ est ignorée par .gitignore donc elle est préservée.
    git reset --hard "origin/$remoteBranch" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git reset --hard a echoue (code $LASTEXITCODE)"
    }

    # Nettoyer les fichiers non suivis (orphelins d'anciennes versions)
    git clean -fd 2>&1

    # S'assurer que la branche locale suit le remote
    git checkout $remoteBranch 2>&1 | Out-Null
    git branch --set-upstream-to="origin/$remoteBranch" $remoteBranch 2>$null | Out-Null

    Write-Host "  OK : Code synchronise sur origin/$remoteBranch" -ForegroundColor Green
}
catch {
    Write-Host ""
    Write-Host " ERREUR : la synchronisation Git a echoue." -ForegroundColor Red
    Write-Host ""
    Write-Host " Causes possibles :" -ForegroundColor Yellow
    Write-Host "   - Token GitHub expire (password auth not supported)" -ForegroundColor White
    Write-Host "     > git remote set-url origin https://LOGIN:NOUVEAU_PAT@github.com/olivier-noblanc/sst.git" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Proxy non configure (failed to connect port 443)" -ForegroundColor White
    Write-Host "     > git config --global http.proxy http://PROXY:PORT" -ForegroundColor Gray
    Write-Host "     > git config --global http.proxyAuthMethod negotiate" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Branche inexistante (main vs master)" -ForegroundColor White
    Write-Host "     > git branch -r                          # liste les branches remote" -ForegroundColor Gray
    Write-Host "     > git fetch origin && git reset --hard origin/main   # ou origin/master" -ForegroundColor Gray
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
    # Copier tous les .html (écraser si existant)
    Copy-Item -Path "$srcScreenshots\*.html" -Destination $dstScreenshots -Force
    $count = (Get-ChildItem "$dstScreenshots\*.html").Count
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
$configFile = "$AppDir\src\config.php"
if (Test-Path $configFile) {
    $version = Select-String -Path $configFile -Pattern "APP_VERSION'\s*,\s*'([^']+)'" | ForEach-Object { $_.Matches[0].Groups[1].Value }
    if ($version) {
        Write-Host "  Version : $version" -ForegroundColor Green
    }
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
