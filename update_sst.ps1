# ============================================================
# update_sst.ps1 — Mise à jour de l'application SST
#
# Ce script effectue :
#   0. Vérification des prérequis (Git, PHP)
#   1. Migration du remote GitHub → Codeberg (si nécessaire)
#   2. Git pull (via proxy Kerberos)
#   3. Copie des captures d'écran (docs/screenshots/ → public/screenshots/)
#   4. Vérification FPDF
#   5. Création des dossiers + permissions IIS
#
# Note : Plus besoin de Composer — FPDF est inclus directement.
#
# Emplacement : C:\inetpub\sst\update_sst.ps1
# Utilisation : clic droit "Exécuter en tant qu'administrateur"
#   ou : powershell -ExecutionPolicy Bypass -File C:\inetpub\sst\update_sst.ps1
# ============================================================

$ErrorActionPreference = "Stop"
$AppDir = "C:\inetpub\sst"
$ExpectedRemoteUrl = "https://codeberg.org/oliviernoblanc/sst.git"

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

# --- Vérifier / migrer le remote vers Codeberg ---
Write-Host ""
Write-Host "[1/5] Verification du depot distant..." -ForegroundColor Yellow

$savedPref = $ErrorActionPreference
$ErrorActionPreference = "Continue"
$currentRemote = git remote get-url origin 2>&1
$remoteExit = $LASTEXITCODE
$ErrorActionPreference = $savedPref

