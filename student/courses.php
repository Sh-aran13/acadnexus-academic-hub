<?php
// student/courses.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];
$deptId = $student['department_id'];

// Handle Manual Course Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    if ($_POST['action'] === 'enroll_single') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            try {
                // Get course details & verify department matching
                $cStmt = $pdo->prepare("SELECT semester, department_id FROM courses WHERE id = ?");
                $cStmt->execute([$courseId]);
                $courseData = $cStmt->fetch();

                if (!$courseData) {
                    setToast('error', 'Selected course does not exist.');
                } elseif (!$deptId || (int)$courseData['department_id'] !== (int)$deptId) {
                    setToast('error', 'Restricted: You can only enroll in courses belonging to your department.');
                } else {
                    $sem = $courseData['semester'] ?: 1;

                    $insStmt = $pdo->prepare("
                        INSERT INTO course_enrollments (student_id, course_id, semester) 
                        VALUES (?, ?, ?) 
                        ON CONFLICT (student_id, course_id, academic_year) DO NOTHING
                    ");
                    $insStmt->execute([$studentId, $courseId, $sem]);

                    setToast('success', 'Enrolled in course & auto-assigned to dealing faculty!');
                    logActivity($userId, 'Enrolled in course ID: ' . $courseId);
                }
                header("Location: courses.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Enrollment failed: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'auto_enroll_dept') {
        // Auto-enroll student strictly into courses relevant to their department
        if ($deptId > 0) {
            try {
                $deptCoursesStmt = $pdo->prepare("SELECT id, semester FROM courses WHERE department_id = ?");
                $deptCoursesStmt->execute([$deptId]);
                $deptCourses = $deptCoursesStmt->fetchAll();

                if (empty($deptCourses)) {
                    setToast('warning', 'No courses found for your department.');
                } else {
                    $enrolledCount = 0;
                    $insStmt = $pdo->prepare("
                        INSERT INTO course_enrollments (student_id, course_id, semester) 
                        VALUES (?, ?, ?) 
                        ON CONFLICT (student_id, course_id, academic_year) DO NOTHING
                    ");

                    foreach ($deptCourses as $dc) {
                        $insStmt->execute([$studentId, $dc['id'], $dc['semester']]);
                        $enrolledCount++;
                    }

                    setToast('success', "Auto-enrolled into {$enrolledCount} department courses!");
                    logActivity($userId, 'Auto-enrolled all department courses');
                }
                header("Location: courses.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Auto-enrollment failed: ' . $e->getMessage());
            }
        } else {
            setToast('error', 'Please set your Department in Profile before auto-enrolling.');
        }
    }
}

// 1. Query currently enrolled courses with assigned faculty info
$enrolledStmt = $pdo->prepare("
    SELECT c.*, d.name as dept_name,
           f.first_name as faculty_fn, f.last_name as faculty_ln, f.designation, f.office_room,
           (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) as materials_count,
           (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id AND a.is_published = TRUE) as assignments_count
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN faculty_courses fc ON c.id = fc.course_id
    LEFT JOIN faculty_profiles f ON fc.faculty_id = f.id
    WHERE ce.student_id = ?
    ORDER BY c.code ASC
");
$enrolledStmt->execute([$studentId]);
$enrolledCourses = $enrolledStmt->fetchAll();

// Extract enrolled course IDs
$enrolledCourseIds = array_column($enrolledCourses, 'id');

// 2. Query Available Course Catalogue (Prioritize student's department)
$catalogueStmt = $pdo->prepare("
    SELECT c.*, d.name as dept_name, d.code as dept_code,
           f.first_name as faculty_fn, f.last_name as faculty_ln, f.designation
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN faculty_courses fc ON c.id = fc.course_id
    LEFT JOIN faculty_profiles f ON fc.faculty_id = f.id
    ORDER BY (c.department_id = ?) DESC, c.code ASC
");
$catalogueStmt->execute([$deptId ?: 0]);
$allCatalogueCourses = $catalogueStmt->fetchAll();

$pageTitle = 'My Courses & Department Catalogue';
$pageHeading = 'Course Enrollment & Catalogue';
$activeNav = 'courses';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ===================================================
   STUDENT COURSES - ULTRA-MODERN MOBILE-FIRST STYLING
=================================================== */
.crs-page-wrap {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
}

/* Modern Gradient Hero Banner */
.crs-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #1e40af 100%);
    border-radius: 22px;
    padding: 28px 32px;
    color: #ffffff;
    margin-bottom: 24px;
    box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.28);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-sizing: border-box;
}

.crs-hero::before {
    content: '';
    position: absolute;
    right: -40px;
    top: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.35) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.crs-hero-left {
    z-index: 2;
    max-width: 600px;
}

.crs-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #93c5fd;
    margin-bottom: 6px;
}

.crs-hero-left h2 {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.4px;
    line-height: 1.25;
    margin-bottom: 6px;
    color: #ffffff;
}

.crs-hero-left p {
    font-size: 13.5px;
    color: rgba(255, 255, 255, 0.82);
    line-height: 1.45;
    margin: 0;
}

.crs-hero-right {
    z-index: 2;
    flex-shrink: 0;
}

.crs-auto-btn {
    padding: 13px 22px;
    border-radius: 14px;
    background: #ffffff;
    color: #1e1b4b;
    border: none;
    font-weight: 800;
    font-size: 13.5px;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.crs-auto-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(255, 255, 255, 0.28);
    background: #f8fafc;
}

.crs-auto-btn:active {
    transform: scale(0.97);
}

/* Section Header Titles */
.crs-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    margin-top: 28px;
}

