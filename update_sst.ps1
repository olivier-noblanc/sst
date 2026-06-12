# ============================================================
# update_sst.ps1 — Mise à jour de l'application SST
#
# Ce script effectue :
#   1. Vérification des prérequis (Git, PHP)
#   2. Git fetch + reset --hard (dossier IIS propre = remote)
#   3. Vérification FPDF + polices Marianne
#   4. Création des dossiers + permissions IIS
#   5. Redémarrage IIS
#
# Note : Plus besoin de Composer — FPDF est inclus directement.
# Note : Les polices Marianne (woff2+woff) sont incluses dans le
#        dépôt git (public/fonts/). Pas de téléchargement externe.
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

# --- 1. Git sync : fetch + reset --hard (dossier IIS = copie exacte du remote) ---
Write-Host ""
Write-Host "[1/5] Synchronisation git (fetch + reset --hard)..." -ForegroundColor Yellow

try {
    git fetch origin main 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git fetch a echoue (code $LASTEXITCODE)"
    }

    # Forcer le répertoire de travail à correspondre exactement au remote
    # Cela écrase toute modification locale (conflits, fichiers modifiés, etc.)
    # La base SQLite dans data/ est ignorée par .gitignore donc elle est préservée.
    git reset --hard origin/main 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git reset --hard a echoue (code $LASTEXITCODE)"
    }

    # Nettoyer les fichiers non suivis (orphelins d'anciennes versions)
    git clean -fd 2>&1

    Write-Host "  OK : Dossier synchronise sur origin/main" -ForegroundColor Green
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
    Write-Host "   - Depot local corrompu" -ForegroundColor White
    Write-Host "     > cd $AppDir && git status" -ForegroundColor Gray
    Write-Host "     > git fetch origin main && git reset --hard origin/main" -ForegroundColor Gray
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- 2. Vérification FPDF ---
Write-Host ""
Write-Host "[2/5] Verification FPDF..." -ForegroundColor Yellow

$fpdfPath = "$AppDir\src\lib\fpdf\fpdf.php"
$fontPath = "$AppDir\src\lib\fpdf\font\DejaVuSans.json"

if ((Test-Path $fpdfPath) -and (Test-Path $fontPath)) {
    Write-Host "  OK : FPDF et polices presents" -ForegroundColor Green
} else {
    Write-Host "  AVERTISSEMENT : FPDF ou polices manquants." -ForegroundColor DarkYellow
    Write-Host "  Verifiez que src\lib\fpdf\ existe apres le git pull." -ForegroundColor DarkYellow
}

# --- 3. Vérification polices Marianne (woff2 + woff) ---
Write-Host ""
Write-Host "[3/5] Verification polices Marianne (woff2 + woff)..." -ForegroundColor Yellow

$fontsDir = "$AppDir\public\fonts"

# Liste des polices Marianne incluses dans le dépôt git
$marianneFonts = @(
    'Marianne-Regular.woff2',
    'Marianne-Regular.woff',
    'Marianne-Medium.woff2',
    'Marianne-Medium.woff',
    'Marianne-Bold.woff2',
    'Marianne-Bold.woff',
    'Marianne-Light.woff2',
    'Marianne-Light.woff'
)

$allPresent = $true
foreach ($fileName in $marianneFonts) {
    $filePath = Join-Path $fontsDir $fileName
    if ((Test-Path $filePath) -and ((Get-Item $filePath).Length -gt 0)) {
        $fileSize = (Get-Item $filePath).Length
        Write-Host "  OK : $fileName ($('{0:N0}' -f $fileSize) octets)" -ForegroundColor Green
    } else {
        Write-Host "  MANQUANT : $fileName" -ForegroundColor Red
        $allPresent = $false
    }
}

if ($allPresent) {
    Write-Host "  Toutes les polices Marianne sont presentes." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "  AVERTISSEMENT : Des polices Marianne sont manquantes." -ForegroundColor DarkYellow
    Write-Host "  Elles devraient etre dans le depot git (public/fonts/)." -ForegroundColor DarkYellow
    Write-Host "  Verifiez que le git fetch + reset --hard s'est bien passe." -ForegroundColor DarkYellow
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
