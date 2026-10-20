<?php
// faculty/profile.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');

/* ------------------------------------------------------------------
   Unique 7-Character Faculty ID Code (FAC + 4 Digits e.g. FAC4829)
------------------------------------------------------------------- */
$currentFacId = trim($faculty['faculty_id_number'] ?? '');
if (empty($currentFacId) || !preg_match('/^FAC\d{4}$/i', $currentFacId)) {
    do {
        $randDigits = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $newFacCode = 'FAC' . $randDigits;
        $chk = $pdo->prepare("SELECT COUNT(*) FROM faculty_profiles WHERE faculty_id_number = ?");
        $chk->execute([$newFacCode]);
        $exists = $chk->fetchColumn();
    } while ($exists > 0);

    // Save permanently in database
    $upd = $pdo->prepare("UPDATE faculty_profiles SET faculty_id_number = ? WHERE id = ?");
    $upd->execute([$newFacCode, $faculty['id']]);
    $faculty['faculty_id_number'] = $newFacCode;
    $currentFacId = $newFacCode;
}

$facultyIdCode  = strtoupper($currentFacId);
$departmentName = !empty($faculty['department_name']) ? $faculty['department_name'] : 'Computer Science & Engineering';

/* ------------------------------------------------------------------
   Profile photo upload (FileService + CSRF + drag-and-drop)
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    requireCSRF();
    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = FileService::upload($_FILES['avatar'], 'avatar', $userId);

        if ($uploadResult['success']) {
            $avatarPath = $uploadResult['file_path'];
            // Delete old avatar if present
            if (!empty($faculty['avatar'])) {
                FileService::delete($faculty['avatar']);
            }
            $updAv = $pdo->prepare("UPDATE faculty_profiles SET avatar = ? WHERE user_id = ?");
            $updAv->execute([$avatarPath, $userId]);
            setToast('success', 'Profile photo updated successfully!');
            header("Location: profile.php");
            exit();
        } else {
            setToast('error', $uploadResult['error']);
        }
    } else {
        setToast('error', 'Choose a PNG, JPG or JPEG image to upload.');
    }
}

/* ------------------------------------------------------------------
   Faculty profile details update
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['avatar'])) {
    requireCSRF();
    $firstName      = trim($_POST['first_name'] ?? '');
    $lastName       = trim($_POST['last_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $designation    = trim($_POST['designation'] ?? '');
    $qualification  = trim($_POST['qualification'] ?? '');
    $officeRoom     = trim($_POST['office_room'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experienceYears = max(0, min(50, (int)($_POST['experience_years'] ?? 0)));

    if (empty($firstName) || empty($lastName)) {
        setToast('error', 'First name and last name are required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE faculty_profiles
                SET first_name = ?, last_name = ?, phone = ?, designation = ?,
                    qualification = ?, office_room = ?, specialization = ?, experience_years = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $firstName, $lastName, $phone, $designation,
                $qualification, $officeRoom, $specialization, $experienceYears,
                $userId
            ]);

            setToast('success', 'Faculty profile details saved.');
            header("Location: profile.php");
            exit();
        } catch (Exception $e) {
            setToast('error', 'Could not save your profile: ' . $e->getMessage());
        }
    }
}

/* ------------------------------------------------------------------
   Data for display
------------------------------------------------------------------- */
$faculty = getUserProfile($userId, 'faculty');
$facultyId = (int)($faculty['id'] ?? 0);

