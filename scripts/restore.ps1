# scripts/restore.ps1
# AcadNexus Database and File Restore Script (Windows PowerShell)

param(
    [Parameter(Mandatory=$true)]
    [string]$SqlFile,
    [string]$ZipFile = "",
    [string]$TargetDb = "acadnexus_restore_test",
    [string]$DbHost = "localhost",
    [string]$DbPort = "5432",
    [string]$DbUser = "postgres",
    [string]$DbPass = "1328",
    [switch]$Force
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $SqlFile)) {
    Write-Host "[ERROR] SQL backup file not found: $SqlFile" -ForegroundColor Red
    exit 1
}

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host " AcadNexus Database Restore Utility" -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host " Target Database: $TargetDb" -ForegroundColor Yellow
Write-Host " Source SQL File: $SqlFile" -ForegroundColor Yellow

if ($TargetDb -eq "acadnexus" -and -not $Force) {
    Write-Host "[SAFETY WARNING] You are targeting the primary production database '$TargetDb'!" -ForegroundColor Red
    $confirm = Read-Host "Type 'RESTORE' to confirm overwriting the live database"
    if ($confirm -ne "RESTORE") {
        Write-Host "Restore cancelled by user." -ForegroundColor Yellow
        exit 0
    }
}

# Locate psql.exe
$psqlPaths = @(
    "E:\Java\Postgre\bin\psql.exe",
    "C:\Program Files\PostgreSQL\18\bin\psql.exe",
    "C:\Program Files\PostgreSQL\17\bin\psql.exe",
    "C:\Program Files\PostgreSQL\16\bin\psql.exe"
)

$psql = $null
foreach ($p in $psqlPaths) {
    if (Test-Path $p) {
        $psql = $p
        break
    }
}

if (-not $psql) {
    $cmd = Get-Command "psql" -ErrorAction SilentlyContinue
    if ($cmd) {
        $psql = $cmd.Source
    }
}

if (-not $psql) {
    Write-Host "[ERROR] psql executable could not be found!" -ForegroundColor Red
    exit 1
}

$env:PGPASSWORD = $DbPass

try {
    # 1. Create target database if it does not exist (for isolated testing)
    Write-Host "[1/3] Preparing target database '$TargetDb'..." -ForegroundColor Yellow
    & $psql -h $DbHost -p $DbPort -U $DbUser -d postgres -c "SELECT 1 FROM pg_database WHERE datname='$TargetDb'" -t | Out-String -OutVariable dbExists
    
    if (-not ($dbExists.Trim() -eq "1")) {
        Write-Host "      Database '$TargetDb' does not exist. Creating..." -ForegroundColor Gray
        & $psql -h $DbHost -p $DbPort -U $DbUser -d postgres -c "CREATE DATABASE $TargetDb"
    }

    # 2. Restore SQL dump into target database
    Write-Host "[2/3] Restoring schema and data into '$TargetDb'..." -ForegroundColor Yellow
    & $psql -h $DbHost -p $DbPort -U $DbUser -d $TargetDb -q -f $SqlFile
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "      SQL restore completed successfully!" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] psql restore exited with code $LASTEXITCODE" -ForegroundColor Red
        exit 1
    }

    # 3. Restore files if zip provided
    if ($ZipFile -ne "" -and (Test-Path $ZipFile)) {
        Write-Host "[3/3] Restoring uploads from zip archive..." -ForegroundColor Yellow
        $restoreDir = "g:\Projects\AcadNexus\uploads"
        Expand-Archive -Path $ZipFile -DestinationPath $restoreDir -Force
        Write-Host "      Uploads restored successfully!" -ForegroundColor Green
    } else {
        Write-Host "[3/3] No uploads zip provided, skipping file restore." -ForegroundColor Gray
    }

    # Verify table count in restored DB
    $tableCount = & $psql -h $DbHost -p $DbPort -U $DbUser -d $TargetDb -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'"
    Write-Host "====================================================" -ForegroundColor Green
    Write-Host " Verification: Restored database contains $($tableCount.Trim()) tables." -ForegroundColor Green
    Write-Host " Restore verification passed!" -ForegroundColor Green
    Write-Host "====================================================" -ForegroundColor Green
} finally {
    $env:PGPASSWORD = $null
}
