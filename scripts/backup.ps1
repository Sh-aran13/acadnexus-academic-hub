# scripts/backup.ps1
# AcadNexus Database and File Backup Script (Windows PowerShell)

param(
    [string]$BackupDir = "g:\Projects\AcadNexus\backups",
    [string]$DbHost = "localhost",
    [string]$DbPort = "5432",
    [string]$DbName = "acadnexus",
    [string]$DbUser = "postgres",
    [string]$DbPass = "1328",
    [int]$RetentionDays = 14
)

$ErrorActionPreference = "Stop"

# Create backup directory
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$dbBackupFile = Join-Path $BackupDir "acadnexus_db_$timestamp.sql"
$filesBackupFile = Join-Path $BackupDir "acadnexus_uploads_$timestamp.zip"

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host " AcadNexus Automated Backup: $timestamp" -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan

# Locate pg_dump.exe
$pgDumpPaths = @(
    "E:\Java\Postgre\bin\pg_dump.exe",
    "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe",
    "C:\Program Files\PostgreSQL\17\bin\pg_dump.exe",
    "C:\Program Files\PostgreSQL\16\bin\pg_dump.exe"
)

$pgDump = $null
foreach ($p in $pgDumpPaths) {
    if (Test-Path $p) {
        $pgDump = $p
        break
    }
}

if (-not $pgDump) {
    $cmd = Get-Command "pg_dump" -ErrorAction SilentlyContinue
    if ($cmd) {
        $pgDump = $cmd.Source
    }
}

if (-not $pgDump) {
    Write-Host "[ERROR] pg_dump executable could not be found!" -ForegroundColor Red
    exit 1
}

Write-Host "[1/3] Dumping PostgreSQL database to: $dbBackupFile" -ForegroundColor Yellow
$env:PGPASSWORD = $DbPass

try {
    & $pgDump -h $DbHost -p $DbPort -U $DbUser -d $DbName --format=plain --no-owner --clean --if-exists -f $dbBackupFile
    if ($LASTEXITCODE -eq 0 -and (Test-Path $dbBackupFile)) {
        $size = (Get-Item $dbBackupFile).Length
        Write-Host "      Database backup completed successfully ($([math]::Round($size / 1KB, 2)) KB)" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] pg_dump returned exit code $LASTEXITCODE" -ForegroundColor Red
        exit 1
    }
} finally {
    $env:PGPASSWORD = $null
}

# 2. Backup uploads folder
Write-Host "[2/3] Archiving uploads directory to: $filesBackupFile" -ForegroundColor Yellow
$uploadsPath = "g:\Projects\AcadNexus\uploads"
if (Test-Path $uploadsPath) {
    Compress-Archive -Path "$uploadsPath\*" -DestinationPath $filesBackupFile -Force
    $zipSize = (Get-Item $filesBackupFile).Length
    Write-Host "      Uploads archive completed successfully ($([math]::Round($zipSize / 1KB, 2)) KB)" -ForegroundColor Green
} else {
    Write-Host "      [WARNING] Uploads directory not found, skipping file archive." -ForegroundColor DarkYellow
}

# 3. Clean up old backups based on retention policy
Write-Host "[3/3] Enforcing retention policy: keeping last $RetentionDays days of backups" -ForegroundColor Yellow
$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -Path $BackupDir -File | Where-Object { $_.LastWriteTime -lt $cutoff } | ForEach-Object {
    Write-Host "      Purging expired backup: $($_.Name)" -ForegroundColor Gray
    Remove-Item $_.FullName -Force
}

Write-Host "====================================================" -ForegroundColor Green
Write-Host " Backup routine finished successfully!" -ForegroundColor Green
Write-Host "====================================================" -ForegroundColor Green