// Fetch allocated teaching courses
$dbCourses = [];
if ($facultyId > 0) {
    $cStmt = $pdo->prepare("
        SELECT c.code as course_code, c.name as course_name, c.credits, fc.semester, fc.academic_year,
               COUNT(DISTINCT ce.student_id) as enrolled_students
        FROM faculty_courses fc
        JOIN courses c ON fc.course_id = c.id
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id
        WHERE fc.faculty_id = ?
        GROUP BY c.id, c.code, c.name, c.credits, fc.semester, fc.academic_year
        ORDER BY c.code ASC
        LIMIT 6
    ");
    $cStmt->execute([$facultyId]);
    $dbCourses = $cStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch mentored capstone / research projects
$dbProjects = [];
if ($facultyId > 0) {
    $pStmt = $pdo->prepare("
        SELECT p.id, p.title, p.technologies, p.description, p.status, p.progress_percent,
               sp.first_name as stu_fn, sp.last_name as stu_ln, sp.student_id_number, sp.reg_number
        FROM projects p
        LEFT JOIN student_profiles sp ON p.student_id = sp.id
        WHERE p.faculty_advisor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 4
    ");
    $pStmt->execute([$facultyId]);
    $dbProjects = $pStmt->fetchAll(PDO::FETCH_ASSOC);
}

$firstNameVal   = $faculty['first_name'] ?? '';
$lastNameVal    = $faculty['last_name'] ?? '';
$fullName       = !empty($firstNameVal) ? trim($firstNameVal . ' ' . $lastNameVal) : 'Faculty Member';
$initials       = strtoupper(substr($firstNameVal ?: 'F', 0, 1) . substr($lastNameVal ?: 'P', 0, 1));
$designation    = !empty($faculty['designation']) ? $faculty['designation'] : 'Professor';
$qualification  = !empty($faculty['qualification']) ? $faculty['qualification'] : 'Ph.D. in Computer Science';
$officeRoom     = !empty($faculty['office_room']) ? $faculty['office_room'] : 'Not allocated';
$specialization = !empty($faculty['specialization']) ? $faculty['specialization'] : 'Distributed Computing, Artificial Intelligence & Cybersecurity';
$experienceYrs  = (int)($faculty['experience_years'] ?? 0);
$joiningDate    = !empty($faculty['joining_date']) ? date('M Y', strtotime($faculty['joining_date'])) : '2020';
$phoneDisplay   = !empty($faculty['phone']) ? $faculty['phone'] : '';

// Experience visual metric ring (capped at 30 years for 100%)
$expPercent = max(0, min(100, round(($experienceYrs / 30) * 100)));

$hasAvatar = !empty($faculty['avatar']) && file_exists(__DIR__ . '/../' . ltrim($faculty['avatar'], '/'));
$avatarUrl = $hasAvatar ? ('../' . ltrim($faculty['avatar'], '/')) : '';

// Real Scannable Faculty QR Code Payload & API URL
$qrDataText = "AcadNexus Verified Faculty Pass\n" .
              "Faculty ID: " . $facultyIdCode . "\n" .
              "Name: Prof. " . $fullName . "\n" .
              "Designation: " . $designation . "\n" .
              "Department: " . $departmentName . "\n" .
              "Office: " . $officeRoom . "\n" .
              "Status: Verified Faculty Member";

$realQrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&data=" . urlencode($qrDataText);

$pageTitle   = 'Faculty Profile & Digital Pass';
$pageHeading = 'Faculty Profile & Digital Pass';
$activeNav   = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,500;0,6..72,600;1,6..72,500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
:root {
    --pf-ink: #0f1b3d;
    --pf-ink-2: #1b2a57;
    --pf-teal: #0e7c86;
    --pf-teal-soft: #e3f2f3;
    --pf-brass: #c9a227;
    --pf-brass-soft: #f7efd2;
    --pf-paper: #f5f6fa;
    --pf-line: #e3e6ee;
    --pf-text: #1c2438;
    --pf-muted: #667089;
    --pf-serif: 'Newsreader', Georgia, serif;
    --pf-sans: 'Inter', -apple-system, 'Segoe UI', Roboto, sans-serif;
}

.pf-wrap {
    max-width: 1120px;
    margin: 8px auto 40px auto;
    font-family: var(--pf-sans);
    color: var(--pf-text);
}

.pf-layout {
    display: grid;
    grid-template-columns: 330px 1fr;
    gap: 28px;
    align-items: start;
}
@media (max-width: 960px) {
    .pf-layout { grid-template-columns: 1fr; }
}

/* ---------- Lanyard ID card ---------- */
.pf-lanyard {
    position: sticky;
    top: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
@media (max-width: 960px) { .pf-lanyard { position: static; } }

.pf-strap {
    width: 22px;
    height: 44px;
    background: linear-gradient(180deg, var(--pf-brass), #a98414);
    border-radius: 3px 3px 0 0;
    position: relative;
}
.pf-strap::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -6px;
    transform: translateX(-50%);
    width: 34px;
    height: 12px;
    border-radius: 6px;
    background: #8f7010;
}

.pf-idcard {
    width: 100%;
    background: var(--pf-ink);
    color: #fff;
    border-radius: 22px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 24px 50px rgba(15, 27, 61, 0.28);
}
.pf-idcard::before {
    content: '';
    position: absolute;
    top: -80px;
    right: -80px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    border: 38px solid rgba(255, 255, 255, 0.05);
}
.pf-idcard::after {
    content: '';
    position: absolute;
    bottom: -60px;
    left: -60px;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    border: 28px solid rgba(201, 162, 39, 0.1);
}

.pf-id-top {
    padding: 34px 26px 0 26px;
    text-align: center;
    position: relative;
    z-index: 1;
}
.pf-brandline {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: var(--pf-serif);
    font-size: 17px;
    font-weight: 600;
    letter-spacing: 0.4px;
    color: #f3e7b4;
    margin-bottom: 22px;
}
.pf-brandline svg { flex-shrink: 0; }

.pf-photo-wrap {
    position: relative;
    width: 156px;
    height: 188px;
    margin: 0 auto 18px auto;
    cursor: pointer;
}
.pf-photo {
    width: 100%;
    height: 100%;
    border-radius: 18px;
    overflow: hidden;
    background: linear-gradient(160deg, #26407f, #14224d);
    border: 3px solid rgba(255, 255, 255, 0.92);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--pf-serif);
    font-size: 54px;
    font-weight: 600;
    color: #f3e7b4;
    position: relative;
}
.pf-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pf-photo-hover {
    position: absolute;
    inset: 0;
    background: rgba(10, 18, 42, 0.6);
    color: #fff;
    font-family: var(--pf-sans);
    font-size: 12px;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.18s ease;
}
.pf-photo-wrap:hover .pf-photo-hover,
.pf-photo-wrap:focus-visible .pf-photo-hover { opacity: 1; }
.pf-photo-wrap:focus-visible { outline: 3px solid var(--pf-brass); outline-offset: 4px; border-radius: 20px; }

.pf-verified {
    position: absolute;
    right: -10px;
    bottom: -10px;
    background: #12a37f;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid var(--pf-ink);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
}

.pf-id-name {
    font-family: var(--pf-serif);
    font-size: 24px;
    font-weight: 600;
    line-height: 1.15;
    margin: 0 0 4px 0;
    color: #fff;
}
.pf-id-prog { font-size: 13px; color: #b9c3e0; margin-bottom: 18px; }

.pf-id-rows {
    margin: 0 26px;
    padding: 14px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 14px;
    text-align: left;
    position: relative;
    z-index: 1;
}
.pf-id-rows .k { font-size: 11px; color: #94a1c6; margin-bottom: 2px; }
.pf-id-rows .v { font-size: 13px; font-weight: 600; color: #fff; word-break: break-word; }
.pf-id-rows .full { grid-column: 1 / -1; }

.pf-id-bottom {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 26px 24px 26px;
    position: relative;
    z-index: 1;
}
.pf-qr {
    width: 104px;
    height: 104px;
    background: #fff;
    border-radius: 14px;
    padding: 8px;
    flex-shrink: 0;
}
.pf-qr img { width: 100%; height: 100%; display: block; }
.pf-id-note { font-size: 12px; line-height: 1.5; color: #b9c3e0; text-align: left; }
.pf-id-note b { color: #fff; display: block; font-size: 13px; margin-bottom: 2px; }

/* ---------- Main column ---------- */
.pf-main {
    background: #fff;
    border: 1px solid var(--pf-line);
    border-radius: 24px;
    padding: 36px 40px 32px 40px;
    box-shadow: 0 10px 34px rgba(15, 27, 61, 0.06);
}
@media (max-width: 600px) { .pf-main { padding: 26px 20px; } }

.pf-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
    padding-bottom: 26px;
    border-bottom: 1px solid var(--pf-line);
}
.pf-title {
    font-family: var(--pf-serif);
    font-size: 38px;
    line-height: 1.08;
    font-weight: 600;
    letter-spacing: -0.4px;
    color: var(--pf-ink);
    margin: 0 0 8px 0;
}
.pf-subtitle { font-size: 15px; color: var(--pf-muted); margin: 0 0 14px 0; }
.pf-tags { display: flex; gap: 8px; flex-wrap: wrap; }
.pf-tag {
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--pf-paper);
    color: var(--pf-ink-2);
    border: 1px solid var(--pf-line);
}
.pf-tag.teal { background: var(--pf-teal-soft); color: #0a5a62; border-color: #c4e2e5; }
.pf-tag.brass { background: var(--pf-brass-soft); color: #7a6209; border-color: #ecdca0; }

.pf-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.pf-btn {
    font-family: var(--pf-sans);
    font-size: 13.5px;
    font-weight: 600;
    padding: 11px 18px;
    border-radius: 12px;
    border: 1px solid var(--pf-line);
    background: #fff;
    color: var(--pf-ink-2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}
.pf-btn:hover { background: var(--pf-paper); border-color: #cbd1e0; }
.pf-btn:focus-visible { outline: 3px solid rgba(14, 124, 134, 0.4); outline-offset: 2px; }
.pf-btn.primary { background: var(--pf-ink); color: #fff; border-color: var(--pf-ink); }
.pf-btn.primary:hover { background: var(--pf-ink-2); }
.pf-btn:disabled { opacity: 0.65; cursor: wait; }

.pf-section { padding: 26px 0; border-bottom: 1px solid var(--pf-line); }
.pf-section:last-child { border-bottom: none; padding-bottom: 0; }
.pf-section h2 {
    font-family: var(--pf-serif);
    font-size: 22px;
    font-weight: 600;
    color: var(--pf-ink);
    margin: 0 0 16px 0;
}

/* Experience Ring + Faculty Facts */
.pf-summary {
    display: grid;
    grid-template-columns: 170px 1fr;
    gap: 30px;
    align-items: center;
}
@media (max-width: 640px) { .pf-summary { grid-template-columns: 1fr; justify-items: center; } }

.pf-ring { position: relative; width: 160px; height: 160px; }
.pf-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.pf-ring .track { stroke: var(--pf-line); }
.pf-ring .bar { stroke: var(--pf-teal); stroke-linecap: round; }
.pf-ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.pf-ring-center .num { font-family: var(--pf-serif); font-size: 38px; font-weight: 600; color: var(--pf-ink); line-height: 1; }
.pf-ring-center .lbl { font-size: 12px; color: var(--pf-muted); margin-top: 4px; }

.pf-facts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px 28px;
    width: 100%;
}
@media (max-width: 520px) { .pf-facts { grid-template-columns: 1fr; } }
.pf-fact .k { font-size: 12.5px; color: var(--pf-muted); margin-bottom: 3px; }
.pf-fact .v { font-size: 15px; font-weight: 600; color: var(--pf-text); word-break: break-word; }

.pf-bio {
    font-family: var(--pf-serif);
    font-size: 18px;
    line-height: 1.6;
    color: var(--pf-text);
    margin: 0;
    max-width: 70ch;
}
.pf-empty { font-size: 14px; color: var(--pf-muted); margin: 0; }

/* Contact list */
.pf-contact { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 28px; }
@media (max-width: 640px) { .pf-contact { grid-template-columns: 1fr; } }
.pf-contact-item { display: flex; gap: 12px; align-items: center; min-width: 0; }
.pf-contact-ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: var(--pf-teal-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.pf-contact-item .k { font-size: 12px; color: var(--pf-muted); }
.pf-contact-item .v { font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Courses table */
.pf-table-scroll { overflow-x: auto; }
.pf-table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 480px; }
.pf-table th {
    text-align: left;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--pf-muted);
    padding: 0 12px 10px 0;
    border-bottom: 2px solid var(--pf-ink);
}
.pf-table td { padding: 13px 12px 13px 0; border-bottom: 1px solid var(--pf-line); vertical-align: middle; }
.pf-table tr:last-child td { border-bottom: none; }
.pf-code { font-weight: 700; color: var(--pf-teal); white-space: nowrap; }
.pf-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12.5px;
    background: var(--pf-teal-soft);
    color: #0a5a62;
}

/* Mentored Projects */
.pf-projects { display: flex; flex-direction: column; gap: 22px; }
.pf-project { display: grid; grid-template-columns: 1fr 160px; gap: 8px 24px; align-items: center; }
@media (max-width: 600px) { .pf-project { grid-template-columns: 1fr; } }
.pf-project h3 { font-size: 16px; font-weight: 700; margin: 0 0 3px 0; color: var(--pf-ink); }
.pf-project .tech { font-size: 13px; color: var(--pf-teal); font-weight: 600; margin-bottom: 4px; }
.pf-project .desc { font-size: 13.5px; color: var(--pf-muted); line-height: 1.5; margin: 0; }
.pf-progress .top { display: flex; justify-content: space-between; font-size: 12px; color: var(--pf-muted); margin-bottom: 6px; }
.pf-progress .top b { color: var(--pf-ink); }
.pf-progress .rail { height: 6px; background: var(--pf-line); border-radius: 999px; overflow: hidden; }
.pf-progress .fill { height: 100%; background: var(--pf-teal); border-radius: 999px; }

/* ---------- Modals ---------- */
.pf-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 18, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
}
.pf-modal-box {
    background: #fff;
    border-radius: 22px;
    width: 100%;
    padding: 30px;
    margin: auto;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.25);
    font-family: var(--pf-sans);
}
.pf-modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 20px; }
.pf-modal-head h3 { font-family: var(--pf-serif); font-size: 24px; font-weight: 600; margin: 0 0 2px 0; color: var(--pf-ink); }
.pf-modal-head p { margin: 0; font-size: 13px; color: var(--pf-muted); }
.pf-close { background: transparent; border: none; font-size: 22px; cursor: pointer; color: #94a1b8; line-height: 1; padding: 4px; }

.pf-drop {
    border: 2px dashed #c3c9da;
    border-radius: 18px;
    padding: 30px 20px;
    text-align: center;
    background: var(--pf-paper);
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
    margin-bottom: 18px;
}
.pf-drop.dragover { border-color: var(--pf-teal); background: var(--pf-teal-soft); }
.pf-drop .big { font-size: 34px; margin-bottom: 6px; }
.pf-drop .t { font-weight: 700; color: var(--pf-ink); margin-bottom: 4px; }
.pf-drop .s { font-size: 12.5px; color: var(--pf-muted); margin-bottom: 14px; }

.pf-field-grid { display: grid; gap: 16px; margin-bottom: 16px; }
.pf-field-grid.c2 { grid-template-columns: 1fr 1fr; }
.pf-field-grid.c21 { grid-template-columns: 1fr 1.5fr; }
.pf-field-grid.c12 { grid-template-columns: 1.5fr 1fr; }
@media (max-width: 560px) { .pf-field-grid.c2, .pf-field-grid.c21, .pf-field-grid.c12 { grid-template-columns: 1fr; } }
.pf-field label { display: block; font-size: 12.5px; font-weight: 600; color: #38425c; margin-bottom: 6px; }
.pf-field input, .pf-field select, .pf-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 13px;
    border-radius: 11px;
    border: 1px solid #cbd1e0;
    font-size: 14px;
    font-family: var(--pf-sans);
    background: #fff;
    color: var(--pf-text);
}
.pf-field input:focus, .pf-field select:focus, .pf-field textarea:focus {
    outline: none;
    border-color: var(--pf-teal);
    box-shadow: 0 0 0 3px rgba(14, 124, 134, 0.18);
}
.pf-field input:disabled { background: var(--pf-paper); color: var(--pf-muted); font-weight: 700; }
.pf-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid var(--pf-line); margin-top: 8px; }
</style>

<div class="pf-wrap">
    <div class="pf-layout">

        <!-- ================= LEFT: LANYARD ID CARD ================= -->
        <aside class="pf-lanyard" id="digitalPassCard">
            <div class="pf-strap"></div>
            <div class="pf-idcard">
                <div class="pf-id-top">
                    <div class="pf-brandline">
                        <svg width="22" height="22" viewBox="0 0 100 100" fill="none">
                            <path d="M50 6 L88 27 L88 67 L50 94 L12 67 L12 27 Z" fill="#c9a227"/>
                            <path d="M50 19 L76 34 L76 62 L50 81 L24 62 L24 34 Z" fill="#0f1b3d"/>
                            <polygon points="50,33 55,46 69,46 58,54 62,68 50,60 38,68 42,54 31,46 45,46" fill="#f3e7b4"/>
                        </svg>
                        <span>AcadNexus Faculty Pass</span>
                    </div>

                    <div class="pf-photo-wrap" onclick="openModal('uploadPhotoModal')" tabindex="0" role="button" aria-label="Upload faculty photo">
                        <div class="pf-photo">
                            <?php if ($hasAvatar): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($initials); ?></span>
                            <?php endif; ?>
                            <div class="pf-photo-hover">
                                <span style="font-size:18px;">📷</span>
                                <span>Change photo</span>
                            </div>
                        </div>
                        <div class="pf-verified" title="Verified faculty member">✓</div>
                    </div>

                    <h2 class="pf-id-name"><?php echo htmlspecialchars($fullName); ?></h2>
                    <div class="pf-id-prog"><?php echo htmlspecialchars($designation); ?></div>
                </div>

                <div class="pf-id-rows">
                    <div class="full">
                        <div class="k">Faculty Identification Number</div>
                        <div class="v" style="font-size:16px; color:#f3e7b4; letter-spacing:0.5px;"><?php echo htmlspecialchars($facultyIdCode); ?></div>
                    </div>
                    <div>
                        <div class="k">Office Room</div>
                        <div class="v"><?php echo htmlspecialchars($officeRoom); ?></div>
                    </div>
                    <div>
                        <div class="k">Experience</div>
                        <div class="v"><?php echo $experienceYrs; ?> Years</div>
                    </div>
                </div>

                <div class="pf-id-bottom">
                    <div class="pf-qr">
                        <img src="<?php echo htmlspecialchars($realQrApiUrl); ?>" alt="QR code for faculty verification" crossorigin="anonymous">
                    </div>
                    <div class="pf-id-note">
                        <b>Scan to verify</b>
                        Confirms faculty credentials, department, and university appointment status.
                    </div>
                </div>
            </div>
        </aside>

        <!-- ================= RIGHT: PROFILE DETAILS ================= -->
        <main class="pf-main">
            <div class="pf-head">
                <div>
                    <h1 class="pf-title"><?php echo htmlspecialchars($fullName); ?></h1>
                    <p class="pf-subtitle"><?php echo htmlspecialchars($departmentName); ?></p>
                    <div class="pf-tags">
                        <span class="pf-tag teal"><?php echo htmlspecialchars($designation); ?></span>
                        <span class="pf-tag">Office: <?php echo htmlspecialchars($officeRoom); ?></span>
                        <span class="pf-tag brass">Verified Faculty Member</span>
                    </div>
                </div>
                <div class="pf-actions">
                    <button type="button" class="pf-btn" onclick="openModal('uploadPhotoModal')">📷 Upload photo</button>
                    <button type="button" class="pf-btn" id="downloadPdfBtn" onclick="downloadPDFPass()">📥 Download pass</button>
                    <button type="button" class="pf-btn primary" onclick="openModal('editProfileModal')">✏️ Edit profile</button>
                </div>
            </div>

            <!-- Academic & Professional Summary -->
            <section class="pf-section">
                <h2>Academic & Professional Summary</h2>
                <div class="pf-summary">
                    <div class="pf-ring" role="img" aria-label="<?php echo $experienceYrs; ?> years of academic experience">
                        <svg viewBox="0 0 120 120">
                            <circle class="track" cx="60" cy="60" r="52" fill="none" stroke-width="10"/>
                            <circle class="bar" cx="60" cy="60" r="52" fill="none" stroke-width="10"
                                    stroke-dasharray="326.7" stroke-dashoffset="<?php echo round(326.7 * (1 - $expPercent / 100), 1); ?>"/>
                        </svg>
                        <div class="pf-ring-center">
                            <div class="num"><?php echo $experienceYrs; ?></div>
                            <div class="lbl">Years Exp.</div>
                        </div>
                    </div>
                    <div class="pf-facts">
                        <div class="pf-fact"><div class="k">Highest Qualification</div><div class="v"><?php echo htmlspecialchars($qualification); ?></div></div>
                        <div class="pf-fact"><div class="k">Faculty ID Code</div><div class="v"><?php echo htmlspecialchars($facultyIdCode); ?></div></div>
                        <div class="pf-fact"><div class="k">Academic Department</div><div class="v"><?php echo htmlspecialchars($departmentName); ?></div></div>
                        <div class="pf-fact"><div class="k">Office Location</div><div class="v"><?php echo htmlspecialchars($officeRoom); ?></div></div>
                    </div>
                </div>
            </section>

            <!-- Research & Specialization -->
            <section class="pf-section">
                <h2>Research & Specialization</h2>
                <?php if (!empty($specialization)): ?>
                    <p class="pf-bio">“<?php echo htmlspecialchars($specialization); ?>”</p>
                <?php else: ?>
                    <p class="pf-empty">No research specialization statement added yet. Use Edit profile to provide your focus areas.</p>
                <?php endif; ?>
            </section>

            <!-- Contact & Office -->
            <section class="pf-section">
                <h2>Contact and Office Details</h2>
                <div class="pf-contact">
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">✉️</div>
                        <div style="min-width:0;"><div class="k">Official Email</div><div class="v"><?php echo htmlspecialchars($faculty['email'] ?? ''); ?></div></div>
                    </div>
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">📞</div>
                        <div style="min-width:0;"><div class="k">Phone Number</div><div class="v"><?php echo htmlspecialchars($phoneDisplay ?: 'Not provided'); ?></div></div>
                    </div>
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">🏢</div>
                        <div style="min-width:0;"><div class="k">Office Room</div><div class="v"><?php echo htmlspecialchars($officeRoom); ?></div></div>
                    </div>
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">📅</div>
                        <div style="min-width:0;"><div class="k">Joined Institution</div><div class="v"><?php echo htmlspecialchars($joiningDate); ?></div></div>
                    </div>
                </div>
            </section>

            <!-- Allocated Teaching Courses -->
            <section class="pf-section">
                <h2>Allocated Teaching Courses</h2>
                <?php if (!empty($dbCourses)): ?>
                    <div class="pf-table-scroll">
                        <table class="pf-table">
                            <thead>
                                <tr><th>Code</th><th>Course Name</th><th>Credits</th><th>Semester</th><th>Enrolled Students</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($dbCourses as $c): ?>
                                <tr>
                                    <td class="pf-code"><?php echo htmlspecialchars($c['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($c['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars((string)($c['credits'] ?? '-')); ?></td>
                                    <td>Semester <?php echo htmlspecialchars((string)($c['semester'] ?? '1')); ?></td>
                                    <td><span class="pf-badge"><?php echo (int)($c['enrolled_students'] ?? 0); ?> Students</span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="pf-empty">No teaching courses currently allocated under your instruction.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<!-- ================= UPLOAD PHOTO MODAL ================= -->
<div id="uploadPhotoModal" class="pf-modal" onclick="if(event.target===this)closeModal('uploadPhotoModal')">
    <div class="pf-modal-box" style="max-width: 520px;">
        <div class="pf-modal-head">
            <div>
                <h3>Upload Faculty Photo</h3>
                <p>Saved securely with verified file headers</p>
            </div>
            <button type="button" class="pf-close" aria-label="Close" onclick="closeModal('uploadPhotoModal')">✕</button>
        </div>

        <form id="avatarUploadForm" action="profile.php" method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="pf-drop" id="dropZone" onclick="document.getElementById('avatarFileInput').click()">
                <div class="big">📁</div>
                <div class="t">Drag and drop your faculty photo here</div>
                <div class="s">PNG, JPG or JPEG. A clear, front-facing professional portrait works best.</div>
                <button type="button" class="pf-btn primary">Choose a file</button>
                <input type="file" id="avatarFileInput" name="avatar" accept="image/png, image/jpeg, image/jpg" style="display:none;" onchange="handleFileSelected(this.files)">
            </div>

            <div id="imagePreviewContainer" style="display:none; text-align:center; margin-bottom:18px;">
                <img id="imagePreview" src="#" alt="Selected photo preview" style="width:110px; height:132px; border-radius:14px; object-fit:cover; border:3px solid var(--pf-teal);">
                <div style="font-size:12.5px; color:var(--pf-muted); margin-top:6px; font-weight:600;" id="fileNameLabel"></div>
            </div>

            <div class="pf-modal-foot">
                <button type="button" class="pf-btn" onclick="closeModal('uploadPhotoModal')">Cancel</button>
                <button type="submit" class="pf-btn primary">Upload photo</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT FACULTY PROFILE MODAL ================= -->
<div id="editProfileModal" class="pf-modal" onclick="if(event.target===this)closeModal('editProfileModal')">
    <div class="pf-modal-box" style="max-width: 700px;">
        <div class="pf-modal-head">
            <div>
                <h3>Edit Faculty Profile</h3>
                <p>Update your personal details, academic title, qualifications, and office room.</p>
            </div>
            <button type="button" class="pf-close" aria-label="Close" onclick="closeModal('editProfileModal')">✕</button>
        </div>

        <form action="profile.php" method="POST">
            <?php echo csrfField(); ?>
            <div class="pf-field-grid c2">
                <div class="pf-field">
                    <label for="f_first">First name *</label>
                    <input id="f_first" type="text" name="first_name" value="<?php echo htmlspecialchars($firstNameVal); ?>" required>
                </div>
                <div class="pf-field">
                    <label for="f_last">Last name *</label>
                    <input id="f_last" type="text" name="last_name" value="<?php echo htmlspecialchars($lastNameVal); ?>" required>
                </div>
            </div>

            <div class="pf-field-grid c2">
                <div class="pf-field">
                    <label for="f_desig">Academic Designation *</label>
                    <input id="f_desig" type="text" name="designation" value="<?php echo htmlspecialchars($designation); ?>" required placeholder="e.g. Associate Professor">
                </div>
                <div class="pf-field">
                    <label for="f_qual">Highest Qualification</label>
                    <input id="f_qual" type="text" name="qualification" value="<?php echo htmlspecialchars($qualification); ?>" placeholder="e.g. Ph.D. in Computer Science">
                </div>
            </div>

            <div class="pf-field-grid c2">
                <div class="pf-field">
                    <label for="f_room">Office Room</label>
                    <input id="f_room" type="text" name="office_room" value="<?php echo htmlspecialchars($officeRoom); ?>" placeholder="e.g. Block B, Room 402">
                </div>
                <div class="pf-field">
                    <label for="f_exp">Years of Experience</label>
                    <input id="f_exp" type="number" min="0" max="50" name="experience_years" value="<?php echo $experienceYrs; ?>">
                </div>
            </div>

            <div class="pf-field-grid c2">
                <div class="pf-field">
                    <label for="f_phone">Phone Number</label>
                    <input id="f_phone" type="text" name="phone" value="<?php echo htmlspecialchars($phoneDisplay); ?>" placeholder="+1 555-0100">
                </div>
                <div class="pf-field">
                    <label for="f_fid">Faculty ID Code</label>
                    <input id="f_fid" type="text" value="<?php echo htmlspecialchars($facultyIdCode); ?>" disabled>
                </div>
            </div>

            <div class="pf-field" style="margin-bottom:8px;">
                <label for="f_spec">Research & Specialization Areas</label>
                <textarea id="f_spec" name="specialization" rows="3" style="resize:vertical;" placeholder="Describe your key research domains and expertise..."><?php echo htmlspecialchars($specialization); ?></textarea>
            </div>

            <div class="pf-modal-foot">
                <button type="button" class="pf-btn" onclick="closeModal('editProfileModal')">Cancel</button>
                <button type="submit" class="pf-btn primary">Save profile</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ---------- Modal helpers ---------- */
function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal('uploadPhotoModal'); closeModal('editProfileModal'); }
});

/* ---------- Drag and drop uploader ---------- */
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('avatarFileInput');

if (dropZone && fileInput) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => {
        dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }, false);
    });
    ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.add('dragover'), false));
    ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.remove('dragover'), false));
    dropZone.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            handleFileSelected(files);
        }
    });
}

