<?php
// student/profile.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');

/* ------------------------------------------------------------------
   Unique student ID (generated once, then stored permanently)
------------------------------------------------------------------- */
$currentId = trim($student['student_id_number'] ?? '');
if (empty($currentId) || !preg_match('/^ANX\d{4}$/i', $currentId)) {
    do {
        $newCode = 'ANX' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE student_id_number = ?");
        $chk->execute([$newCode]);
        $exists = $chk->fetchColumn();
    } while ($exists > 0);

    $upd = $pdo->prepare("UPDATE student_profiles SET student_id_number = ? WHERE id = ?");
    $upd->execute([$newCode, $student['id']]);
    $student['student_id_number'] = $newCode;
    $currentId = $newCode;
}

$studentIdCode  = strtoupper($currentId);
$regNumber      = !empty($student['reg_number']) ? strtoupper(trim($student['reg_number'])) : $studentIdCode;
$departmentName = !empty($student['department_name']) ? $student['department_name'] : 'Computer Science & Engineering';

// Upload folder rule: uploads/student/{Department_Name}/{StudentID}/profile/
$cleanDeptName       = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $departmentName);
$cleanStudentId      = preg_replace('/[^A-Za-z0-9\-\_]/', '', $studentIdCode);
$studentUploadSubdir = 'student/' . $cleanDeptName . '/' . $cleanStudentId . '/profile';

/* ------------------------------------------------------------------
   Profile photo upload (drag-and-drop or file input)
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    requireCSRF();
    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = FileService::upload($_FILES['avatar'], 'avatar', $userId);

        if ($uploadResult['success']) {
            $avatarPath = $uploadResult['file_path'];
            // Delete old avatar if present
            if (!empty($student['avatar'])) {
                FileService::delete($student['avatar']);
            }
            $updAv = $pdo->prepare("UPDATE student_profiles SET avatar = ? WHERE user_id = ?");
            $updAv->execute([$avatarPath, $userId]);
            setToast('success', 'Profile photo updated.');
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
   Profile details update
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['avatar'])) {
    requireCSRF();
    $firstName   = trim($_POST['first_name'] ?? '');
    $lastName    = trim($_POST['last_name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
    $githubUrl   = trim($_POST['github_url'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        setToast('error', 'First name and last name are required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE student_profiles
                SET first_name = ?, last_name = ?, phone = ?,
                    linkedin_url = ?, github_url = ?, bio = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $firstName, $lastName, $phone,
                $linkedinUrl, $githubUrl, $bio,
                $userId
            ]);

            setToast('success', 'Profile details saved.');
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
$student = getUserProfile($userId, 'student');

$deptStmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC");
$allDepartments = $deptStmt->fetchAll();

$dbProjects = [];
if (!empty($student['id'])) {
    $pStmt = $pdo->prepare("
        SELECT p.id, p.title, p.technologies, p.description, p.status, p.progress_percent,
               f.first_name as fac_fn, f.last_name as fac_ln
        FROM projects p
        LEFT JOIN faculty_profiles f ON p.faculty_advisor_id = f.id
        WHERE p.student_id = ? AND LOWER(p.status) = 'approved'
        ORDER BY p.created_at DESC
        LIMIT 3
    ");
    $pStmt->execute([$student['id']]);
    $dbProjects = $pStmt->fetchAll(PDO::FETCH_ASSOC);
}

$dbMarks = [];
if (!empty($student['id'])) {
    $mStmt = $pdo->prepare("
        SELECT c.code as course_code, c.name as course_name, c.credits, m.total_marks, m.grade
        FROM marks m
        JOIN courses c ON m.course_id = c.id
        WHERE m.student_id = ?
        ORDER BY c.code ASC
        LIMIT 4
    ");
    $mStmt->execute([$student['id']]);
    $dbMarks = $mStmt->fetchAll(PDO::FETCH_ASSOC);
}

$firstNameVal = $student['first_name'] ?? '';
$lastNameVal  = $student['last_name'] ?? '';
$fullName     = !empty($firstNameVal) ? trim($firstNameVal . ' ' . $lastNameVal) : 'Student';
$initials     = strtoupper(substr($firstNameVal ?: 'S', 0, 1) . substr($lastNameVal ?: 'T', 0, 1));
$program      = !empty($student['program']) ? $student['program'] : 'Computer Science';
$departmentName = !empty($student['department_name']) ? $student['department_name'] : 'Department of Computer Science & Engineering';
$regNumber    = !empty($student['reg_number']) ? strtoupper(trim($student['reg_number'])) : $studentIdCode;
$addressFull  = trim(($student['address'] ?? '') . ', ' . ($student['city'] ?? '') . ', ' . ($student['state'] ?? '') . ' ' . ($student['postal_code'] ?? ''), ', ');
$locationStr  = !empty($addressFull) ? $addressFull : (!empty($student['city']) ? $student['city'] : 'Not provided');

$startYr  = !empty($student['admission_year']) ? (int)$student['admission_year'] : (int)date('Y');
$endYr    = !empty($student['graduation_year']) ? (int)$student['graduation_year'] : ($startYr + 4);
$batchStr = $startYr . ' – ' . $endYr;

$cgpaNum      = !empty($student['cgpa']) ? (float)$student['cgpa'] : 0;
$cgpaVal      = $cgpaNum > 0 ? number_format($cgpaNum, 2) : 'N/A';
$cgpaInput    = $cgpaNum > 0 ? number_format($cgpaNum, 2, '.', '') : '';
$cgpaPercent  = max(0, min(100, $cgpaNum * 10));

$linkedinDisplay = $student['linkedin_url'] ?? '';
$githubDisplay   = $student['github_url'] ?? '';
$bioText         = $student['bio'] ?? '';
$phoneDisplay    = !empty($student['phone']) ? $student['phone'] : '';
$yearOfStudy     = (int)($student['year_of_study'] ?? 1);
$semesterVal     = (int)($student['semester'] ?? 1);
$sectionVal      = $student['section'] ?? 'A';

$hasAvatar = !empty($student['avatar']) && file_exists(__DIR__ . '/../' . $student['avatar']);
$avatarUrl = $hasAvatar ? ('../' . $student['avatar']) : '';

// Scannable QR payload
$qrDataText = "AcadNexus Verified Student Pass\n" .
              "Reg/Roll No: " . $regNumber . "\n" .
              "Student ID: " . $studentIdCode . "\n" .
              "Name: " . $fullName . "\n" .
              "Program: " . $program . "\n" .
              "Batch: " . $batchStr . "\n" .
              "Department: " . $departmentName . "\n" .
              "CGPA: " . $cgpaVal . "\n" .
              "Status: Verified Active Student";

$realQrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&data=" . urlencode($qrDataText);

$pageTitle   = 'My Profile & Digital Pass';
$pageHeading = 'Student Profile & Digital Pass';
$activeNav   = 'profile';
require_once __DIR__ . '/../includes/header.php';

// Grade colour helper
function gradeClass($g) {
    $g = strtoupper(trim((string)$g));
    if ($g === '') return 'g-na';
    $c = $g[0];
    if ($c === 'O' || $c === 'A') return 'g-top';
    if ($c === 'B') return 'g-good';
    if ($c === 'C' || $c === 'D') return 'g-mid';
    return 'g-low';
}
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
    font-size: 26px;
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
    font-size: 40px;
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

/* CGPA + facts */
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

