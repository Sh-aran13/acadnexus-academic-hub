# AcadNexus Core Security & Data Integrity Upgrade — Implementation Report

**System Version:** AcadNexus 2.0 (Security & Data Integrity Hardened)  
**Environment:** PHP 8.5.7, PostgreSQL 18.1, Apache/Nginx compatible  
**Audit & Implementation Status:** Completed & 100% Verified  

---

## 1. Executive Summary

The AcadNexus Academic Management System has undergone an end-to-end security, architecture, and data integrity transformation. All critical vulnerabilities—including unauthenticated document downloads, missing CSRF protection, unparameterized database queries, client-trusting profile privilege escalation, dangerous script upload avenues, unconstrained marks/credits input, and lack of migration/backup automation—have been resolved.

All 19 automated integration and unit test workflows pass with a **100% success rate**.

---

## 2. Foundational Pillars: Summary of Implementation

### Pillar 1: Complete Authentication & Authorization (RBAC)
- **Centralized Session Hardening (`config/auth.php`):**
  - Session cookies configured with `httponly=true`, `samesite=Lax`, and conditional `secure` flags.
  - Active 2-hour session inactivity timeout with automatic invalidation.
  - Live database session verification (`validateSessionUser()`) on every authenticated request to guarantee that suspended, deactivated, or role-modified users are instantly locked out.
- **Role-Based Access Control:**
  - `requireRole($roles)` supports single or multiple roles (`student`, `faculty`, `admin`) with safe dashboard fallbacks.
  - Granular ownership verification functions:
    - `isFacultyAssignedToCourse($facultyId, $courseId)`
    - `isStudentEnrolledInCourse($studentId, $courseId)`
    - `canFacultyManageAssignment($facultyId, $assignmentId)`
    - `canAccessSubmission($userId, $role, $submissionId)`
    - `canAccessMaterial($userId, $role, $materialId)`
    - `canAccessProject($userId, $role, $projectId)`
    - `canAccessAppointment($userId, $role, $appointmentId)`
- **Brute Force & Login Throttling:**
  - Login attempts recorded in `login_attempts` table with client IP and identifier tracking.
  - Enforces exponential lockout after 5 consecutive failed attempts within a 15-minute window (`checkLoginThrottling()` and `recordLoginAttempt()` with strict boolean binding).
- **CSRF Token Lifecycle:**
  - Cryptographically secure 32-byte tokens generated via `random_bytes(32)` (`generateCSRFToken()`).
  - Strict timing-attack-safe validation via `hash_equals()` (`verifyCSRFToken()`, `requireCSRF()`).
  - Integrated into all state-altering forms across all portals (`csrfField()`).
  - Converted unsafe `GET` deletions (materials, announcements, students, faculty, users) to secure `POST` forms protected by CSRF tokens.

### Pillar 2: Complete CRUD Operations & Server-Side Validation
- **Input Bounds & Type Validation:**
  - Standardized `Validator` class (`config/functions.php`) with explicit nullable PHP 8.4+ compatible signatures (`?string $label = null`).
  - Validates email formatting, integer/float ranges, string length minimums and maximums, regex constraints, and required fields.
  - Faculty marks entry strictly bounds scores between 0 and 100 with individual component limits.
  - Student profile updates strictly allow-listed to contact info (`phone`, `address`, `bio`, `emergency_contact`, `date_of_birth`, `gender`), preventing tampering with `cgpa`, `department_id`, `program`, or `admission_year`.
  - Administrative deletion operations guard against self-deletion/self-deactivation and cascade constraint violations.
- **SQL Injection Prevention:**
  - 100% of dynamic queries converted to parameterized PDO prepared statements.
  - Eliminated raw variable interpolation in query strings (e.g. `course_id`, `department_id`, and `student_id`).
  - Enforced Post-Redirect-Get (PRG) pattern across all form submissions to prevent duplicate execution on refresh.

### Pillar 3: Secure Document & File Management
- **Centralized File Service (`config/file_service.php`):**
  - Category-based configurations (`avatar`, `material`, `submission`, `assignment`, `announcement`, `project`) specifying maximum size limits and strict extension allowlists.
  - Two-stage MIME sniffing: checks PHP `finfo` if available, combined with a built-in binary magic-byte validator (`PDF`, `PNG`, `JPEG`, `GIF`) to guard against MIME spoofing even in minimal CLI/server environments.
  - Generates cryptographically randomized file storage names (`doc_` + `bin2hex(random_bytes(16))` + extension) to prevent directory enumeration, filename collision, and path traversal.
  - Base64 image decoding (`FileService::uploadBase64Image()`) for camera snapshots with byte-size and dimension checks.
  - Safe deletion (`FileService::delete()`) strictly confined to the `uploads/` directory with `realpath()` path traversal defenses.
- **Directory Execution Prevention (`uploads/.htaccess`):**
  - Explicitly disables script execution for `.php`, `.phtml`, `.phar`, `.pl`, `.py`, `.cgi`, `.sh`, `.exe`, `.asp`, `.js`.
  - Sets `X-Content-Type-Options: nosniff` header.