function handleFileSelected(files) {
    if (!files || !files[0]) return;
    const file = files[0];
    const label = document.getElementById('fileNameLabel');
    const img = document.getElementById('imagePreview');
    const box = document.getElementById('imagePreviewContainer');
    if (label) label.textContent = file.name;
    const reader = new FileReader();
    reader.onload = e => {
        if (img) img.src = e.target.result;
        if (box) box.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

/* ---------- PDF pass generation ---------- */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function downloadPDFPass() {
    const btn = document.getElementById('downloadPdfBtn');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = 'Preparing PDF…'; }

    const d = {
        name: <?php echo json_encode($fullName); ?>,
        facId: <?php echo json_encode($facultyIdCode); ?>,
        designation: <?php echo json_encode($designation); ?>,
        dept: <?php echo json_encode($departmentName); ?>,
        qualification: <?php echo json_encode($qualification); ?>,
        office: <?php echo json_encode($officeRoom); ?>,
        experience: <?php echo json_encode($experienceYrs . ' Years'); ?>,
        email: <?php echo json_encode($faculty['email'] ?? ''); ?>,
        phone: <?php echo json_encode($phoneDisplay ?: 'Not provided'); ?>,
        specialization: <?php echo json_encode($specialization); ?>,
        avatarUrl: <?php echo json_encode($avatarUrl); ?>,
        initials: <?php echo json_encode($initials); ?>,
        qrUrl: <?php echo json_encode($realQrApiUrl); ?>,
        generatedAt: <?php echo json_encode(date('d M Y, h:i A T')); ?>,
        courses: <?php echo json_encode($dbCourses); ?>,
        projects: <?php echo json_encode($dbProjects); ?>
    };

    const SERIF = "'Newsreader', Georgia, serif";
    const SANS = "'Inter', -apple-system, 'Segoe UI', Roboto, sans-serif";

    const photoHtml = d.avatarUrl
        ? `<img src="${esc(d.avatarUrl)}" style="width:100%;height:100%;object-fit:cover;display:block;">`
        : `<div style="width:100%;height:100%;background:#1b2a57;display:flex;align-items:center;justify-content:center;color:#f3e7b4;font-family:${SERIF};font-size:46px;font-weight:600;">${esc(d.initials)}</div>`;

    const courseRows = (d.courses && d.courses.length)
        ? d.courses.map(c => `<tr>
            <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;font-weight:700;color:#0e7c86;">${esc(c.course_code)}</td>
            <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;">${esc(c.course_name)}</td>
            <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;text-align:center;">${esc(c.credits ?? '-')}</td>
            <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;text-align:center;">Sem ${esc(c.semester ?? '1')}</td>
            <td style="padding:7px 0;border-bottom:1px solid #e3e6ee;text-align:center;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;background:#e3f2f3;color:#0a5a62;font-weight:700;">${esc(c.enrolled_students || 0)} Students</span></td>
        </tr>`).join('')
        : `<tr><td colspan="5" style="padding:10px 0;color:#667089;">No teaching courses currently allocated.</td></tr>`;

    const passContent = `
    <div id="passCardExport" style="width:794px;height:1120px;position:relative;overflow:hidden;box-sizing:border-box;background:#ffffff;color:#1c2438;font-family:${SANS};">
        <!-- Header band -->
        <div style="position:relative;height:150px;background:#0f1b3d;color:#ffffff;overflow:hidden;">
            <div style="position:absolute;top:-90px;right:-60px;width:300px;height:300px;border-radius:50%;border:44px solid rgba(255,255,255,0.05);"></div>
            <div style="position:absolute;bottom:-70px;left:200px;width:200px;height:200px;border-radius:50%;border:30px solid rgba(201,162,39,0.12);"></div>
            <div style="position:absolute;left:36px;top:34px;display:flex;align-items:center;gap:14px;">
                <svg width="58" height="58" viewBox="0 0 100 100" fill="none">
                    <path d="M50 6 L88 27 L88 67 L50 94 L12 67 L12 27 Z" fill="#c9a227"/>
                    <path d="M50 19 L76 34 L76 62 L50 81 L24 62 L24 34 Z" fill="#0f1b3d"/>
                    <polygon points="50,33 55,46 69,46 58,54 62,68 50,60 38,68 42,54 31,46 45,46" fill="#f3e7b4"/>
                </svg>
                <div>
                    <div style="font-family:${SERIF};font-size:30px;font-weight:600;line-height:1;letter-spacing:0.4px;">AcadNexus</div>
                    <div style="font-size:12px;color:#b9c3e0;margin-top:6px;">${esc(d.dept)}</div>
                </div>
            </div>
            <div style="position:absolute;right:36px;top:38px;text-align:right;">
                <div style="display:inline-block;padding:6px 14px;border-radius:999px;border:1px solid #c9a227;color:#f3e7b4;font-size:11px;font-weight:600;">Official Faculty Pass</div>
                <div style="font-size:11px;color:#b9c3e0;margin-top:10px;">ID: ${esc(d.facId)}</div>
            </div>
            <div style="position:absolute;left:0;right:0;bottom:0;height:5px;background:#c9a227;"></div>
        </div>

        <!-- Body -->
        <div style="display:flex;height:900px;">
            <!-- Sidebar -->
            <div style="width:240px;box-sizing:border-box;background:#f5f6fa;padding:0 24px 20px 28px;border-right:1px solid #e3e6ee;position:relative;">
                <div style="width:150px;height:184px;margin-top:-52px;border-radius:14px;overflow:hidden;border:4px solid #ffffff;background:#ffffff;box-shadow:0 8px 20px rgba(15,27,61,0.22);position:relative;">
                    ${photoHtml}
                </div>
                <div style="font-family:${SERIF};font-size:20px;font-weight:600;color:#0f1b3d;line-height:1.15;margin-top:16px;">${esc(d.name)}</div>
                <div style="font-size:11px;color:#667089;margin:4px 0 14px 0;">${esc(d.designation)}</div>
                <div style="display:inline-block;padding:4px 10px;border-radius:999px;background:#dff5ea;color:#0b6b44;font-size:10px;font-weight:700;margin-bottom:18px;">✓ Verified Faculty</div>

                <div style="border-top:1px solid #e3e6ee;padding-top:14px;">
                    <div style="margin-bottom:9px;"><div style="font-size:9px;color:#8a94ad;">FACULTY ID</div><div style="font-size:11px;font-weight:700;color:#0f1b3d;">${esc(d.facId)}</div></div>
                    <div style="margin-bottom:9px;"><div style="font-size:9px;color:#8a94ad;">OFFICE ROOM</div><div style="font-size:11px;font-weight:600;color:#1c2438;">${esc(d.office)}</div></div>
                    <div style="margin-bottom:9px;"><div style="font-size:9px;color:#8a94ad;">EXPERIENCE</div><div style="font-size:11px;font-weight:600;color:#1c2438;">${esc(d.experience)}</div></div>
                    <div style="margin-bottom:9px;"><div style="font-size:9px;color:#8a94ad;">EMAIL</div><div style="font-size:10px;font-weight:600;color:#1c2438;word-break:break-all;">${esc(d.email)}</div></div>
                </div>

                <div style="position:absolute;bottom:20px;left:28px;right:24px;text-align:center;">
                    <img src="${esc(d.qrUrl)}" style="width:84px;height:84px;background:#ffffff;padding:5px;border-radius:8px;border:1px solid #e3e6ee;" alt="QR">
                </div>
            </div>

            <!-- Main area -->
            <div style="flex:1;box-sizing:border-box;padding:28px 36px;">
                <div style="border-bottom:1px solid #e3e6ee;padding-bottom:16px;margin-bottom:20px;">
                    <div style="font-family:${SERIF};font-size:26px;font-weight:600;color:#0f1b3d;">Academic & Teaching Dossier</div>
                    <div style="font-size:12px;color:#667089;margin-top:2px;">${esc(d.qualification)} &bull; ${esc(d.dept)}</div>
                </div>

                <div style="margin-bottom:20px;">
                    <div style="font-family:${SERIF};font-size:16px;font-weight:600;color:#0f1b3d;margin-bottom:6px;">Research Focus</div>
                    <div style="font-size:12px;color:#475569;line-height:1.5;">${esc(d.specialization)}</div>
                </div>

                <div>
                    <div style="font-family:${SERIF};font-size:16px;font-weight:600;color:#0f1b3d;margin-bottom:8px;">Allocated Teaching Courses</div>
                    <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:0 8px 6px 0;border-bottom:2px solid #0f1b3d;color:#667089;">Code</th>
                                <th style="text-align:left;padding:0 8px 6px 0;border-bottom:2px solid #0f1b3d;color:#667089;">Course Name</th>
                                <th style="text-align:center;padding:0 8px 6px 0;border-bottom:2px solid #0f1b3d;color:#667089;">Credits</th>
                                <th style="text-align:center;padding:0 8px 6px 0;border-bottom:2px solid #0f1b3d;color:#667089;">Semester</th>
                                <th style="text-align:center;padding:0 0 6px 0;border-bottom:2px solid #0f1b3d;color:#667089;">Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${courseRows}
                        </tbody>
                    </table>
                </div>

                <div style="position:absolute;bottom:24px;right:36px;font-size:10px;color:#8a94ad;">
                    Generated on ${esc(d.generatedAt)} &bull; AcadNexus Security Verified
                </div>
            </div>
        </div>
    </div>`;

    const container = document.createElement('div');
    container.style.position = 'fixed';
    container.style.left = '-9999px';
    container.style.top = '0';
    container.innerHTML = passContent;
    document.body.appendChild(container);

    const targetEl = container.firstElementChild;
    const opt = {
        margin: 0,
        filename: 'Faculty_Pass_' + String(d.facId || 'Pass') + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'px', format: [794, 1120], orientation: 'portrait' }
    };

    try {
        await html2pdf().set(opt).from(targetEl).save();
    } catch (err) {
        console.error('PDF generation error:', err);
        alert('Could not generate the PDF pass. Please try again.');
    } finally {
        document.body.removeChild(container);
        if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