/* Marks table */
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
.pf-grade {
    display: inline-block;
    min-width: 34px;
    text-align: center;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
}
.g-top { background: #dff5ea; color: #0b6b44; }
.g-good { background: var(--pf-teal-soft); color: #0a5a62; }
.g-mid { background: var(--pf-brass-soft); color: #7a6209; }
.g-low { background: #fde4e4; color: #9b1c1c; }
.g-na { background: var(--pf-paper); color: var(--pf-muted); }

/* Projects */
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
.pf-batch-box { background: var(--pf-paper); border: 1px solid var(--pf-line); border-radius: 14px; padding: 16px; margin-bottom: 16px; }
.pf-batch-box > .t { font-size: 13px; font-weight: 700; color: var(--pf-ink); margin-bottom: 10px; }
.pf-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid var(--pf-line); margin-top: 8px; }

@media (max-width: 640px) {
    .pf-head {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }
    .pf-title {
        font-size: 28px;
    }
    .pf-actions {
        width: 100%;
        display: flex;
        flex-direction: column;
    }
    .pf-btn {
        width: 100%;
        justify-content: center;
        padding: 12px;
    }
    .pf-idcard {
        max-width: 330px;
        margin: 0 auto;
    }
    .pf-modal {
        padding: 12px;
    }
    .pf-modal-box {
        padding: 20px 16px;
        border-radius: 18px;
    }
}
</style>

<div class="pf-wrap">
    <div class="pf-layout">

        <!-- ================= LEFT: LANYARD ID CARD ================= -->
        <aside class="pf-lanyard" id="digitalPassCard">
            <div class="pf-strap"></div>
            <div class="pf-idcard">
                <div class="pf-id-top">
                    <div class="pf-brandline">
                        <svg width="26" height="26" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                            <path d="M50 8 L86 28 L86 66 L50 92 L14 66 L14 28 Z" fill="#c9a227"/>
                            <path d="M50 20 L74 34 L74 62 L50 80 L26 62 L26 34 Z" fill="#0f1b3d"/>
                            <polygon points="50,34 55,46 68,46 58,54 62,67 50,59 38,67 42,54 32,46 45,46" fill="#f3e7b4"/>
                        </svg>
                        AcadNexus
                    </div>

                    <div class="pf-photo-wrap" role="button" tabindex="0" aria-label="Change profile photo"
                         onclick="openModal('uploadPhotoModal')"
                         onkeydown="if(event.key==='Enter'||event.key===' '){openModal('uploadPhotoModal');}">
                        <div class="pf-photo">
                            <?php if ($hasAvatar): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Photo of <?php echo htmlspecialchars($fullName); ?>">
                            <?php else: ?>
                                <?php echo htmlspecialchars($initials); ?>
                            <?php endif; ?>
                            <div class="pf-photo-hover"><span style="font-size:20px;">📷</span><span>Change photo</span></div>
                        </div>
                        <div class="pf-verified" title="Verified active student">✓</div>
                    </div>

                    <h2 class="pf-id-name"><?php echo htmlspecialchars($fullName); ?></h2>
                    <div class="pf-id-prog"><?php echo htmlspecialchars($program); ?></div>
                </div>

                <div class="pf-id-rows">
                    <div class="full">
                        <div class="k">Registration / roll number</div>
                        <div class="v" style="font-size:16px; color:#f3e7b4; letter-spacing:0.5px;"><?php echo htmlspecialchars($regNumber); ?></div>
                    </div>
                    <div>
                        <div class="k">Student ID</div>
                        <div class="v"><?php echo htmlspecialchars($studentIdCode); ?></div>
                    </div>
                    <div>
                        <div class="k">Batch</div>
                        <div class="v"><?php echo htmlspecialchars($batchStr); ?></div>
                    </div>
                </div>

                <div class="pf-id-bottom">
                    <div class="pf-qr">
                        <img src="<?php echo htmlspecialchars($realQrApiUrl); ?>" alt="QR code for student verification" crossorigin="anonymous">
                    </div>
                    <div class="pf-id-note">
                        <b>Scan to verify</b>
                        Shows this student's registration number, program and enrolment status.
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
                        <span class="pf-tag teal">Year <?php echo $yearOfStudy; ?>, semester <?php echo $semesterVal; ?></span>
                        <span class="pf-tag">Section <?php echo htmlspecialchars($sectionVal); ?></span>
                        <span class="pf-tag brass">Verified active student</span>
                    </div>
                </div>
                <div class="pf-actions">
                    <button type="button" class="pf-btn" onclick="openModal('uploadPhotoModal')">📷 Upload photo</button>
                    <button type="button" class="pf-btn" id="downloadPdfBtn" onclick="downloadPDFPass()">📄 Download pass</button>
                    <button type="button" class="pf-btn primary" onclick="openModal('editProfileModal')">✏️ Edit profile</button>
                </div>
            </div>

            <!-- Academic summary -->
            <section class="pf-section">
                <h2>Academic summary</h2>
                <div class="pf-summary">
                    <div class="pf-ring" role="img" aria-label="CGPA <?php echo htmlspecialchars($cgpaVal); ?> out of 10">
                        <svg viewBox="0 0 120 120">
                            <circle class="track" cx="60" cy="60" r="52" fill="none" stroke-width="10"/>
                            <circle class="bar" cx="60" cy="60" r="52" fill="none" stroke-width="10"
                                    stroke-dasharray="326.7" stroke-dashoffset="<?php echo round(326.7 * (1 - $cgpaPercent / 100), 1); ?>"/>
                        </svg>
                        <div class="pf-ring-center">
                            <div class="num"><?php echo htmlspecialchars($cgpaVal); ?></div>
                            <div class="lbl">CGPA out of 10</div>
                        </div>
                    </div>
                    <div class="pf-facts">
                        <div class="pf-fact"><div class="k">Program</div><div class="v"><?php echo htmlspecialchars($program); ?></div></div>
                        <div class="pf-fact"><div class="k">Batch</div><div class="v"><?php echo htmlspecialchars($batchStr); ?></div></div>
                        <div class="pf-fact"><div class="k">Department</div><div class="v"><?php echo htmlspecialchars($departmentName); ?></div></div>
                        <div class="pf-fact"><div class="k">Location</div><div class="v"><?php echo htmlspecialchars($locationStr); ?></div></div>
                    </div>
                </div>
            </section>

            <!-- About -->
            <section class="pf-section">
                <h2>About</h2>
                <?php if (!empty($bioText)): ?>
                    <p class="pf-bio">“<?php echo htmlspecialchars($bioText); ?>”</p>
                <?php else: ?>
                    <p class="pf-empty">You haven't added a bio yet. Use Edit profile to add a short statement about yourself.</p>
                <?php endif; ?>
            </section>

            <!-- Contact -->
            <section class="pf-section">
                <h2>Contact and links</h2>
                <div class="pf-contact">
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">✉️</div>
                        <div style="min-width:0;"><div class="k">Email</div><div class="v"><?php echo htmlspecialchars($student['email'] ?? ''); ?></div></div>
                    </div>
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">📞</div>
                        <div style="min-width:0;"><div class="k">Phone</div><div class="v"><?php echo htmlspecialchars($phoneDisplay ?: 'Not provided'); ?></div></div>
                    </div>
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">💼</div>
                        <div style="min-width:0;"><div class="k">LinkedIn</div><div class="v"><?php echo htmlspecialchars($linkedinDisplay ?: 'Not provided'); ?></div></div>
                    </div>
                    <div class="pf-contact-item">
                        <div class="pf-contact-ico">🐙</div>
                        <div style="min-width:0;"><div class="k">GitHub</div><div class="v"><?php echo htmlspecialchars($githubDisplay ?: 'Not provided'); ?></div></div>
                    </div>
                </div>
            </section>

            <!-- Marks -->
            <section class="pf-section">
                <h2>Course performance</h2>
                <?php if (!empty($dbMarks)): ?>
                    <div class="pf-table-scroll">
                        <table class="pf-table">
                            <thead>
                                <tr><th>Code</th><th>Course</th><th>Credits</th><th>Marks</th><th>Grade</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($dbMarks as $m): ?>
                                <tr>
                                    <td class="pf-code"><?php echo htmlspecialchars($m['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($m['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars((string)($m['credits'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($m['total_marks'] ?? 'N/A')); ?></td>
                                    <td><span class="pf-grade <?php echo gradeClass($m['grade']); ?>"><?php echo htmlspecialchars($m['grade'] ?: 'N/A'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="pf-empty">No course marks have been published for you yet.</p>
                <?php endif; ?>
            </section>

            <!-- Projects -->
            <section class="pf-section">
                <h2>Projects</h2>
                <?php if (!empty($dbProjects)): ?>
                    <div class="pf-projects">
                    <?php foreach ($dbProjects as $p): $pp = max(0, min(100, (int)($p['progress_percent'] ?? 0))); ?>
                        <div class="pf-project">
                            <div>
                                <h3><?php echo htmlspecialchars($p['title']); ?></h3>
                                <?php if (!empty($p['fac_fn'])): ?>
                                    <div style="font-size: 12px; color: #4f46e5; font-weight: 600; margin: 2px 0 4px 0;">
                                        Faculty Advisor: Prof. <?php echo htmlspecialchars($p['fac_fn'] . ' ' . $p['fac_ln']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="tech"><?php echo htmlspecialchars($p['technologies'] ?: 'Technologies not listed'); ?></div>
                                <p class="desc"><?php echo htmlspecialchars($p['description'] ?: 'No description added.'); ?></p>
                            </div>
                            <div class="pf-progress">
                                <div class="top"><span><?php echo htmlspecialchars(ucfirst((string)($p['status'] ?? 'Approved'))); ?></span><b><?php echo $pp; ?>%</b></div>
                                <div class="rail"><div class="fill" style="width: <?php echo $pp; ?>%;"></div></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="pf-empty">No approved projects assigned by faculty yet.</p>
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
                <h3>Upload profile photo</h3>
                <p>Saved in <code>uploads/<?php echo htmlspecialchars($studentUploadSubdir); ?>/</code></p>
            </div>
            <button type="button" class="pf-close" aria-label="Close" onclick="closeModal('uploadPhotoModal')">✕</button>
        </div>

        <form id="avatarUploadForm" action="profile.php" method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="pf-drop" id="dropZone" onclick="document.getElementById('avatarFileInput').click()">
                <div class="big">☁️</div>
                <div class="t">Drag and drop your photo here</div>
                <div class="s">PNG, JPG or JPEG. A clear, front-facing portrait works best.</div>
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

<!-- ================= EDIT PROFILE MODAL ================= -->
<div id="editProfileModal" class="pf-modal" onclick="if(event.target===this)closeModal('editProfileModal')">
    <div class="pf-modal-box" style="max-width: 700px;">
        <div class="pf-modal-head">
            <div>
                <h3>Edit profile</h3>
                <p>Update your name, roll number, program, batch, department, CGPA and links.</p>
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

            <div class="pf-field-grid c21">
                <div class="pf-field">
                    <label for="f_reg">Registration / roll number *</label>
                    <input id="f_reg" type="text" name="reg_number" value="<?php echo htmlspecialchars($regNumber); ?>" required style="font-weight:700; color:var(--pf-teal);">
                </div>
                <div class="pf-field">
                    <label for="f_prog">Program / degree</label>
                    <input id="f_prog" type="text" name="program" value="<?php echo htmlspecialchars($student['program'] ?? 'Computer Science'); ?>" required>
                </div>
            </div>

            <div class="pf-batch-box">
                <div class="t">Batch duration (currently <?php echo htmlspecialchars($batchStr); ?>)</div>
                <div class="pf-field-grid c2" style="margin-bottom:0;">
                    <div class="pf-field">
                        <label for="f_start">Start year</label>
                        <input id="f_start" type="number" name="admission_year" value="<?php echo $startYr; ?>" min="2015" max="2035" required>
                    </div>
                    <div class="pf-field">
                        <label for="f_end">End year</label>
                        <input id="f_end" type="number" name="graduation_year" value="<?php echo $endYr; ?>" min="2015" max="2035" required>
                    </div>
                </div>
            </div>

            <div class="pf-field-grid c12">
                <div class="pf-field">
                    <label for="f_dept">Academic department</label>
                    <select id="f_dept" name="department_id">
                        <?php foreach ($allDepartments as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo (($student['department_id'] ?? null) == $d['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pf-field">
                    <label for="f_cgpa">CGPA (out of 10)</label>
                    <input id="f_cgpa" type="number" step="0.01" min="0" max="10" name="cgpa" value="<?php echo htmlspecialchars($cgpaInput); ?>" required>
                </div>
            </div>

            <div class="pf-field-grid c2">
                <div class="pf-field">
                    <label for="f_phone">Phone number</label>
                    <input id="f_phone" type="text" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" placeholder="+91 98765 43210">
                </div>
                <div class="pf-field">
                    <label for="f_sid">System student ID</label>
                    <input id="f_sid" type="text" value="<?php echo htmlspecialchars($studentIdCode); ?>" disabled>
                </div>
            </div>

            <div class="pf-field-grid c2">
                <div class="pf-field">
                    <label for="f_li">LinkedIn profile URL</label>
                    <input id="f_li" type="text" name="linkedin_url" value="<?php echo htmlspecialchars($linkedinDisplay); ?>" placeholder="linkedin.com/in/username">
                </div>
                <div class="pf-field">
                    <label for="f_gh">GitHub profile URL</label>
                    <input id="f_gh" type="text" name="github_url" value="<?php echo htmlspecialchars($githubDisplay); ?>" placeholder="github.com/username">
                </div>
            </div>

            <div class="pf-field" style="margin-bottom:8px;">
                <label for="f_bio">Bio or personal statement</label>
                <textarea id="f_bio" name="bio" rows="3" style="resize:vertical;"><?php echo htmlspecialchars($bioText); ?></textarea>
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

/* ---------- PDF pass ---------- */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function gradeColors(g) {
    const c = String(g || '').trim().toUpperCase().charAt(0);
    if (c === 'O' || c === 'A') return ['#dff5ea', '#0b6b44'];
    if (c === 'B') return ['#e3f2f3', '#0a5a62'];
    if (c === 'C' || c === 'D') return ['#f7efd2', '#7a6209'];
    if (!c) return ['#eef0f5', '#667089'];
    return ['#fde4e4', '#9b1c1c'];
}

async function downloadPDFPass() {
    const btn = document.getElementById('downloadPdfBtn');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = 'Preparing PDF…'; }

    const d = {
        name: <?php echo json_encode($fullName); ?>,
        regNo: <?php echo json_encode($regNumber); ?>,
        studentId: <?php echo json_encode($studentIdCode); ?>,
        program: <?php echo json_encode($program); ?>,
        dept: <?php echo json_encode($departmentName); ?>,
        batch: <?php echo json_encode($batchStr); ?>,
        level: <?php echo json_encode('Year ' . $yearOfStudy . ', semester ' . $semesterVal . ' (section ' . $sectionVal . ')'); ?>,
        cgpa: <?php echo json_encode($cgpaVal); ?>,
        cgpaPct: <?php echo json_encode($cgpaPercent); ?>,
        email: <?php echo json_encode($student['email'] ?? ''); ?>,
        phone: <?php echo json_encode($phoneDisplay ?: 'Not provided'); ?>,
        address: <?php echo json_encode($locationStr); ?>,
        dob: <?php echo json_encode(!empty($student['dob']) ? date('d M Y', strtotime($student['dob'])) : 'Not provided'); ?>,
        gender: <?php echo json_encode(!empty($student['gender']) ? ucfirst($student['gender']) : 'Not provided'); ?>,
        linkedin: <?php echo json_encode($linkedinDisplay ?: 'Not provided'); ?>,
        github: <?php echo json_encode($githubDisplay ?: 'Not provided'); ?>,
        bio: <?php echo json_encode($bioText ?: 'Active student enrolled in the university program.'); ?>,
        avatarUrl: <?php echo json_encode($avatarUrl); ?>,
        initials: <?php echo json_encode($initials); ?>,
        qrUrl: <?php echo json_encode($realQrApiUrl); ?>,
        generatedAt: <?php echo json_encode(date('d M Y, h:i A T')); ?>,
        projects: <?php echo json_encode($dbProjects); ?>,
        marks: <?php echo json_encode($dbMarks); ?>
    };

    const SERIF = "'Newsreader', Georgia, serif";
    const SANS = "'Inter', -apple-system, 'Segoe UI', Roboto, sans-serif";

    const photoHtml = d.avatarUrl
        ? `<img src="${esc(d.avatarUrl)}" style="width:100%;height:100%;object-fit:cover;display:block;">`
        : `<div style="width:100%;height:100%;background:#1b2a57;display:flex;align-items:center;justify-content:center;color:#f3e7b4;font-family:${SERIF};font-size:46px;font-weight:600;">${esc(d.initials)}</div>`;

    const marksRows = (d.marks && d.marks.length)
        ? d.marks.map(m => {
            const [bg, fg] = gradeColors(m.grade);
            return `<tr>
                <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;font-weight:700;color:#0e7c86;">${esc(m.course_code)}</td>
                <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;">${esc(m.course_name)}</td>
                <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;text-align:center;">${esc(m.credits ?? '-')}</td>
                <td style="padding:7px 8px 7px 0;border-bottom:1px solid #e3e6ee;text-align:center;">${esc(m.total_marks ?? 'N/A')}</td>
                <td style="padding:7px 0;border-bottom:1px solid #e3e6ee;text-align:center;"><span style="display:inline-block;min-width:28px;padding:2px 8px;border-radius:6px;background:${bg};color:${fg};font-weight:700;">${esc(m.grade || 'N/A')}</span></td>
            </tr>`;
        }).join('')
        : `<tr><td colspan="5" style="padding:10px 0;color:#667089;">No course marks have been published yet.</td></tr>`;

    const projectRows = (d.projects && d.projects.length)
        ? d.projects.map(p => {
            const pct = Math.max(0, Math.min(100, parseInt(p.progress_percent || 0, 10)));
            const desc = p.description ? (p.description.length > 120 ? p.description.substring(0, 120) + '…' : p.description) : 'No description added.';
            const facStr = p.fac_fn ? `<div style="font-size:10px;color:#4f46e5;font-weight:600;margin-top:1px;">Faculty Advisor: Prof. ${esc(p.fac_fn)} ${esc(p.fac_ln)}</div>` : '';
            return `<div style="padding:8px 0;border-bottom:1px solid #e3e6ee;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;">
                    <div style="font-weight:700;color:#0f1b3d;font-size:12px;">${esc(p.title || 'Academic project')}</div>
                    <div style="font-size:10px;color:#667089;">${esc(p.status ? p.status.charAt(0).toUpperCase() + p.status.slice(1) : 'Approved')} · ${pct}%</div>
                </div>
                ${facStr}
                <div style="font-size:10px;color:#0e7c86;font-weight:600;margin:1px 0 2px 0;">${esc(p.technologies || 'Technologies not listed')}</div>
                <div style="font-size:10px;color:#667089;line-height:1.4;">${esc(desc)}</div>
                <div style="height:4px;background:#e3e6ee;border-radius:99px;margin-top:5px;overflow:hidden;"><div style="height:100%;width:${pct}%;background:#0e7c86;"></div></div>
            </div>`;
        }).join('')
        : `<div style="padding:8px 0;color:#667089;font-size:11px;">No approved projects assigned by faculty yet.</div>`;

    const sideRow = (k, v) => `<div style="margin-bottom:9px;">
        <div style="font-size:9px;color:#8a94ad;margin-bottom:1px;">${k}</div>
        <div style="font-size:10.5px;font-weight:600;color:#1c2438;word-break:break-word;line-height:1.35;">${esc(v)}</div>
    </div>`;

    const idRow = (k, v, strong) => `<td style="padding:6px 10px 6px 0;vertical-align:top;width:50%;">
        <div style="font-size:9px;color:#8a94ad;margin-bottom:1px;">${k}</div>
        <div style="font-size:12px;font-weight:${strong ? 700 : 600};color:${strong ? '#0f1b3d' : '#1c2438'};word-break:break-word;">${esc(v)}</div>
    </td>`;

    const ringC = 2 * Math.PI * 26;
    const ringOffset = (ringC * (1 - (d.cgpaPct || 0) / 100)).toFixed(1);

    // A4 at 96dpi ≈ 794 x 1123 px
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
                <div style="display:inline-block;padding:6px 14px;border-radius:999px;border:1px solid #c9a227;color:#f3e7b4;font-size:11px;font-weight:600;">Official student pass</div>
                <div style="font-size:11px;color:#b9c3e0;margin-top:10px;">Session ${esc(d.batch)}</div>
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
                <div style="font-family:${SERIF};font-size:22px;font-weight:600;color:#0f1b3d;line-height:1.15;margin-top:16px;">${esc(d.name)}</div>
                <div style="font-size:11px;color:#667089;margin:4px 0 14px 0;">${esc(d.program)}</div>

                <div style="display:inline-block;padding:4px 10px;border-radius:999px;background:#dff5ea;color:#0b6b44;font-size:10px;font-weight:700;margin-bottom:18px;">✓ Verified active student</div>

                <div style="border-top:1px solid #e3e6ee;padding-top:14px;">
                    ${sideRow('Email', d.email)}
                    ${sideRow('Phone', d.phone)}
                    ${sideRow('LinkedIn', d.linkedin)}
                    ${sideRow('GitHub', d.github)}
                    ${sideRow('Address', d.address)}
                </div>

                <div style="position:absolute;left:28px;right:24px;bottom:20px;background:#ffffff;border:1px solid #e3e6ee;border-radius:12px;padding:10px;text-align:center;">
                    <img src="${esc(d.qrUrl)}" crossorigin="anonymous" style="width:96px;height:96px;display:block;margin:0 auto;" alt="QR">
                    <div style="font-size:9.5px;font-weight:700;color:#0f1b3d;margin-top:6px;">Scan to verify</div>
                    <div style="font-size:8.5px;color:#8a94ad;margin-top:1px;">Pass ID ${esc(d.regNo)}</div>
                </div>
            </div>

            <!-- Main -->
            <div style="flex:1;box-sizing:border-box;padding:24px 36px 0 32px;">

                <!-- Identity grid -->
                <div style="font-family:${SERIF};font-size:17px;font-weight:600;color:#0f1b3d;margin-bottom:6px;">Student identity</div>
                <table style="width:100%;border-collapse:collapse;table-layout:fixed;border-top:2px solid #0f1b3d;">
                    <tr>${idRow('Registration / roll number', d.regNo, true)}${idRow('Student ID', d.studentId, true)}</tr>
                    <tr>${idRow('Program', d.program)}${idRow('Academic level', d.level)}</tr>
                    <tr>${idRow('Gender', d.gender)}${idRow('Date of birth', d.dob)}</tr>
                    <tr>${idRow('Batch', d.batch)}${idRow('Department', d.dept)}</tr>
                </table>

                <!-- CGPA strip -->
                <div style="display:flex;align-items:center;gap:18px;margin:14px 0 4px 0;padding:12px 16px;background:#f5f6fa;border:1px solid #e3e6ee;border-left:4px solid #c9a227;border-radius:10px;">
                    <div style="position:relative;width:64px;height:64px;flex-shrink:0;">
                        <svg width="64" height="64" viewBox="0 0 64 64" style="transform:rotate(-90deg);">
                            <circle cx="32" cy="32" r="26" fill="none" stroke="#e3e6ee" stroke-width="6"/>
                            <circle cx="32" cy="32" r="26" fill="none" stroke="#0e7c86" stroke-width="6" stroke-linecap="round" stroke-dasharray="${ringC.toFixed(1)}" stroke-dashoffset="${ringOffset}"/>
                        </svg>
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:${SERIF};font-size:15px;font-weight:600;color:#0f1b3d;">${esc(d.cgpa)}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#0f1b3d;">Cumulative grade point average</div>
                        <div style="font-size:10.5px;color:#667089;margin-top:2px;">${esc(d.cgpa)} out of 10.00 across all published courses.</div>
                    </div>
                </div>

                <!-- Courses -->
                <div style="font-family:${SERIF};font-size:17px;font-weight:600;color:#0f1b3d;margin:16px 0 4px 0;">Course performance</div>
                <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:10.5px;">
                    <thead>
                        <tr style="border-bottom:2px solid #0f1b3d;">
                            <th style="text-align:left;padding:0 8px 6px 0;color:#667089;font-weight:600;width:70px;">Code</th>
                            <th style="text-align:left;padding:0 8px 6px 0;color:#667089;font-weight:600;">Course</th>
                            <th style="text-align:center;padding:0 8px 6px 0;color:#667089;font-weight:600;width:52px;">Credits</th>
                            <th style="text-align:center;padding:0 8px 6px 0;color:#667089;font-weight:600;width:50px;">Marks</th>
                            <th style="text-align:center;padding:0 0 6px 0;color:#667089;font-weight:600;width:54px;">Grade</th>
                        </tr>
                    </thead>
                    <tbody>${marksRows}</tbody>
                </table>

                <!-- Projects -->
                <div style="font-family:${SERIF};font-size:17px;font-weight:600;color:#0f1b3d;margin:16px 0 2px 0;">Projects</div>
                <div style="border-top:2px solid #0f1b3d;">${projectRows}</div>

                <!-- Bio -->
                <div style="font-family:${SERIF};font-size:17px;font-weight:600;color:#0f1b3d;margin:16px 0 6px 0;">Statement</div>
                <div style="font-family:${SERIF};font-style:italic;font-size:12.5px;line-height:1.55;color:#38425c;border-left:3px solid #c9a227;padding-left:12px;">“${esc(d.bio)}”</div>
            </div>
        </div>

        <!-- Footer -->
        <div style="position:absolute;left:0;right:0;bottom:0;height:70px;background:#0f1b3d;color:#ffffff;display:flex;align-items:center;justify-content:space-between;padding:0 36px;box-sizing:border-box;">
            <div>
                <div style="font-size:9.5px;color:#f3e7b4;font-weight:600;">Document ID ANX-PASS-${esc(d.regNo)}</div>
                <div style="font-size:9px;color:#94a1c6;margin-top:3px;">Generated ${esc(d.generatedAt)}. Verify authenticity by scanning the QR code.</div>
            </div>
            <div style="text-align:center;">
                <div style="font-family:${SERIF};font-style:italic;font-size:14px;color:#f3e7b4;">Office of the University Registrar</div>
                <div style="border-top:1px solid #c9a227;margin-top:3px;padding-top:3px;font-size:8.5px;color:#94a1c6;">Authorised signatory and seal</div>
            </div>
        </div>
    </div>`;

    // Must sit inside the layout tree (not off-screen) for html2canvas to measure correctly
    const tempDiv = document.createElement('div');
    tempDiv.style.cssText = 'position:fixed;top:0;left:0;width:794px;opacity:0;pointer-events:none;z-index:-9999;';
    tempDiv.innerHTML = passContent;
    document.body.appendChild(tempDiv);
    const exportTarget = tempDiv.querySelector('#passCardExport');

    const cleanup = () => {
        if (document.body.contains(tempDiv)) document.body.removeChild(tempDiv);
        if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
    };

    try {
        if (document.fonts && document.fonts.ready) await document.fonts.ready;
        // Wait for photo + QR to finish loading
        await Promise.all(Array.from(exportTarget.querySelectorAll('img')).map(img =>
            img.complete ? Promise.resolve() : new Promise(res => { img.onload = img.onerror = res; })
        ));

        if (typeof html2pdf === 'undefined') throw new Error('html2pdf not loaded');

        await html2pdf().set({
            margin: 0,
            filename: 'Student_Pass_' + d.regNo + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false, width: 794, windowWidth: 794, scrollX: 0, scrollY: 0 },
            jsPDF: { unit: 'px', format: [794, 1120], orientation: 'portrait', hotfixes: ['px_scaling'] },
            pagebreak: { mode: ['avoid-all'] }
        }).from(exportTarget).save();
        cleanup();
    } catch (err) {
        console.error('PDF generation failed, falling back to print window:', err);
        cleanup();
        openPrintWindow(passContent);
    }
}

function openPrintWindow(htmlContent) {
    const w = window.open('', '_blank', 'width=820,height=960');
    if (!w) return;
    w.document.write(`<html><head><title>Student pass</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,500;0,6..72,600;1,6..72,500&display=swap" rel="stylesheet">
        <style>@page { size: 794px 1120px; margin: 0; } body { margin: 0; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }</style>
        </head><body>${htmlContent}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 700);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>