- **Authenticated Download Gateway (`download.php`):**
  - Direct file links replaced with `download.php?type={type}&id={id}`.
  - Authenticates session and enforces role and resource-level access control before streaming files.
  - Streams files using chunked output with `X-Content-Type-Options: nosniff`, `Content-Disposition: attachment`, and private cache headers.

### Pillar 4: Database Integrity, Migrations, and Backups
- **Database Connection Hardening (`config/database.php`):**
  - Parses `.env` if available, falls back to `getenv()`, and defaults securely to local configuration.
  - PDO configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`, and query timeout parameters.
- **Versioned Migration Runner (`database/migrate.php`):**
  - Tracks applied migrations in `schema_migrations` table (`id`, `migration_name`, `applied_at`, `execution_time_ms`).
  - Supports `--status` inspection and transactional rollbacks on error.
  - Applied migrations:
    1. `001_baseline_schema.sql`: Reconciled baseline tables and schema synchronization.
    2. `002_integrity_constraints_and_indexes.sql`: Check constraints on marks bounds (0-100), course credits (> 0), assignment max marks (> 0), attendance and appointment statuses; high-performance foreign key indexes.
    3. `003_login_throttling_table.sql`: `login_attempts` tracking table.
- **Automated Backup & Recovery System:**
  - PowerShell (`scripts/backup.ps1`, `scripts/restore.ps1`) and Bash (`scripts/backup.sh`, `scripts/restore.sh`) scripts.
  - Backs up PostgreSQL schema and data via `pg_dump` and compresses `uploads/` directory into zip archives.
  - Validated restore drill into isolated test database `acadnexus_restore_test` confirming 21 tables and relationships.
  - Complete operational guide documented in `BACKUP_AND_RECOVERY.md`.

---

## 3. Automated Verification Results

The automated test runner (`tests/run_tests.php`) executes 19 comprehensive tests covering security, validation, file operations, and application workflows:

| Category | Test Name | Status |
| :--- | :--- | :---: |
| **Auth** | CSRF token generation and validation | **PASS** |
| **Auth** | Login rate limiting and throttling | **PASS** |
| **Auth** | Session user validation against database | **PASS** |
| **Authorization** | Faculty course assignment check | **PASS** |
| **Authorization** | Student course enrollment check | **PASS** |
| **Authorization** | Submission ownership check | **PASS** |
| **FileService** | Rejects dangerous PHP script upload | **PASS** |
| **FileService** | Rejects MIME spoofing (PHP script disguised as .png) | **PASS** |
| **FileService** | Valid Base64 image decode, storage and deletion | **PASS** |
| **FileService** | Path traversal defense in delete() | **PASS** |
| **Database** | Check constraint: marks bounds (0-100) | **PASS** |
| **Database** | Check constraint: negative course credits rejected | **PASS** |
| **Database** | Migrations tracker table integrity | **PASS** |
| **Workflow A** | Role-Based Navigation & Session Protection | **PASS** |
| **Workflow B** | Course Creation and Faculty Department Isolation | **PASS** |
| **Workflow C** | Marks insertion and attendance validation | **PASS** |
| **Workflow D** | Assignment creation and submission flow | **PASS** |
| **Workflow E** | Student appointment booking and faculty confirmation | **PASS** |
| **Workflow F** | Activity logging and user lifecycle auditability | **PASS** |

**Overall Score:** 19 / 19 (100% Success Rate)

---

## 4. Key Files Created and Modified

### Core Infrastructure & Security
- `config/auth.php` (Session validation, RBAC, CSRF, rate-limiting, resource-level ownership)
- `config/file_service.php` (Centralized FileService with MIME/magic-byte sniffing)
- `config/functions.php` (Validator class, output escaping `e()`, FileService bridge)
- `config/database.php` (Hardened PDO connection, .env loader)
- `download.php` (Authenticated download gateway)
- `uploads/.htaccess` (Script execution blocking, nosniff headers)

### Database & Migrations
- `database/migrate.php` (Versioned migration runner)
- `database/migrations/001_baseline_schema.sql` (Baseline synchronization)
- `database/migrations/002_integrity_constraints_and_indexes.sql` (Check constraints & indexes)
- `database/migrations/003_login_throttling_table.sql` (Login throttling table)
- `database.sql` (Updated baseline installer)

### Backup & Disaster Recovery
- `scripts/backup.ps1` & `scripts/backup.sh` (Database & file backup automation)
- `scripts/restore.ps1` & `scripts/restore.sh` (Database & file restore automation)
- `BACKUP_AND_RECOVERY.md` (Disaster recovery SOP and runbooks)

### Portal Hardening & CRUD
- **Faculty:** `attendance.php`, `marks.php`, `assignments.php`, `assignment_create.php`, `assignment_submissions.php`, `materials.php`, `announcements.php`, `appointments.php`, `projects.php`, `course_create.php`, `settings.php`, `register.php`.
- **Student:** `materials.php`, `assignment_view.php`, `courses.php`, `profile.php`, `appointments.php`, `projects.php`, `settings.php`, `register.php`.
- **Admin:** `courses.php`, `departments.php`, `students.php`, `faculty.php`, `users.php`, `announcements.php`, `projects.php`, `settings.php`.
- **Global:** `login.php`, `logout.php`.

### Verification Suite
- `tests/run_tests.php` (Automated security and workflow verification suite)
