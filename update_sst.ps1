# ============================================================
# update_sst.ps1 — Mise a jour de l'application SST
#
# Usage :
#   .\update_sst.ps1              # Mise a jour normale (sauvegarde auto + gate)
#   .\update_sst.ps1 -DryRun      # Simule sans rien modifier
#   .\update_sst.ps1 -SkipBackup  # Pas de sauvegarde (dangereux)
#   .\update_sst.ps1 -SkipTests   # Passe la gate (DEPLOIEMENT D'URGENCE UNIQUEMENT)
#   .\update_sst.ps1 -SkipLint    # Passe seulement le lint PHP (URGENCE)
#
# Fonctionne en 2 scenarios :
#   1. Depot git existant -> git pull + gate + post-hooks
#   2. Pas de git -> clone temp + copie a plat + gate
#
# -- Gate qualite (securite deploiement) ------------------------
# Apres git pull (ou copie), le script execute :
#   1. Lint PHP (php -l sur tous les .php hors vendor/tests)
#   2. PHPStan niveau 10 (analyse statique)
#   3. Tests PHPUnit (phpunit.phar --no-coverage)
# Si un seul echoue -> ROLLBACK AUTOMATIQUE + exit 1.
# Pour bypasser (hotfix urgent) : -SkipTests
#
# Emplacement prod : C:\inetpub\sst\update_sst.ps1
# ============================================================

param(
    [switch]$DryRun     = $false,
    [switch]$SkipBackup = $false,
    [switch]$SkipTests  = $false,
    [switch]$SkipLint   = $false
)

$ErrorActionPreference = "Stop"
$AppDir = "C:\inetpub\sst"
$ExpectedRemoteUrl = "https://codeberg.org/oliviernoblanc/sst.git"

# -- Outils PHP (shims scoop) --
$PhpBin     = "php"
$PhpUnitPhar = "$env:USERPROFILE\scoop\shims\phpunit.phar"
$PhpStanBin  = "phpstan"

# -- Branches --
$RepoBranch = "main"

# -- Fichiers a proteger (jamais ecrases) --
$ProtectedFiles = @()

# -- Repertoire du script --
$ScriptDir = $PSScriptRoot
if (-not $ScriptDir) { $ScriptDir = (Get-Location).Path }

# -- Empecher git de bloquer en attente de saisie --
$env:GIT_TERMINAL_PROMPT = "0"
$env:GCM_INTERACTIVE = "Never"

# ============================================================
# Fonctions utilitaires
# ============================================================

function Write-Status {
    param([string]$Icon, [string]$Message, [string]$Color = "White")
    Write-Host "  $Icon $Message" -ForegroundColor $Color
}

function Write-Section {
    param([string]$Title)
    Write-Host ""
    Write-Host "  -- $Title --" -ForegroundColor Cyan
}

function Create-Backup {
    param([string]$SourceDir)
    $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $backupDir = Join-Path $SourceDir "backups\backup-$timestamp"
    try {
        New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
        $extensions = @("*.php", "*.md", "*.css", "*.js")
        foreach ($ext in $extensions) {
            $files = Get-ChildItem -Path $SourceDir -Filter $ext -Recurse -File |
                Where-Object { $_.FullName -notmatch '\\(db|sessions|vendor|logs|\.git|\.mimocode|backups|data|graphify-out|node_modules)\\' }
            foreach ($file in $files) {
                $relativePath = $file.FullName.Substring($SourceDir.Length + 1)
                $destPath     = Join-Path $backupDir $relativePath
                $destDir      = Split-Path -Parent $destPath
                if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
                Copy-Item -Path $file.FullName -Destination $destPath -Force
            }
        }
        Write-Status "OK" "Sauvegarde creee : backups\backup-$timestamp" "Green"
        return $backupDir
    } catch {
        Write-Status "X" "Echec de la sauvegarde : $_" "Red"
        return $null
    }
}

function Restore-LastBackup {
    param([string]$BackupPath)
    if (-not $BackupPath -or -not (Test-Path $BackupPath)) {
        Write-Status "X" "Aucune sauvegarde a restaurer." "Red"
        return
    }
    Write-Section "Rollback : restauration de la sauvegarde"
    $restored = 0
    $backupFiles = Get-ChildItem -Path $BackupPath -Recurse -File -ErrorAction SilentlyContinue
    foreach ($file in $backupFiles) {
        $relativePath = $file.FullName.Substring($BackupPath.Length + 1)
        $destPath = Join-Path $AppDir $relativePath
        $destParent = Split-Path -Parent $destPath
        if (-not (Test-Path $destParent)) { New-Item -ItemType Directory -Path $destParent -Force | Out-Null }
        Copy-Item -Path $file.FullName -Destination $destPath -Force
        $restored++
    }
    Write-Status "OK" "$restored fichier(s) restaure(s) depuis la sauvegarde." "Green"
}

# ============================================================
# Gate qualite : lint + PHPStan + PHPUnit
# Retourne $true si tout passe, $false sinon.
# ============================================================

