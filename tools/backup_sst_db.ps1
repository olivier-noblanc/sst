# ============================================================
# backup_sst_db.ps1 — Sauvegarde de la base SQLite SST
#
# Ce script effectue :
#   1. Vérification des prérequis (PHP)
#   2. Checkpoint WAL (via PHP/SQLite) pour s'assurer que
#      la base est dans un état cohérent
#   3. Copie horodatée de data\sst.db
#   4. Nettoyage des sauvegardes de plus de 30 jours
#
# Emplacement : C:\inetpub\sst\tools\backup_sst_db.ps1
# Utilisation (planificateur de tâches Windows) :
#   powershell -ExecutionPolicy Bypass -File C:\inetpub\sst\tools\backup_sst_db.ps1
#
# Recommandation : planifier une exécution quotidienne
# via le Planificateur de tâches Windows.
# ============================================================

$ErrorActionPreference = "Stop"
$AppDir = "C:\inetpub\sst"
$BackupDir = "$AppDir\data\backups"
$DbPath = "$AppDir\data\sst.db"
$RetentionDays = 30

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Sauvegarde SST DB - $(Get-Date -Format 'dd/MM/yyyy HH:mm')" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# --- Vérifier qu'on est admin ---
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host " AVERTISSEMENT : Ce script n'est pas exécuté en tant qu'administrateur." -ForegroundColor DarkYellow
    Write-Host "                La sauvegarde fonctionnera si les permissions sont correctes." -ForegroundColor DarkYellow
    Write-Host ""
}

# --- Vérifier les prérequis ---
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host " ERREUR : PHP n'est pas installe ou pas dans le PATH." -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

if (-not (Test-Path $DbPath)) {
    Write-Host " ERREUR : La base de donnees n'existe pas : $DbPath" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- Créer le dossier de sauvegarde ---
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    Write-Host " Dossier de sauvegarde cree : $BackupDir" -ForegroundColor Gray
}

# --- 1. WAL Checkpoint ---
Write-Host "[1/3] Checkpoint WAL (SQLite)..." -ForegroundColor Yellow

try {
    $checkpointScript = @"
<?php
\$db = new PDO('sqlite:$DbPath');
\$db->exec('PRAGMA wal_checkpoint(FULL)');
echo 'OK';
"@
    $checkpointScript | Set-Content -Path "$env:TEMP\sst_checkpoint.php" -Encoding UTF8
    $result = php "$env:TEMP\sst_checkpoint.php" 2>&1
    Remove-Item "$env:TEMP\sst_checkpoint.php" -ErrorAction SilentlyContinue

    if ($result -eq 'OK') {
        Write-Host "  OK : Checkpoint WAL effectue" -ForegroundColor Green
    } else {
        Write-Host "  AVERTISSEMENT : Le checkpoint WAL a retourne : $result" -ForegroundColor DarkYellow
        Write-Host "  La sauvegarde continue (la base peut ne pas etre totalement coherent)." -ForegroundColor DarkYellow
    }
}
catch {
    Write-Host "  AVERTISSEMENT : Impossible d'effectuer le checkpoint WAL." -ForegroundColor DarkYellow
    Write-Host "  Detail : $($_.Exception.Message)" -ForegroundColor DarkYellow
    Write-Host "  La sauvegarde continue." -ForegroundColor DarkYellow
}

# --- 2. Copie horodatée ---
Write-Host ""
Write-Host "[2/3] Copie de la base de donnees..." -ForegroundColor Yellow

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$backupFile = "$BackupDir\sst_db_$timestamp.db"

try {
    Copy-Item -Path $DbPath -Destination $backupFile -Force
    $size = (Get-Item $backupFile).Length / 1KB
    Write-Host "  OK : Sauvegarde creee : sst_db_$timestamp.db ($([math]::Round($size, 1)) Ko)" -ForegroundColor Green
}
catch {
    Write-Host "  ERREUR : Impossible de copier la base de donnees." -ForegroundColor Red
    Write-Host "  Detail : $($_.Exception.Message)" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour quitter"
    exit 1
}

# --- 3. Nettoyage des sauvegardes anciennes ---
Write-Host ""
Write-Host "[3/3] Nettoyage des sauvegardes de plus de $RetentionDays jours..." -ForegroundColor Yellow

$cutoff = (Get-Date).AddDays(-$RetentionDays)
$oldBackups = Get-ChildItem -Path $BackupDir -Filter "sst_db_*.db" | Where-Object { $_.LastWriteTime -lt $cutoff }

if ($oldBackups.Count -gt 0) {
    foreach ($old in $oldBackups) {
        Remove-Item $old.FullName -Force
        Write-Host "  Supprime : $($old.Name)" -ForegroundColor Gray
    }
    Write-Host "  OK : $($oldBackups.Count) sauvegarde(s) ancienne(s) supprimee(s)" -ForegroundColor Green
} else {
    Write-Host "  OK : Aucune sauvegarde ancienne a nettoyer" -ForegroundColor Green
}

# --- Résumé ---
$backupCount = (Get-ChildItem -Path $BackupDir -Filter "sst_db_*.db").Count
$totalSize = [math]::Round(((Get-ChildItem -Path $BackupDir -Filter "sst_db_*.db" | Measure-Object -Property Length -Sum).Sum / 1MB), 1)

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " Sauvegarde terminee avec succes !" -ForegroundColor Green
Write-Host " Dossier : $BackupDir" -ForegroundColor Green
Write-Host " Sauvegardes : $backupCount fichier(s), $totalSize Mo au total" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""

# Si exécuté manuellement (pas par le planificateur), attendre une touche
if ([Environment]::UserInteractive) {
    Read-Host "Appuyez sur Entree pour quitter"
}