.crs-section-head:first-of-type {
    margin-top: 0;
}

.crs-section-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: -0.3px;
    margin: 0;
}

.crs-section-badge {
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 11px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 800;
}

/* Course Grid */
.crs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
    width: 100%;
    max-width: 100%;
}

/* Premium Course Card */
.crs-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
}

.crs-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.crs-card-top {
    margin-bottom: 16px;
}

.crs-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.crs-code-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.4px;
}

.crs-credits-pill {
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 3px 8px;
    border-radius: 6px;
}

.crs-dept-tag {
    font-size: 11px;
    font-weight: 700;
    color: #047857;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 3px 8px;
    border-radius: 6px;
}

.crs-title {
    font-size: 16.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.35;
    margin-bottom: 6px;
    word-break: break-word;
}

.crs-dept-name {
    font-size: 12.5px;
    color: #64748b;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Compact Faculty Profile Box */
.crs-faculty-box {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.crs-faculty-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);
}

.crs-faculty-info {
    min-width: 0;
    flex: 1;
}

.crs-faculty-name {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.crs-faculty-desig {
    font-size: 11.5px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Card Bottom Stats & Actions */
.crs-card-bottom {
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.crs-card-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    background: #f8fafc;
    padding: 8px 12px;
    border-radius: 10px;
}

.crs-card-stat-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

.crs-card-stat-item strong {
    color: #0f172a;
}

/* Action Buttons */
.crs-enrolled-badge {
    width: 100%;
    padding: 11px;
    border-radius: 12px;
    background: #ecfdf5;
    color: #047857;
    font-weight: 800;
    font-size: 13px;
    text-align: center;
    border: 1px solid #a7f3d0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-sizing: border-box;
}

.crs-enroll-btn {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff;
    font-weight: 800;
    font-size: 13.5px;
    border: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-sizing: border-box;
}

.crs-enroll-btn:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
}

.crs-enroll-btn:active {
    transform: scale(0.98);
}

.crs-restricted-btn {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
    font-size: 12.5px;
    font-weight: 700;
    cursor: not-allowed;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    box-sizing: border-box;
}

/* ===================================================
   MOBILE SCREEN ALIGNMENT & ERGONOMIC ADAPTATION
=================================================== */
@media (max-width: 768px) {
    .crs-hero {
        padding: 20px 16px;
        border-radius: 18px;
        margin-bottom: 20px;
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
    }

    .crs-hero-left h2 {
        font-size: 20px;
        line-height: 1.25;
        margin-bottom: 4px;
    }

    .crs-hero-left p {
        font-size: 12.5px;
        line-height: 1.4;
    }

    .crs-auto-btn {
        width: 100%;
        justify-content: center;
        padding: 12px 16px;
        font-size: 13.5px;
        border-radius: 12px;
    }

    .crs-section-head {
        margin-top: 22px;
        margin-bottom: 12px;
    }

    .crs-section-title {
        font-size: 16.5px;
    }

    .crs-section-badge {
        font-size: 11px;
        padding: 3px 9px;
    }

    .crs-grid {
        grid-template-columns: 1fr;
        gap: 14px;
        margin-bottom: 22px;
    }

    .crs-card {
        padding: 16px 14px;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
    }

    .crs-title {
        font-size: 15.5px;
        line-height: 1.35;
    }

    .crs-dept-name {
        font-size: 12px;
        margin-bottom: 12px;
    }

    .crs-faculty-box {
        padding: 8px 10px;
        border-radius: 12px;
    }

    .crs-faculty-avatar {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }

    .crs-faculty-name {
        font-size: 12.5px;
    }

    .crs-faculty-desig {
        font-size: 11px;
    }

    .crs-card-bottom {
        padding-top: 12px;
        gap: 10px;
    }

    .crs-card-stats {
        padding: 6px 10px;
        font-size: 11.5px;
    }

    .crs-enroll-btn, .crs-enrolled-badge, .crs-restricted-btn {
        padding: 11px 14px;
        font-size: 13px;
        border-radius: 12px;
    }
}
</style>

<div class="crs-page-wrap">
    <!-- Hero Banner with 1-Click Enrollment -->
    <div class="crs-hero">
        <div class="crs-hero-left">
            <div class="crs-hero-kicker">⚡ Academic Hub</div>
            <h2>Course Catalogue & Auto-Enrollment</h2>
            <p>Explore your departmental courses, enroll with a single tap, and connect directly with your course professors.</p>
        </div>
        <div class="crs-hero-right">
            <form action="courses.php" method="POST" style="margin: 0;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="auto_enroll_dept">
                <button type="submit" class="crs-auto-btn">
                    <span>⚡ Auto-Enroll All Dept Courses</span>
                </button>
            </form>
        </div>
    </div>

    <!-- SECTION 1: MY ENROLLED COURSES -->
    <div class="crs-section-head">
        <h3 class="crs-section-title">
            <span>📖 My Enrolled Courses</span>
        </h3>
        <span class="crs-section-badge"><?php echo count($enrolledCourses); ?> Active</span>
    </div>

    <div class="crs-grid">
        <?php if (empty($enrolledCourses)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 36px 20px; background: #ffffff; border-radius: 18px; border: 1px dashed #cbd5e1; color: #64748b;">
                <div style="font-size: 34px; margin-bottom: 8px;">📚</div>
                <h4 style="margin: 0 0 4px 0; color: #0f172a; font-size: 16px;">No Enrolled Courses Yet</h4>
                <p style="margin: 0; font-size: 13px;">Browse the catalogue below and tap "Enroll in Course" to begin your academic semester.</p>
            </div>
        <?php else: ?>
            <?php foreach ($enrolledCourses as $c): 
                $facName = !empty($c['faculty_fn']) ? ('Prof. ' . $c['faculty_fn'] . ' ' . $c['faculty_ln']) : 'Dealing Faculty Assigned';
                $facInitials = !empty($c['faculty_fn']) ? strtoupper(substr($c['faculty_fn'], 0, 1) . substr($c['faculty_ln'], 0, 1)) : 'FA';
            ?>
                <div class="crs-card">
                    <div class="crs-card-top">
                        <div class="crs-card-header">
                            <span class="crs-code-tag"><?php echo htmlspecialchars($c['code']); ?></span>
                            <span class="crs-credits-pill"><?php echo (int)$c['credits']; ?> Credits</span>
                        </div>

                        <div class="crs-title"><?php echo htmlspecialchars($c['name']); ?></div>
                        <div class="crs-dept-name">🏢 <?php echo htmlspecialchars($c['dept_name'] ?? 'Department'); ?></div>

                        <div class="crs-faculty-box">
                            <div class="crs-faculty-avatar"><?php echo $facInitials; ?></div>
                            <div class="crs-faculty-info">
                                <div class="crs-faculty-name"><?php echo htmlspecialchars($facName); ?></div>
                                <div class="crs-faculty-desig"><?php echo htmlspecialchars($c['designation'] ?? 'Instructor'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="crs-card-bottom">
                        <div class="crs-card-stats">
                            <span class="crs-card-stat-item">📄 Materials: <strong><?php echo (int)$c['materials_count']; ?></strong></span>
                            <span class="crs-card-stat-item">📝 Tasks: <strong><?php echo (int)$c['assignments_count']; ?></strong></span>
                        </div>
                        <div class="crs-enrolled-badge">✓ Currently Enrolled</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SECTION 2: ALL AVAILABLE COURSE CATALOGUE -->
    <div class="crs-section-head">
        <h3 class="crs-section-title">
            <span>🌐 Campus Course Catalogue</span>
        </h3>
        <span class="crs-section-badge" style="background: #f3e8ff; color: #7e22ce;"><?php echo count($allCatalogueCourses); ?> Available</span>
    </div>

    <div class="crs-grid">
        <?php foreach ($allCatalogueCourses as $cat): 
            $isEnrolled = in_array($cat['id'], $enrolledCourseIds);
            $facName = !empty($cat['faculty_fn']) ? ('Prof. ' . $cat['faculty_fn'] . ' ' . $cat['faculty_ln']) : 'Dealing Faculty Assigned';
            $facInitials = !empty($cat['faculty_fn']) ? strtoupper(substr($cat['faculty_fn'], 0, 1) . substr($cat['faculty_ln'], 0, 1)) : 'FA';
            $isDeptMatch = ($cat['department_id'] == $deptId);
        ?>
            <div class="crs-card" style="<?php echo $isDeptMatch ? 'border-color: #c7d2fe;' : ''; ?>">
                <div class="crs-card-top">
                    <div class="crs-card-header">
                        <span class="crs-code-tag"><?php echo htmlspecialchars($cat['code']); ?></span>
                        <?php if ($isDeptMatch): ?>
                            <span class="crs-dept-tag">Your Dept</span>
                        <?php else: ?>
                            <span class="crs-credits-pill"><?php echo (int)$cat['credits']; ?> Credits</span>
                        <?php endif; ?>
                    </div>

                    <div class="crs-title"><?php echo htmlspecialchars($cat['name']); ?></div>
                    <div class="crs-dept-name">🏢 <?php echo htmlspecialchars($cat['dept_name'] ?? 'General Department'); ?></div>

                    <div class="crs-faculty-box">
                        <div class="crs-faculty-avatar"><?php echo $facInitials; ?></div>
                        <div class="crs-faculty-info">
                            <div class="crs-faculty-name"><?php echo htmlspecialchars($facName); ?></div>
                            <div class="crs-faculty-desig"><?php echo htmlspecialchars($cat['designation'] ?? 'Course Faculty'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="crs-card-bottom">
                    <?php if ($isEnrolled): ?>
                        <div class="crs-enrolled-badge">✓ Enrolled</div>
                    <?php elseif (!$isDeptMatch): ?>
                        <button type="button" class="crs-restricted-btn" disabled>
                            <span>🔒 Restricted to <?php echo htmlspecialchars($cat['dept_code'] ?? 'Dept'); ?></span>
                        </button>
                    <?php else: ?>
                        <form action="courses.php" method="POST" style="margin: 0; width: 100%;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="enroll_single">
                            <input type="hidden" name="course_id" value="<?php echo $cat['id']; ?>">
                            <button type="submit" class="crs-enroll-btn">
                                <span>✨ Enroll in Course</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