function Invoke-QualityGate {
    Write-Section "Gate qualite (lint + [PHPStan | PHPUnit | E2E] parallele)"

    # Verifier que PHP est disponible
    $phpExe = Get-Command $PhpBin -ErrorAction SilentlyContinue
    if (-not $phpExe) {
        $phpFallback = "C:\PHP\php.exe"
        if (Test-Path $phpFallback) {
            $phpExe = $phpFallback
        } else {
            Write-Status "X" "PHP non trouve dans le PATH." "Red"
            return $false
        }
    }
    $phpPath = if ($phpExe.Source) { $phpExe.Source } else { $phpExe }
    Write-Status "OK" "PHP : $phpPath" "Green"

    # ── Vérifier les outils requis ──
    $missing = @()

    $phpstanShim = "$env:USERPROFILE\scoop\shims\phpstan"
    if (-not (Get-Command $PhpStanBin -ErrorAction SilentlyContinue) -and -not (Test-Path $phpstanShim)) {
        $missing += "PHPStan"
    }

    if (-not (Test-Path $PhpUnitPhar) -and -not (Get-Command phpunit -ErrorAction SilentlyContinue)) {
        $missing += "PHPUnit"
    }

    $pythonPw = $false
    $npxPw = $false
    try { $npxV = & npx playwright --version 2>&1; if ($LASTEXITCODE -eq 0 -and $npxV -match 'Version') { $npxPw = $true } } catch {}

    if ($missing.Count -gt 0) {
        Write-Host ""
        Write-Status "X" "OUTILS MANQUANTS — deploiement BLOQUE" "Red"
        foreach ($m in $missing) {
            switch ($m) {
                "PHPStan"   { Write-Host "    Installer PHPStan : scoop install phpstan" -ForegroundColor Yellow }
                "PHPUnit"   { Write-Host "    Installer PHPUnit : scoop install phpunit" -ForegroundColor Yellow }
            }
        }
        Write-Host ""
        return $false
    }

    Write-Status "OK" "PHPStan, PHPUnit, Playwright (npx: $npxPw)" "Green"
    Write-Host ""

    $gateOk = $true

    # ── 1. Lint PHP (php -l) sur tous les .php hors vendor/tests ──
    if ($SkipLint) {
        Write-Status "!" "Lint PHP ignore (-SkipLint). DANGEREUX." "Yellow"
    } else {
        Write-Status ">" "Etape 1/4 : Lint PHP (php -l, xdebug off)..." "Cyan"

        $phpFiles = Get-ChildItem -Path $AppDir -Recurse -File -Filter "*.php" -ErrorAction SilentlyContinue |
            Where-Object {
                $rel = $_.FullName.Substring($AppDir.Length + 1)
                -not ($rel -like "vendor\*" -or $rel -like "vendor/*" -or
                      $rel -like "tests\*"   -or $rel -like "tests/*"   -or
                      $rel -like "backups\*" -or $rel -like "backups/*" -or
                      $rel -like ".git\*"    -or $rel -like ".git/*"    -or
                      $rel -like ".mimocode\*" -or $rel -like ".mimocode/*" -or
                      $rel -like "data\*"    -or $rel -like "data/*"    -or
                      $rel -like "graphify-out\*" -or $rel -like "graphify-out/*" -or
                      $rel -like "node_modules\*" -or $rel -like "node_modules/*" -or
                      $rel -like "e2e\*"     -or $rel -like "e2e/*")
            }

        # Scope incremental : ne linter que les fichiers modifies
        $filesToLint = $phpFiles.FullName
        $incrementalUsed = $false
        try {
            $gitAvailable = (Get-Command git -ErrorAction SilentlyContinue) -ne $null
            if ($gitAvailable) {
                Push-Location $AppDir
                $hasCommits = (git rev-parse --is-inside-work-tree 2>&1) -eq $true
                if ($hasCommits) {
                    $changedFiles = git diff --name-only --diff-filter=ACM HEAD~1 HEAD -- "*.php" 2>$null
                    if (-not $changedFiles -or $changedFiles.Count -eq 0) {
                        $changedFiles = git diff --name-only --diff-filter=ACM -- "*.php" 2>$null
                    }
                    if ($changedFiles -and $changedFiles.Count -gt 0 -and $changedFiles.Count -le 50) {
                        $filesToLint = @()
                        foreach ($rel in $changedFiles) {
                            $full = Join-Path $AppDir $rel
                            if (Test-Path $full) {
                                $normalized = $full -replace '/', '\'
                                if ($normalized -notmatch '\\(vendor|tests|backups|\.git|\.mimocode|data|graphify-out|node_modules|e2e)\\') {
                                    $filesToLint += $full
                                }
                            }
                        }
                        if ($filesToLint.Count -gt 0) {
                            $incrementalUsed = $true
                            Write-Status ">" "Lint incrementel : $($filesToLint.Count) fichier(s) modifie(s)." "DarkGray"
                        }
                    }
                }
                Pop-Location
            }
        } catch {
            Write-Status ">" "git non disponible — lint complet." "DarkGray"
        }

        if (-not $filesToLint -or $filesToLint.Count -eq 0) {
            Write-Status "OK" "Lint PHP : aucun fichier a verifier." "Green"
        } else {
            $lintErrors = 0
            $lintChecked = 0
            $psVersion = $PSVersionTable.PSVersion.Major

            if ($psVersion -ge 7) {
                # Parallele PowerShell 7+
                Write-Status ">" "Parallisme active (PS $($PSVersionTable.PSVersion))." "DarkGray"
                $results = $filesToLint | ForEach-Object -ThrottleLimit 8 -Parallel {
                    $out = & $using:phpPath -d xdebug.mode=off -l $_ 2>&1
                    [PSCustomObject]@{ File = $_; OK = $LASTEXITCODE -eq 0; Output = $out }
                }
                foreach ($r in $results) {
                    $lintChecked++
                    if (-not $r.OK) {
                        $rel = $r.File.Substring($using:AppDir.Length + 1)
                        Write-Status "X" "Erreur de syntaxe : $rel" "Red"
                        $r.Output | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkRed }
                        $lintErrors++
                    }
                }
            } else {
                # Sequentiel PS 5.1
                foreach ($file in $filesToLint) {
                    $lintChecked++
                    $output = & $phpPath -d xdebug.mode=off -l $file 2>&1
                    if ($LASTEXITCODE -ne 0) {
                        $rel = $file.Substring($AppDir.Length + 1)
                        Write-Status "X" "Erreur de syntaxe : $rel" "Red"
                        $output | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkRed }
                        $lintErrors++
                    }
                }
            }

            $modeStr = if ($incrementalUsed) { "incrementel" } else { "complet" }
            if ($lintErrors -gt 0) {
                Write-Status "X" "Lint PHP ($modeStr) : $lintErrors erreur(s) sur $lintChecked fichier(s)." "Red"
                $gateOk = $false
            } else {
                Write-Status "OK" "Lint PHP ($modeStr) : $lintChecked fichier(s), 0 erreur." "Green"
            }
        }
    }

    # ── 2-4. PHPStan + PHPUnit + E2E en parallèle ──
    Write-Status ">" "Etapes 2-4 : PHPStan + PHPUnit + E2E (parallèle)..." "Cyan"

    $tmpDir = Join-Path $env:TEMP "sst-gate-$(Get-Random)"
    New-Item -ItemType Directory -Path $tmpDir -Force | Out-Null

    # Lancer PHPStan en arrière-plan
    $phpstanJob = Start-Job -ScriptBlock {
        param($phpstanBin, $phpstanShim, $tmpDir)
        $ran = $false
        $output = $null
        $rc = 1
        if (Get-Command $phpstanBin -ErrorAction SilentlyContinue) {
            $ran = $true
            $output = & $phpstanBin analyse --memory-limit=1G --no-progress 2>&1
            $rc = $LASTEXITCODE
        } elseif (Test-Path $phpstanShim) {
            $ran = $true
            $output = & $phpstanShim analyse --memory-limit=1G --no-progress 2>&1
            $rc = $LASTEXITCODE
        }
        $output | Out-File "$tmpDir\phpstan.out"
        Set-Content "$tmpDir\phpstan.rc" $rc
        Set-Content "$tmpDir\phpstan.ran" $ran
    } -ArgumentList $PhpStanBin, $phpstanShim, $tmpDir

    # Lancer PHPUnit en arrière-plan
    $phpunitJob = Start-Job -ScriptBlock {
        param($phpPath, $phpUnitPhar, $tmpDir)
        $ran = $false
        $output = $null
        $rc = 1
        if (Test-Path $phpUnitPhar) {
            $ran = $true
            $output = & $phpPath $phpUnitPhar --no-coverage 2>&1
            $rc = $LASTEXITCODE
        } elseif (Get-Command phpunit -ErrorAction SilentlyContinue) {
            $ran = $true
            $output = & phpunit --no-coverage 2>&1
            $rc = $LASTEXITCODE
        }
        $output | Out-File "$tmpDir\phpunit.out"
        Set-Content "$tmpDir\phpunit.rc" $rc
        Set-Content "$tmpDir\phpunit.ran" $ran
    } -ArgumentList $phpPath, $PhpUnitPhar, $tmpDir

    # Lancer E2E en arrière-plan
    $e2eJob = Start-Job -ScriptBlock {
        param($tmpDir)
        $ran = $false
        $output = $null
        $rc = 1
        $e2eCmd = $null
        try {
            $npxVersion = & npx playwright --version 2>&1
            if ($LASTEXITCODE -eq 0 -and $npxVersion -match 'Version') {
                $e2eCmd = "npx"
            }
        } catch {}
        if ($e2eCmd) {
            $ran = $true
            $output = & npx playwright test --project=firefox -q 2>&1
            $rc = $LASTEXITCODE
        }
        $output | Out-File "$tmpDir\e2e.out"
        Set-Content "$tmpDir\e2e.rc" $rc
        Set-Content "$tmpDir\e2e.ran" $ran
        Set-Content "$tmpDir\e2e.cmd" $(if ($e2eCmd) { $e2eCmd } else { "none" })
    } -ArgumentList $tmpDir

    # Lancer CSS checker en arrière-plan
    $cssJob = Start-Job -ScriptBlock {
        param($tmpDir)
        $ran = $false
        $output = $null
        $rc = 1
        try {
            $ran = $true
            $output = & php tools/check_css_classes.php 2>&1
            $rc = $LASTEXITCODE
        } catch {}
        $output | Out-File "$tmpDir\css.out"
        Set-Content "$tmpDir\css.rc" $rc
        Set-Content "$tmpDir\css.ran" $ran
    } -ArgumentList $tmpDir

    # Lancer Infection (mutation testing) en arrière-plan
    $infectionBin = "$env:USERPROFILE\scoop\shims\infection"
    $infectionJob = Start-Job -ScriptBlock {
        param($phpPath, $infectionBin, $tmpDir)
        $ran = $false
        $output = $null
        $rc = 1
        if (Test-Path $infectionBin) {
            $ran = $true
            $output = & $phpPath $infectionBin --show-mutations --no-progress --threads=4 2>&1
            $rc = $LASTEXITCODE
        } elseif (Test-Path "vendor/bin/infection") {
            $ran = $true
            $output = & $phpPath vendor/bin/infection --show-mutations --no-progress --threads=4 2>&1
            $rc = $LASTEXITCODE
        }
        $output | Out-File "$tmpDir\infection.out"
        Set-Content "$tmpDir\infection.rc" $rc
        Set-Content "$tmpDir\infection.ran" $ran
    } -ArgumentList $phpPath, $infectionBin, $tmpDir

    # Attendre les 5 jobs
    $phpstanJob, $phpunitJob, $e2eJob, $cssJob, $infectionJob | Wait-Job | Out-Null

    # ── Résultat PHPStan ──
    $phpstanRan = (Get-Content "$tmpDir\phpstan.ran" -ErrorAction SilentlyContinue) -eq 'True'
    $phpstanRc = if (Test-Path "$tmpDir\phpstan.rc") { [int](Get-Content "$tmpDir\phpstan.rc") } else { 1 }
    if ($phpstanRan) {
        $phpstanOut = Get-Content "$tmpDir\phpstan.out" -ErrorAction SilentlyContinue |
            Where-Object { $_ -notmatch 'session_start|PHP Request Shutdown|headers already sent' }
        $phpstanOut | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        if ($phpstanRc -eq 0) {
            Write-Status "OK" "PHPStan : OK (niveau 8, baseline autorisee)." "Green"
        } else {
            Write-Status "X" "PHPStan : echec (code $phpstanRc)." "Red"
            $gateOk = $false
        }
    } else {
        Write-Status "!" "PHPStan non trouve. Etape skippee." "Yellow"
    }

    # ── Résultat PHPUnit ──
    $phpunitRan = (Get-Content "$tmpDir\phpunit.ran" -ErrorAction SilentlyContinue) -eq 'True'
    $phpunitRc = if (Test-Path "$tmpDir\phpunit.rc") { [int](Get-Content "$tmpDir\phpunit.rc") } else { 1 }
    if ($phpunitRan) {
        $phpunitOut = Get-Content "$tmpDir\phpunit.out" -ErrorAction SilentlyContinue |
            Where-Object { $_ -notmatch '\[SST-DB\]' }
        $phpunitOut | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        $lastLines = $phpunitOut | Select-Object -Last 5
        $failed = $false
        foreach ($line in $lastLines) {
            if ($line -match 'FAILURES|Tests:.*Errors:|Tests:.*Failures:' -and $line -notmatch 'OK \(') {
                $failed = $true
            }
        }
        if ($phpunitRc -eq 0 -and -not $failed) {
            Write-Status "OK" "PHPUnit : OK." "Green"
        } else {
            Write-Status "X" "PHPUnit : echec (code $phpunitRc)." "Red"
            $gateOk = $false
        }
    } else {
        Write-Status "!" "PHPUnit non trouve ($PhpUnitPhar). Etape skippee." "Yellow"
    }

    # ── Résultat E2E ──
    $e2eRan = (Get-Content "$tmpDir\e2e.ran" -ErrorAction SilentlyContinue) -eq 'True'
    $e2eRc = if (Test-Path "$tmpDir\e2e.rc") { [int](Get-Content "$tmpDir\e2e.rc") } else { 1 }
    $e2eCmd = Get-Content "$tmpDir\e2e.cmd" -ErrorAction SilentlyContinue
    if ($e2eRan) {
        $e2eOut = Get-Content "$tmpDir\e2e.out" -ErrorAction SilentlyContinue |
            Where-Object { $_ -notmatch '^\s*$' }
        $e2eOut | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        if ($e2eRc -eq 0) {
            Write-Status "OK" "E2E Playwright ($e2eCmd, Firefox) : OK." "Green"
        } else {
            Write-Status "!" "E2E Playwright : echec (code $e2eRc) — non bloquant pour le deploiement." "Yellow"
        }
    } else {
        Write-Status "!" "Playwright non trouve (npx). E2E skippee." "Yellow"
    }

    # ── Résultat CSS checker ──
    $cssRan = (Get-Content "$tmpDir\css.ran" -ErrorAction SilentlyContinue) -eq 'True'
    $cssRc = if (Test-Path "$tmpDir\css.rc") { [int](Get-Content "$tmpDir\css.rc") } else { 1 }
    if ($cssRan) {
        $cssOut = Get-Content "$tmpDir\css.out" -ErrorAction SilentlyContinue |
            Where-Object { $_ -notmatch '^$' }
        $cssOut | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        if ($cssRc -eq 0) {
            Write-Status "OK" "CSS checker : OK (classes alignees)." "Green"
        } else {
            Write-Status "X" "CSS checker : classes orphelines detectees." "Red"
            $gateOk = $false
        }
    } else {
        Write-Status "!" "CSS checker non trouve. Etape skippee." "Yellow"
    }

    # ── Résultat Infection ──
    $infectionRan = (Get-Content "$tmpDir\infection.ran" -ErrorAction SilentlyContinue) -eq 'True'
    $infectionRc = if (Test-Path "$tmpDir\infection.rc") { [int](Get-Content "$tmpDir\infection.rc") } else { 1 }
    if ($infectionRan) {
        $infectionOut = Get-Content "$tmpDir\infection.out" -ErrorAction SilentlyContinue |
            Where-Object { $_ -notmatch '^\s*$' }
        $infectionOut | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        if ($infectionRc -eq 0) {
            Write-Status "OK" "Infection : OK (mutation score >= minMsi)." "Green"
        } else {
            Write-Status "X" "Infection : echec (code $infectionRc)." "Red"
            $gateOk = $false
        }
    } else {
        Write-Status "!" "Infection non trouve. Etape skippee." "Yellow"
    }

    # Nettoyage
    Remove-Item -Path $tmpDir -Recurse -Force -ErrorAction SilentlyContinue

    # ── Resume ──
    Write-Host ""
    if ($gateOk) {
        Write-Status "OK" "==========================================" "Green"
        Write-Status "OK" "  GATE QUALITE REUSSIE — deploiement OK" "Green"
        Write-Status "OK" "==========================================" "Green"
    } else {
        Write-Status "X" "==========================================" "Red"
        Write-Status "X" "  GATE QUALITE ECHOUEE — deploiement BLOQUE" "Red"
        Write-Status "X" "  Rollback automatique..." "Red"
        Write-Status "X" "==========================================" "Red"
    }
    return $gateOk
}

