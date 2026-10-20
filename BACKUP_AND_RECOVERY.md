# AcadNexus Backup, Recovery, and Disaster Recovery Procedures

## 1. Overview
This document specifies the official backup, retention, and disaster recovery procedures for the **AcadNexus Academic Management System**. 

The system state is composed of two independent persistent resources:
1. **Relational Database**: PostgreSQL containing users, courses, grades, enrollments, attendance, announcements, and activity logs.
2. **Persistent Document Storage**: The `uploads/` directory containing course lecture slides, student submissions, circular documents, and profile avatars.

---

## 2. Backup Strategy

### Automated Backup Script
Run the automated backup routine via PowerShell (Windows) or Bash (Linux):

#### Windows PowerShell:
```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup.ps1
```
*Optional parameters*:
- `-BackupDir <path>`: Custom destination directory (defaults to `.\backups`).
- `-RetentionDays <int>`: Retention period in days (defaults to `14`).
- `-DbHost <host>`, `-DbPort <port>`, `-DbUser <user>`, `-DbPass <pass>`.

#### Linux / macOS:
```bash
./scripts/backup.sh
```

### What the Backup Routine Performs
1. **Consistent Database Dump**:
   - Uses `pg_dump` with `--clean --if-exists --no-owner` to produce clean, reproducible SQL dumps.
   - Credentials are piped via the secure environment variable `PGPASSWORD` (never passed directly on the CLI command line where process inspection tools could expose it).
2. **Document Store Archive**:
   - Compresses the contents of `uploads/` into a timestamped zip archive (`.tar.gz` on Linux).
3. **Retention Policy Enforcement**:
   - Automatically prunes backup archives older than the specified retention window (default: 14 days).

---

## 3. Disaster Recovery and Restore Procedures

> [!IMPORTANT]
> **Difference between Backup Creation and Recovery Verification**:
> A backup file existing on disk does **not** prove recoverability. A recovery process is only proven when the backup file is successfully restored into an isolated database, verified for referential integrity, and checked for valid table/row counts.

### Restoring into an Isolated Test Environment (Recommended for drills)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore.ps1 -SqlFile "backups\acadnexus_db_YYYYMMDD_HHMMSS.sql" -TargetDb "acadnexus_restore_test"
```

The script will:
1. Verify source file existence.
2. Ensure the target database exists (or create it safely).
3. Execute the SQL restore using `psql`.
4. Run automated integrity verification checking that all tables exist and report the public table count.

### Restoring to Production Database
```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore.ps1 -SqlFile "backups\acadnexus_db_YYYYMMDD_HHMMSS.sql" -TargetDb "acadnexus"
```
*Note*: Restoring directly to the production database prompts for explicit user confirmation (`RESTORE`) to prevent accidental overwrites.

---

## 4. Periodic Recovery Checklist

Execute this drill at least monthly:
1. [ ] Generate fresh backup using `scripts/backup.ps1`.
2. [ ] Restore the SQL file into `acadnexus_drill_db`.
3. [ ] Query user counts, marks, enrollments, and check migration history:
   ```sql
   SELECT count(*) FROM users;
   SELECT count(*) FROM marks;
   SELECT version, migration_name FROM schema_migrations;
   ```
4. [ ] Verify random student submission and lecture material downloads via `download.php`.
5. [ ] Drop `acadnexus_drill_db` after drill sign-off.
