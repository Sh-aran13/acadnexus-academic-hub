<?php
// student/results.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

$stmt = $pdo->prepare("
    SELECT c.code as course_code, c.name as course_name, c.credits,
           COALESCE(m.internal_marks, 0) as internal,
           COALESCE(m.assignment_marks, 0) as assignment,
           COALESCE(m.quiz_marks, 0) as quiz,
           COALESCE(m.mid_exam, 0) as mid,
           COALESCE(m.final_exam, 0) as final,
           COALESCE(m.total_marks, 0) as total,
           COALESCE(m.grade, 'P') as grade
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN marks m ON c.id = m.course_id AND m.student_id = ?
    WHERE ce.student_id = ?
");
$stmt->execute([$studentId, $studentId]);
$results = $stmt->fetchAll();

// Calculate total credits and average
$totalCredits = 0;
$scoreSum = 0;
foreach ($results as $r) {
    $totalCredits += (int)$r['credits'];
    $scoreSum += (float)$r['total'];
}
$totalCourses = count($results);
$overallAvg = ($totalCourses > 0) ? round($scoreSum / $totalCourses, 1) : 0;

$pageTitle = 'Academic Results & Marks';
$pageHeading = 'Examination & Grades Report';
$activeNav = 'results';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .results-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #7c3aed 100%);
        border-radius: 24px;
        padding: 32px 40px;
        color: #ffffff;
        margin-bottom: 32px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .results-hero h2 {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }

    .results-hero p {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.85);
    }

    .gpa-card-box {
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(12px);
        padding: 14px 28px;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        text-align: center;
    }

    .gpa-card-score {
        font-size: 34px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1;
    }

    .gpa-card-label {
        font-size: 11px;
        font-weight: 700;
        color: #c084fc;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    /* Actions Bar */
    .report-actions-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .btn-print {
        padding: 10px 20px;
        border-radius: 12px;
        background: #ffffff;
        color: var(--text-main);
        border: 1px solid var(--border-color);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: all 0.2s;
    }

    .btn-print:hover {
        background: var(--accent-gradient);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 4px 14px var(--accent-glow);
    }

    /* Results Ledger Table Card */
    .results-ledger-card {
        background: #ffffff;
        border-radius: 22px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 25px rgba(15, 23, 42, 0.03);
        overflow: hidden;
        margin-bottom: 32px;
    }

    .ledger-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ledger-table th {
        background: #f8fafc;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #64748b;
        padding: 16px 20px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    .ledger-table td {
        padding: 18px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }

    .ledger-table tr:hover {
        background: #f8fafc;
    }

    .grade-badge-custom {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 800;
        color: #ffffff;
    }

    .breakdown-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }

    @media (max-width: 768px) {
        .results-hero {
            padding: 24px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }
        .results-hero h2 {
            font-size: 22px;
        }
        .gpa-card-box {
            width: 100%;
            padding: 12px 18px;
        }
        .report-actions-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .btn-print {
            width: 100%;
            justify-content: center;
        }
    }

    @media print {
        .app-sidebar, .app-header, .report-actions-bar { display: none !important; }
        .app-main { margin-left: 0 !important; }
        .results-hero { background: #0f172a !important; color: #fff !important; }
    }
</style>

<!-- Hero Section -->
<div class="results-hero">
    <div>
        <h2>Semester Mark Ledger 📊</h2>
        <p>Comprehensive transcript breakdown across internal assessments, mid exams, quizzes, and final grades.</p>
    </div>

    <div class="gpa-card-box">
        <div class="gpa-card-score"><?php echo $overallAvg; ?>%</div>
        <div class="gpa-card-label">Overall Average Score</div>
    </div>
</div>

<!-- Report Actions Bar -->
<div class="report-actions-bar">
    <div style="font-size: 14px; font-weight: 700; color: var(--text-muted);">
        Enrolled Subjects: <strong><?php echo $totalCourses; ?></strong> • Total Credits: <strong><?php echo $totalCredits; ?></strong>
    </div>

    <button onclick="window.print()" class="btn-print">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        <span>Print Official Grade Sheet</span>
    </button>
</div>

<!-- Ledger Table -->
<div class="results-ledger-card">
    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="ledger-table" style="min-width: 680px;">
        <thead>
            <tr>
                <th>Subject & Code</th>
                <th>Credits</th>
                <th>Internal (20)</th>
                <th>Assignment (25)</th>
                <th>Quiz (10)</th>
                <th>Mid Exam (30)</th>
                <th>Total Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td>
                        <strong style="color: var(--accent); font-size: 15px;"><?php echo htmlspecialchars($r['course_code']); ?></strong>
                        <div style="font-size: 13px; color: var(--text-main); font-weight: 700; margin-top: 2px;"><?php echo htmlspecialchars($r['course_name']); ?></div>
                    </td>
                    <td><span class="breakdown-pill"><?php echo $r['credits']; ?> Credits</span></td>
                    <td><span class="breakdown-pill"><?php echo $r['internal']; ?></span></td>
                    <td><span class="breakdown-pill"><?php echo $r['assignment']; ?></span></td>
                    <td><span class="breakdown-pill"><?php echo $r['quiz']; ?></span></td>
                    <td><span class="breakdown-pill"><?php echo $r['mid']; ?></span></td>
                    <td>
                        <strong style="font-size: 18px; color: var(--text-main);"><?php echo $r['total']; ?></strong>
                        <span style="font-size: 12px; color: var(--text-muted);">/ 100</span>
                    </td>
                    <td>
                        <?php 
                            $gColor = 'linear-gradient(135deg, #10b981, #059669)';
                            if ($r['grade'] === 'F') $gColor = 'linear-gradient(135deg, #ef4444, #dc2626)';
                            elseif (strpos($r['grade'], 'B') !== false) $gColor = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
                            elseif (strpos($r['grade'], 'A') !== false) $gColor = 'linear-gradient(135deg, #7c3aed, #6d28d9)';
                        ?>
                        <span class="grade-badge-custom" style="background: <?php echo $gColor; ?>;"><?php echo $r['grade']; ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