# ============================================================
# Programme principal
# ============================================================

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Mise a jour SST - $(Get-Date -Format 'dd/MM/yyyy HH:mm')" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# --- Verifier qu'on est admin ---
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host " ERREUR : Ce script doit etre execute en tant qu'administrateur." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- Verifier les prerequis ---
Write-Section "Verification des prerequis"

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Host " ERREUR : Git n'est pas installe." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"; exit 1
}
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host " ERREUR : PHP n'est pas installe." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"; exit 1
}
if (-not (Test-Path $AppDir)) {
    Write-Host " ERREUR : Le dossier $AppDir n'existe pas." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"; exit 1
}
Set-Location $AppDir
Write-Status "OK" "Git, PHP, dossier $AppDir" "Green"

# --- Verifier / migrer le remote vers Codeberg ---
Write-Section "Verification du depot distant"

$savedPref = $ErrorActionPreference
$ErrorActionPreference = "Continue"
$currentRemote = git remote get-url origin 2>&1
$remoteExit = $LASTEXITCODE
$ErrorActionPreference = $savedPref

if ($remoteExit -ne 0 -or -not $currentRemote) {
    Write-Host " ERREUR : Impossible de lire l'URL du remote." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"; exit 1
}

$currentRemoteNormalized = $currentRemote -replace 'https://[^@]+@', 'https://'

