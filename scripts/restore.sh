#!/usr/bin/env bash
# scripts/restore.sh
# AcadNexus Database and File Restore Script (Linux/macOS)

set -euo pipefail

SQL_FILE="${1:-}"
TARGET_DB="${2:-acadnexus_restore_test}"
ZIP_FILE="${3:-}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USER:-postgres}"

if [ -z "${SQL_FILE}" ]; then
    echo "Usage: ./scripts/restore.sh <backup_file.sql> [target_database] [uploads_archive.tar.gz]"
    exit 1
fi

if [ ! -f "${SQL_FILE}" ]; then
    echo "[ERROR] SQL file not found: ${SQL_FILE}"
    exit 1
fi

echo "===================================================="
echo " AcadNexus Database Restore Utility"
echo "===================================================="
echo " Target Database: ${TARGET_DB}"
echo " Source SQL File: ${SQL_FILE}"

if [ "${TARGET_DB}" = "acadnexus" ]; then
    echo "[SAFETY WARNING] You are restoring directly into the primary database 'acadnexus'!"
    read -rp "Type 'RESTORE' to proceed: " CONFIRM
    if [ "${CONFIRM}" != "RESTORE" ]; then
        echo "Restore cancelled by user."
        exit 0
    fi
fi

# 1. Prepare target DB
echo "[1/3] Ensuring database '${TARGET_DB}' exists..."
psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='${TARGET_DB}'" | grep -q 1 || \
    psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d postgres -c "CREATE DATABASE \"${TARGET_DB}\""

# 2. Restore SQL dump
echo "[2/3] Restoring database schema and data..."
psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TARGET_DB}" -q -f "${SQL_FILE}"
echo "      Database restored successfully."

# 3. Restore files
if [ -n "${ZIP_FILE}" ] && [ -f "${ZIP_FILE}" ]; then
    echo "[3/3] Restoring uploads archive..."
    mkdir -p "uploads"
    tar -xzf "${ZIP_FILE}" -C "uploads"
    echo "      Uploads restored successfully."
else
    echo "[3/3] No files archive specified, skipping uploads restore."
fi

TABLE_COUNT=$(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TARGET_DB}" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'")
echo "===================================================="
echo " Verification: Database contains ${TABLE_COUNT// /} public tables."
echo " Restore verification passed!"
echo "===================================================="
