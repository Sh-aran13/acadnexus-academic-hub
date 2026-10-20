#!/usr/bin/env bash
# scripts/backup.sh
# AcadNexus Database and File Backup Script (Linux/macOS)

set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-$(pwd)/backups}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-acadnexus}"
DB_USER="${DB_USER:-postgres}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_BACKUP_FILE="${BACKUP_DIR}/acadnexus_db_${TIMESTAMP}.sql"
FILES_BACKUP_FILE="${BACKUP_DIR}/acadnexus_uploads_${TIMESTAMP}.tar.gz"

mkdir -p "${BACKUP_DIR}"

echo "===================================================="
echo " AcadNexus Automated Backup: ${TIMESTAMP}"
echo "===================================================="

# 1. PostgreSQL Database Dump
echo "[1/3] Dumping PostgreSQL database to: ${DB_BACKUP_FILE}"
pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" \
    --format=plain --no-owner --clean --if-exists -f "${DB_BACKUP_FILE}"

echo "      Database dump complete ($(du -h "${DB_BACKUP_FILE}" | cut -f1))"

# 2. Uploads Directory Archive
echo "[2/3] Archiving uploads to: ${FILES_BACKUP_FILE}"
if [ -d "uploads" ]; then
    tar -czf "${FILES_BACKUP_FILE}" -C "uploads" .
    echo "      Uploads archive complete ($(du -h "${FILES_BACKUP_FILE}" | cut -f1))"
else
    echo "      [WARNING] uploads directory not found."
fi

# 3. Retention policy
echo "[3/3] Enforcing ${RETENTION_DAYS}-day backup retention policy"
find "${BACKUP_DIR}" -type f -name "acadnexus_*" -mtime +"${RETENTION_DAYS}" -delete

echo "===================================================="
echo " Backup routine finished successfully!"
echo "===================================================="