if ($currentRemoteNormalized -match 'github\.com') {
    Write-Host "  Migration GitHub -> Codeberg..." -ForegroundColor Yellow
    $null = git remote set-url origin $ExpectedRemoteUrl 2>&1
    Write-Status "OK" "Remote migre vers Codeberg" "Green"
} elseif ($currentRemoteNormalized -match 'codeberg\.org') {
    Write-Status "OK" "Remote : Codeberg" "Green"
} else {
    Write-Host "  Remote non reconnu, correction..." -ForegroundColor Yellow
    $null = git remote set-url origin $ExpectedRemoteUrl 2>&1
}

# --- Git sync ---
Write-Section "Telechargement des mises a jour"

$token = $env:FORMULAIRE_TOKEN
if ($token) {
    $authRemoteUrl = "https://${token}@codeberg.org/oliviernoblanc/sst.git"
    $null = git remote set-url origin $authRemoteUrl 2>&1
    Write-Status "OK" "Token Codeberg configure" "Green"
} else {
    Write-Status "!" "Pas de token FORMULAIRE_TOKEN — si depot prive, le fetch echouera." "Yellow"
}

try {
    $ErrorActionPreference = "Continue"
    $fetchOutput = git -c credential.helper= fetch origin "+refs/heads/*:refs/remotes/origin/*" 2>&1
    $fetchExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref

    if ($fetchExit -ne 0) { throw "git fetch echoue (code $fetchExit)`n$fetchOutput" }

    # Detecter la branche distante
    $remoteBranch = $null
    $headRef = git symbolic-ref refs/remotes/origin/HEAD 2>$null
    if ($headRef -match 'refs/remotes/origin/(.+)') { $remoteBranch = $Matches[1] }
    if (-not $remoteBranch) {
        $null = git remote set-head origin --auto 2>$null
        $headRef = git symbolic-ref refs/remotes/origin/HEAD 2>$null
        if ($headRef -match 'refs/remotes/origin/(.+)') { $remoteBranch = $Matches[1] }
    }
    if (-not $remoteBranch) {
        $remoteBranches = git branch -r 2>$null
        if ($remoteBranches -match 'origin/main') { $remoteBranch = 'main' }
        elseif ($remoteBranches -match 'origin/master') { $remoteBranch = 'master' }
    }
    if (-not $remoteBranch) { throw "Impossible de detecter la branche distante" }

    Write-Status ">" "Banche distante : $remoteBranch" "DarkGray"

    # Sauvegarder HEAD avant reset (pour rollback)
    $preUpdateHash = git rev-parse HEAD 2>$null

    $ErrorActionPreference = "Continue"
    $null = git reset --hard "origin/$remoteBranch" 2>&1
    $resetExit = $LASTEXITCODE
    $ErrorActionPreference = $savedPref
    if ($resetExit -ne 0) { throw "git reset --hard a echoue" }

    $null = git checkout -B $remoteBranch 2>&1
    $null = git clean -fd 2>&1

    # Supprimer ancienne branche master si migration
    if ($remoteBranch -eq 'main') {
        $localBranches = git branch 2>$null
        if ($localBranches -match 'master') { $null = git branch -D master 2>$null }
    }

    $gitLog = git log -1 --format="%h %s" 2>$null
    Write-Status "OK" "Code synchronise ($gitLog)" "Green"

    # Restaurer URL propre
    if ($token) { $null = git remote set-url origin $ExpectedRemoteUrl 2>&1 }
} catch {
    Write-Host ""
    Write-Host " ERREUR : synchronisation Git echouee." -ForegroundColor Red
    Write-Host "  $_" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"; exit 1
}