if ($remoteExit -ne 0 -or -not $currentRemote) {
    Write-Host " ERREUR : Impossible de lire l'URL du remote origin." -ForegroundColor Red
    Write-Host "  $currentRemote" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# Normaliser l'URL pour comparaison (retirer le token s'il est inclus)
$currentRemoteNormalized = $currentRemote -replace 'https://[^@]+@', 'https://'

if ($currentRemoteNormalized -match 'github\.com') {
    Write-Host "  Remote actuel : $currentRemoteNormalized" -ForegroundColor Yellow
    Write-Host "  Le depot a migre de GitHub vers Codeberg." -ForegroundColor Yellow
    Write-Host "  Migration automatique du remote..." -ForegroundColor Yellow

    $ErrorActionPreference = "Continue"
    $null = git remote set-url origin $ExpectedRemoteUrl 2>&1
    $setUrlExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref

    if ($setUrlExit -ne 0) {
        Write-Host " ERREUR : Impossible de mettre a jour le remote." -ForegroundColor Red
        Write-Host "  Executez manuellement :" -ForegroundColor Yellow
        Write-Host "    cd $AppDir" -ForegroundColor Gray
        Write-Host "    git remote set-url origin $ExpectedRemoteUrl" -ForegroundColor Gray
        Read-Host "Appuyez sur Entree pour quitter"
        exit 1
    }

    Write-Host "  OK : Remote migre vers Codeberg" -ForegroundColor Green
} elseif ($currentRemoteNormalized -match 'codeberg\.org') {
    Write-Host "  OK : Remote deja sur Codeberg ($currentRemoteNormalized)" -ForegroundColor Green
} else {
    Write-Host "  AVERTISSEMENT : Remote non reconnu : $currentRemoteNormalized" -ForegroundColor DarkYellow
    Write-Host "  Le remote attendu est : $ExpectedRemoteUrl" -ForegroundColor DarkYellow
    Write-Host "  Tentative de correction..." -ForegroundColor Yellow

    $ErrorActionPreference = "Continue"
    $null = git remote set-url origin $ExpectedRemoteUrl 2>&1
    $setUrlExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref

    if ($setUrlExit -eq 0) {
        Write-Host "  OK : Remote corrige vers Codeberg" -ForegroundColor Green
    } else {
        Write-Host " ERREUR : Impossible de corriger le remote." -ForegroundColor Red
        Read-Host "Appuyez sur Entree pour quitter"
        exit 1
    }
}

# --- 2. Git sync (force le contenu du remote, écrase les modifs locales) ---
Write-Host ""
Write-Host "[2/5] Telechargement des mises a jour..." -ForegroundColor Yellow

# Construire l'URL avec le token pour le fetch/pull
$token = $env:FORMULAIRE_TOKEN
if ($token) {
    $authRemoteUrl = "https://${token}@codeberg.org/oliviernoblanc/sst.git"
    $null = git remote set-url origin $authRemoteUrl 2>&1
    Write-Host "  OK : Token Codeberg configure pour cette session" -ForegroundColor Green
} else {
    Write-Host "  AVERTISSEMENT : Aucun token FORMULAIRE_TOKEN dans l'environnement." -ForegroundColor DarkYellow
    Write-Host "  Si le depot est prive, le fetch va echouer." -ForegroundColor DarkYellow
    Write-Host '  Definissez : $env:FORMULAIRE_TOKEN = "votre_token"' -ForegroundColor Gray
}

try {
    # Récupérer TOUTES les branches du remote, pas seulement celle trackée.
    # Un clone --single-branch (ou ancien clone master) ne fetch que sa branche
    # par défaut. Ce refspec explicite force la récupération de TOUTES les branches.
    $savedPref = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $env:GIT_TERMINAL_PROMPT = "0"
    $fetchOutput = git -c credential.helper= fetch origin "+refs/heads/*:refs/remotes/origin/*" 2>&1
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

    # Restaurer l'URL propre du remote (sans token) pour la sécurité
    if ($token) {
        $null = git remote set-url origin $ExpectedRemoteUrl 2>&1
    }
}
catch {
    Write-Host ""
    Write-Host " ERREUR : la synchronisation Git a echoue." -ForegroundColor Red
    Write-Host "  $_" -ForegroundColor Red
    Write-Host ""
    Write-Host " Causes possibles :" -ForegroundColor Yellow
    Write-Host "   - Token Codeberg expire ou invalide" -ForegroundColor White
    Write-Host "     > git remote set-url origin https://TOKEN@codeberg.org/oliviernoblanc/sst.git" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Proxy non configure (failed to connect port 443)" -ForegroundColor White
    Write-Host "     > git config --global http.proxy http://PROXY:PORT" -ForegroundColor Gray
    Write-Host "     > git config --global http.proxyAuthMethod negotiate" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Clone single-branch bloque la branche" -ForegroundColor White
    Write-Host "     > git config remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'" -ForegroundColor Gray
    Write-Host "     > git fetch origin" -ForegroundColor Gray
    Write-Host ""
    Write-Host "   - Ancien remote GitHub encore configure" -ForegroundColor White
    Write-Host "     > git remote set-url origin https://TOKEN@codeberg.org/oliviernoblanc/sst.git" -ForegroundColor Gray
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- 3. Composer autoload (PSR-4) ---
Write-Host ""
Write-Host "[3/6] Generation de l'autoload Composer..." -ForegroundColor Yellow

$composerJson = "$AppDir\composer.json"
if (Test-Path $composerJson) {
    $savedPrefComposer = $ErrorActionPreference
    $ErrorActionPreference = "Continue"

    # Toujours régénérer l'autoload — vendor/ peut être absent (gitignore)
    # composer dump-autoload fonctionne même sans vendor/ (crée la structure)
    if (-not (Test-Path "$AppDir\vendor\autoload.php")) {
        Write-Host "  vendor/ absent — composer install puis dump-autoload..." -ForegroundColor Gray
        $composerOutput = composer install --no-interaction 2>&1
        $composerExit = $LASTEXITCODE
        if ($composerExit -eq 0) {
            $composerOutput = composer dump-autoload --optimize --no-dev 2>&1
            $composerExit = $LASTEXITCODE
        }
    } else {
        $composerOutput = composer dump-autoload --optimize --no-dev 2>&1
        $composerExit = $LASTEXITCODE
    }
    $ErrorActionPreference = $savedPrefComposer

    if ($composerExit -eq 0) {
        Write-Host "  OK : Autoload PSR-4 genere" -ForegroundColor Green
    } else {
        Write-Host "  ERREUR : Composer a echoue (code $composerExit)" -ForegroundColor Red
        Write-Host "  $composerOutput" -ForegroundColor Gray
        Write-Host "  Les classes PSR-4 ne seront pas chargees — l'application ne demarrera pas." -ForegroundColor Red
        Read-Host "Appuyez sur Entree pour quitter"
        exit 1
    }
} else {
    Write-Host "  SKIP : composer.json absent" -ForegroundColor DarkYellow
}

# --- 4. Copie des captures d'écran ---
Write-Host ""
Write-Host "[4/6] Copie des captures d'ecran..." -ForegroundColor Yellow

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

# --- 4. Vérification FPDF ---
Write-Host ""
Write-Host "[5/6] Verification FPDF..." -ForegroundColor Yellow

$fpdfPath = "$AppDir\src\lib\fpdf\fpdf.php"
$fontPath = "$AppDir\src\lib\fpdf\font\DejaVuSans.json"

if ((Test-Path $fpdfPath) -and (Test-Path $fontPath)) {
    Write-Host "  OK : FPDF et polices presents" -ForegroundColor Green
} else {
    Write-Host "  AVERTISSEMENT : FPDF ou polices manquants." -ForegroundColor DarkYellow
    Write-Host "  Verifiez que src\lib\fpdf\ existe apres le git pull." -ForegroundColor DarkYellow
}

# --- 5. Création des dossiers + permissions ---
Write-Host ""
Write-Host "[5/5] Configuration des dossiers et permissions..." -ForegroundColor Yellow

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

# --- Version déployée ---
Write-Host ""
# Read version from CHANGELOG.md (authoritative source — matches what PHP shows)
$changelogFile = "$AppDir\CHANGELOG.md"
$version = $null
if (Test-Path $changelogFile) {
    $match = Select-String -Path $changelogFile -Pattern '^##\s*\[(\d+\.\d+\.\d+)\]' | Select-Object -First 1
    if ($match) {
        $version = $match.Matches[0].Groups[1].Value
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

# --- 6. Clear PHP opcache ---
Write-Host ""
Write-Host "[6/6] Clear du cache PHP..." -ForegroundColor Yellow

# Toucher un fichier PHP pour forcer IIS à recharger
$indexPhp = "$AppDir\public\index.php"
if (Test-Path $indexPhp) {
    (Get-Item $indexPhp).LastWriteTime = Get-Date
    Write-Host "  OK : index.php mis a jour (opcache clear)" -ForegroundColor Green
}

# --- Résumé ---
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " Mise a jour terminee avec succes !" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Read-Host "Appuyez sur Entree pour quitter"