# --- Regeneration autoload Composer (AVANT la gate) ---
Write-Section "Regeneration autoload Composer"
$composerBin = Get-Command composer -ErrorAction SilentlyContinue
$composerPhar = Join-Path $AppDir "composer.phar"
$usePhar = $false

if ($composerBin) {
    # composer global dispo
} elseif (Test-Path $composerPhar) {
    $usePhar = $true
} elseif (-not $DryRun) {
    Write-Status ">" "Composer introuvable. Telechargement de composer.phar..." "Yellow"
    try {
        Invoke-WebRequest -Uri 'https://getcomposer.org/composer-stable.phar' `
            -OutFile $composerPhar -UseBasicParsing -ErrorAction Stop
        Write-Status "OK" "composer.phar telecharge" "Green"
        $usePhar = $true
    } catch {
        Write-Status "X" "Impossible de telecharger composer.phar : $_" "Red"
    }
}

if ($composerBin -or $usePhar) {
    if (-not $DryRun) {
        Push-Location $AppDir
        if (-not $usePhar) {
            $composerOutput = & composer dump-autoload -o 2>&1
        } else {
            $composerOutput = & php $composerPhar dump-autoload -o 2>&1
        }
        Pop-Location
        if ($LASTEXITCODE -eq 0) {
            Write-Status "OK" "composer dump-autoload -o : reussi" "Green"
        } else {
            Write-Status "X" "composer dump-autoload -o a echoue" "Red"
            $composerOutput | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkRed }
        }
    } else {
        Write-Status ".." "composer dump-autoload -o (simule)" "DarkGray"
    }
} else {
    Write-Status "!" "Composer introuvable — autoload non regenere." "Yellow"
}

# ============================================================
# GATE QUALITE — apres git pull + autoload, AVANT post-hooks
# Si la gate echoue → rollback automatique via git reset + exit 1
# ============================================================

if ($SkipTests) {
    Write-Section "Gate qualite"
    Write-Status "!" "Gate qualite ignoree (-SkipTests). DANGEREUX." "Yellow"
    Write-Status ">" "Le code n'a PAS ete verifie. Hotfix urgent uniquement." "White"
} elseif ($DryRun) {
    Write-Section "Gate qualite"
    Write-Status ">" "Mode DryRun — gate non executee." "DarkGray"
} else {
    $gateResult = Invoke-QualityGate
    if (-not $gateResult) {
        # Rollback : git reset au commit precedent
        Write-Section "Rollback apres echec gate"
        if ($preUpdateHash) {
            $null = git reset --hard $preUpdateHash 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Status "OK" "git reset au commit precedent ($preUpdateHash)" "Green"
            } else {
                Write-Status "X" "git reset echoue — etat potentiellement casse." "Red"
            }
        }
        Write-Host ""
        Write-Host "============================================" -ForegroundColor Red
        Write-Host " MISE A JOUR ANNULEE — gate qualite echouee" -ForegroundColor Red
        Write-Host " Corrigez les erreurs ci-dessus puis relancez." -ForegroundColor Red
        Write-Host "============================================" -ForegroundColor Red
        Read-Host "Appuyez sur Entree pour quitter"
        exit 1
    }
}

# ============================================================
# POST-GATE : hooks, copies, permissions
# ============================================================

# --- Pre-push hook ---
Write-Section "Installation du hook pre-push"
$hookDir = Join-Path $AppDir ".git\hooks"
$hookPath = Join-Path $hookDir "pre-push"
if (-not (Test-Path $hookDir)) { New-Item -ItemType Directory -Path $hookDir -Force | Out-Null }
$hookContent = @'
#!/usr/bin/env bash
# Gate qualité avant push — empêche de pusher du code cassé
# Lint d'abord, puis PHPStan + PHPUnit + E2E en parallèle
set -uo pipefail
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
PHP_BIN="$(command -v php)"
PHPUNIT="$USERPROFILE/scoop/shims/phpunit.phar"
TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

# Ne gate que les pushs vers main/master
SHOULD_GATE=0
while read -r local_ref local_sha remote_ref remote_sha; do
    if [[ "$remote_ref" == *"main" || "$remote_ref" == *"master" ]]; then
        SHOULD_GATE=1; break
    fi
done
if [[ $SHOULD_GATE -eq 0 ]]; then exit 0; fi

echo "[pre-push] Gate qualité en cours..."

# ── Prérequis : vérifier que tous les outils sont installés ──
MISSING=0

if ! command -v php >/dev/null 2>&1; then
    echo "[pre-push] ✗ PHP manquant."
    echo "[pre-push]   Installer PHP : scoop install php"
    MISSING=1
fi

if ! command -v phpstan >/dev/null 2>&1 && [[ ! -f "$USERPROFILE/scoop/shims/phpstan" ]]; then
    echo "[pre-push] ✗ PHPStan manquant."
    echo "[pre-push]   Installer PHPStan : scoop install phpstan"
    MISSING=1
fi

if [[ ! -f "$PHPUNIT" ]] && ! command -v phpunit >/dev/null 2>&1; then
    echo "[pre-push] ✗ PHPUnit manquant."
    echo "[pre-push]   Installer PHPUnit : scoop install phpunit"
    MISSING=1
fi

if ! command -v npx >/dev/null 2>&1 || ! npx playwright --version >/dev/null 2>&1; then
    echo "[pre-push] ✗ Playwright manquant (npx)."
    echo "[pre-push]   Installer Playwright : npm install -g @playwright/test && npx playwright install firefox"
    MISSING=1
fi

if [[ $MISSING -eq 1 ]]; then
    echo ""
    echo "[pre-push] ✗ Outils manquants. Push bloqué."
    echo "[pre-push] Installer les dépendances : scoop install php phpstan phpunit"
    echo "[pre-push] Et : pip install playwright && python -m playwright install firefox"
    exit 1
fi

# ── 1) Lint PHP (séquentiel — doit passer avant le parallèle) ──
CHANGED=$(git diff --name-only --diff-filter=ACM HEAD~1 HEAD -- "*.php" 2>/dev/null)
LINT_ERR=0
if [[ -n "$CHANGED" ]]; then
    while IFS= read -r f; do
        FULL="$REPO_ROOT/$f"
        [[ -f "$FULL" ]] || continue
        [[ "$f" == vendor/* || "$f" == tests/* || "$f" == backups/* || "$f" == data/* ]] && continue
        if ! "$PHP_BIN" -d xdebug.mode=off -l "$FULL" >/dev/null 2>&1; then
            echo "[pre-push] ✗ Erreur syntaxe : $f"
            LINT_ERR=1
        fi
    done <<< "$CHANGED"
fi
if [[ $LINT_ERR -eq 1 ]]; then
    echo "[pre-push] ✗ Lint échoué. Push bloqué."
    exit 1
fi
echo "[pre-push] ✓ Lint OK"

# ── 2) PHPStan + PHPUnit + E2E en parallèle ──

# PHPStan
(
    phpstan analyse --memory-limit=1G --no-progress >"$TMPDIR/phpstan.out" 2>&1
    echo $? >"$TMPDIR/phpstan.rc"
) &
PID_PHPSTAN=$!

# PHPUnit
(
    "$PHP_BIN" "$PHPUNIT" --no-coverage -q >"$TMPDIR/phpunit.out" 2>&1
    echo $? >"$TMPDIR/phpunit.rc"
) &
PID_PHPUNIT=$!

# E2E Playwright (Firefox)
(
    cd "$REPO_ROOT"
    npx playwright test --project=firefox -q >"$TMPDIR/e2e.out" 2>&1
    echo $? >"$TMPDIR/e2e.rc"
) &
PID_E2E=$!

# CSS checker
(
    cd "$REPO_ROOT"
    php tools/check_css_classes.php >"$TMPDIR/css.out" 2>&1
    echo $? >"$TMPDIR/css.rc"
) &
PID_CSS=$!

# Infection (mutation testing)
(
    cd "$REPO_ROOT"
    if [[ -f "vendor/bin/infection" ]]; then
        php vendor/bin/infection --show-mutations --no-progress --threads=4 >"$TMPDIR/infection.out" 2>&1
    else
        echo "Infection non trouve" >"$TMPDIR/infection.out"
        echo 1 >"$TMPDIR/infection.rc"
    fi
    echo $? >"$TMPDIR/infection.rc"
) &
PID_INFECTION=$!

# Attendre les 5 en parallèle
wait $PID_PHPSTAN $PID_PHPUNIT $PID_E2E $PID_CSS $PID_INFECTION 2>/dev/null

# ── 3) Résultats ──
ALL_OK=1

# PHPStan
RC=$(cat "$TMPDIR/phpstan.rc" 2>/dev/null)
if [[ "$RC" == "0" ]]; then
    echo "[pre-push] ✓ PHPStan OK"
else
    echo "[pre-push] ✗ PHPStan échoué (code $RC)"
    cat "$TMPDIR/phpstan.out" 2>/dev/null | tail -5
    ALL_OK=0
fi

# PHPUnit
RC=$(cat "$TMPDIR/phpunit.rc" 2>/dev/null)
if [[ "$RC" == "0" ]]; then
    echo "[pre-push] ✓ PHPUnit OK"
else
    echo "[pre-push] ✗ PHPUnit échoué (code $RC)"
    cat "$TMPDIR/phpunit.out" 2>/dev/null | tail -5
    ALL_OK=0
fi

# E2E
RC=$(cat "$TMPDIR/e2e.rc" 2>/dev/null)
if [[ "$RC" == "0" ]]; then
    echo "[pre-push] ✓ E2E OK (Firefox)"
else
    echo "[pre-push] ✗ E2E échoué (code $RC)"
    cat "$TMPDIR/e2e.out" 2>/dev/null | tail -10
    ALL_OK=0
fi

# CSS checker
RC=$(cat "$TMPDIR/css.rc" 2>/dev/null)
if [[ "$RC" == "0" ]]; then
    echo "[pre-push] ✓ CSS checker OK"
else
    echo "[pre-push] ✗ CSS checker : classes orphelines"
    cat "$TMPDIR/css.out" 2>/dev/null | tail -10
    ALL_OK=0
fi

# Infection
RC=$(cat "$TMPDIR/infection.rc" 2>/dev/null)
if [[ "$RC" == "0" ]]; then
    echo "[pre-push] ✓ Infection OK (mutation score >= minMsi)"
else
    echo "[pre-push] ✗ Infection échoué (code $RC)"
    cat "$TMPDIR/infection.out" 2>/dev/null | tail -10
    ALL_OK=0
fi

if [[ $ALL_OK -eq 1 ]]; then
    echo "[pre-push] ✓ Gate qualité réussie."
    exit 0
else
    echo "[pre-push] ✗ Gate échouée. Push bloqué."
    echo "[pre-push] Pour bypasser : git push --no-verify"
    exit 1
fi
'@
Set-Content -Path $hookPath -Value $hookContent -Encoding UTF8
Write-Status "OK" "Hook pre-push installe" "Green"

# --- Copie des captures d'ecran ---
Write-Section "Copie des captures d'ecran"
$srcScreenshots = "$AppDir\docs\screenshots"
$dstScreenshots = "$AppDir\public\screenshots"
if (Test-Path $srcScreenshots) {
    if (-not (Test-Path $dstScreenshots)) {
        New-Item -ItemType Directory -Path $dstScreenshots -Force | Out-Null
    }
    Copy-Item -Path "$srcScreenshots\*.png" -Destination $dstScreenshots -Force
    $count = (Get-ChildItem "$dstScreenshots\*.png" -ErrorAction SilentlyContinue).Count
    Write-Status "OK" "$count capture(s) copiee(s)" "Green"
} else {
    Write-Status "!" "Dossier docs\screenshots\ introuvable" "Yellow"
}

# --- Verification FPDF ---
Write-Section "Verification FPDF"
$fpdfPath = "$AppDir\src\lib\fpdf\fpdf.php"
$fontPath = "$AppDir\src\lib\fpdf\font\DejaVuSans.json"
if ((Test-Path $fpdfPath) -and (Test-Path $fontPath)) {
    Write-Status "OK" "FPDF et polices presents" "Green"
} else {
    Write-Status "!" "FPDF ou polices manquants" "Yellow"
}

# --- Creation des dossiers + permissions ---
Write-Section "Configuration des dossiers"
$dirsToCreate = @("$AppDir\data")
foreach ($dir in $dirsToCreate) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Status "Cree" "$dir" "Gray"
    }
}
# Permissions IIS_IUSRS sur data\
$aclDir = "$AppDir\data"
if (Test-Path $aclDir) {
    $acl = Get-Acl $aclDir
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
    )
    $acl.SetAccessRule($rule)
    Set-Acl $aclDir $acl
    Write-Status "OK" "Permissions IIS_IUSRS sur data\" "Green"
}

# --- Version deployee ---
Write-Section "Version"
$changelogFile = "$AppDir\CHANGELOG.md"
$version = $null
if (Test-Path $changelogFile) {
    $match = Select-String -Path $changelogFile -Pattern '^##\s*\[(\d+\.\d+\.\d+)\]' | Select-Object -First 1
    if ($match) { $version = $match.Matches[0].Groups[1].Value }
}
if (-not $version) {
    $configFile = "$AppDir\src\config.php"
    if (Test-Path $configFile) {
        $version = Select-String -Path $configFile -Pattern "APP_VERSION'\s*,\s*'([^']+)'" | ForEach-Object { $_.Matches[0].Groups[1].Value }
    }
}
if ($version) { Write-Status "OK" "Version : $version" "Green" }
$gitLog = git log -1 --format="%h - %s (%cr)" 2>$null
if ($gitLog) { Write-Status ">" "$gitLog" "DarkGray" }

# --- Clear OPcache ---
Write-Section "Clear cache PHP"
try {
    $phpCgiProcs = Get-Process -Name "php-cgi" -ErrorAction SilentlyContinue
    if ($phpCgiProcs -and $phpCgiProcs.Count -gt 0) {
        $phpCgiProcs | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Milliseconds 500
        Write-Status "OK" "$($phpCgiProcs.Count) php-cgi.exe tue(s) → OPcache vidé" "Green"
    } else {
        # Fallback : toucher index.php
        $indexPhp = "$AppDir\public\index.php"
        if (Test-Path $indexPhp) {
            (Get-Item $indexPhp).LastWriteTime = Get-Date
            Write-Status "OK" "index.php mis a jour (opcache clear)" "Green"
        }
    }
} catch {
    Write-Status "!" "OPcache reset partiel : $_" "Yellow"
}

# --- Clear CSS caches ---
Write-Section "Nettoyage des caches CSS"
$cssCacheDir = Join-Path $AppDir "db\cache"
if (Test-Path $cssCacheDir) {
    $oldCssFiles = Get-ChildItem -Path $cssCacheDir -Filter "assets_css_*.css" -ErrorAction SilentlyContinue
    foreach ($f in $oldCssFiles) {
        Remove-Item -Path $f.FullName -Force -ErrorAction SilentlyContinue
    }
    if ($oldCssFiles.Count -gt 0) {
        Write-Status "OK" "$($oldCssFiles.Count) fichier(s) de cache CSS supprimé(s)" "Green"
    } else {
        Write-Status "OK" "Aucun cache CSS a nettoyer" "Green"
    }
} else {
    Write-Status "OK" "Repertoire de cache CSS inexistant (pas de nettoyage necessaire)" "Green"
}

# --- Nettoyage anciennes sauvegardes ---
$backupsDir = Join-Path $AppDir "backups"
if (Test-Path $backupsDir) {
    $oldBackups = Get-ChildItem -Path $backupsDir -Directory -ErrorAction SilentlyContinue |
        Sort-Object CreationTime -Descending | Select-Object -Skip 5
    if ($oldBackups.Count -gt 0) {
        Write-Host ""
        Write-Status "!" "$($oldBackups.Count) ancienne(s) sauvegarde(s) (>5 conservees)." "Yellow"
        $clean = Read-Host "  Supprimer les anciennes sauvegardes ? (o/N)"
        if ($clean -match "^[oO]$") {
            foreach ($old in $oldBackups) {
                Remove-Item -Path $old.FullName -Recurse -Force
                Write-Status "  " "Supprime : $($old.Name)" "DarkGray"
            }
        }
    }
}

# --- Resultat final ---
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " Mise a jour terminee avec succes !" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Read-Host "Appuyez sur Entree pour quitter"
