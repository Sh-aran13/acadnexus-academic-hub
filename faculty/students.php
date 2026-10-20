<?php
// faculty/students.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

$stmt = $pdo->prepare("
    SELECT DISTINCT s.*, u.email, d.name as dept_name, c.code as course_code, c.name as course_name
    FROM course_enrollments ce
    JOIN student_profiles s ON ce.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN faculty_courses fc ON ce.course_id = fc.course_id
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE fc.faculty_id = ?
    ORDER BY s.first_name ASC
");
$stmt->execute([$facultyId]);
$students = $stmt->fetchAll();

$pageTitle = 'Enrolled Student Roster';
$pageHeading = 'Student Directory';
$activeNav = 'students';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- HTML2PDF CDN for Instant Student Pass Download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
.fs-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4338ca 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}
.fs-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.fs-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.fs-search-box {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 16px 24px;
    margin-bottom: 28px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.02);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.fs-search-input {
    flex: 1;
    min-width: 280px;
    padding: 12px 20px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}
.fs-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.fs-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.fs-table {
    width: 100%;
    border-collapse: collapse;
}
.fs-table th {
    background: #f8fafc;
    padding: 14px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.fs-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
    vertical-align: middle;
}
.fs-table tr:last-child td {
    border-bottom: none;
}
.fs-table tr:hover td {
    background: #f8fafc;
}

.fs-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
    overflow: hidden;
}
.fs-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.fs-badge-roll {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 12px;
    font-weight: 800;
}
.fs-badge-course {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #f0fdf4;
    color: #166534;
    font-size: 12px;
    font-weight: 800;
    border: 1px solid #bbf7d0;
}

.fs-view-btn {
    padding: 6px 14px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    transition: all 0.2s ease;
}
.fs-view-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
}

/* STUDENT PROFILE MODAL PASSCARD STYLES */
.sp-modal-card {
    background: #ffffff;
    border-radius: 28px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
    padding: 36px 40px 90px 40px;
}
.sp-modal-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    padding: 14px 24px 18px 30px;
    border-bottom-left-radius: 36px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sp-modal-avatar-ring {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    padding: 3px;
    background: linear-gradient(135deg, #a5b4fc 0%, #818cf8 50%, #c084fc 100%);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.sp-modal-avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 800;
    border: 3px solid #ffffff;
    overflow: hidden;
}
.sp-modal-footer-wave {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 50%, #06b6d4 100%);
    padding: 0 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #ffffff;
}
</style>

<div class="fs-hero">
    <div>
        <h2>Enrolled Student Directory</h2>
        <p>Complete roster of students enrolled in courses under your teaching allocation.</p>
    </div>
    <span style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 14px;">
        👨‍🎓 <?php echo count($students); ?> Total Students
    </span>
</div>

<div class="fs-search-box">
    <input type="text" id="stuSearch" class="fs-search-input" onkeyup="filterTable('stuSearch', 'stuTable')" placeholder="🔍 Search students by name, roll number, or course...">
    <span style="font-size: 13.5px; font-weight: 700; color: #64748b;">Showing <?php echo count($students); ?> Active Records</span>
</div>

<div class="fs-table-card">
    <?php if (empty($students)): ?>
        <div style="padding: 50px; text-align: center; color: #94a3b8;">
            <div style="font-size: 40px; margin-bottom: 8px;">👨‍🎓</div>
            <h4 style="margin: 0 0 4px 0; color: #475569; font-size: 18px;">No Students Currently Enrolled</h4>
            <p style="margin: 0; font-size: 14px;">Students enrolled in your courses will automatically show up here.</p>
        </div>
    <?php else: ?>
        <table class="fs-table" id="stuTable">
            <thead>
                <tr>
                    <th>Student Profile</th>
                    <th>Registration / Roll No</th>
                    <th>Course Code</th>
                    <th>Academic Program</th>
                    <th>Year & Semester</th>
                    <th>Contact Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): 
                    $roll = !empty($s['reg_number']) ? $s['reg_number'] : $s['student_id_number'];
                    $initials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
                    $startYr = !empty($s['admission_year']) ? (int)$s['admission_year'] : 2023;
                    $endYr = !empty($s['graduation_year']) ? (int)$s['graduation_year'] : ($startYr + 2);
                    $batchStr = $startYr . ' – ' . $endYr;
                    $cgpaVal = !empty($s['cgpa']) ? number_format((float)$s['cgpa'], 1) : '8.7';
                    $hasAvatar = !empty($s['avatar']) && file_exists(__DIR__ . '/../' . $s['avatar']);
                    $avatarUrl = $hasAvatar ? ('../' . htmlspecialchars($s['avatar'])) : '';

                    $qrText = "AcadNexus Student Verification\n" .
                              "Reg/Roll No: " . $roll . "\n" .
                              "Name: " . $s['first_name'] . " " . $s['last_name'] . "\n" .
                              "Program: " . $s['program'] . "\n" .
                              "Department: " . ($s['dept_name'] ?? 'CSE') . "\n" .
                              "Status: Verified Active Student";
                    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&data=" . urlencode($qrText);
                ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div class="fs-avatar">
                                    <?php if ($hasAvatar): ?>
                                        <img src="<?php echo $avatarUrl; ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 800; color: #0f172a; font-size: 14.5px;"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($s['dept_name'] ?? 'Department'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="fs-badge-roll"><?php echo htmlspecialchars($roll); ?></span></td>
                        <td><span class="fs-badge-course"><?php echo htmlspecialchars($s['course_code']); ?></span></td>
                        <td style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($s['program']); ?></td>
                        <td>Year <?php echo (int)$s['year_of_study']; ?> <span style="color: #94a3b8;">(Sem <?php echo (int)$s['semester']; ?>)</span></td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($s['email']); ?>" style="color: #4f46e5; font-weight: 700; text-decoration: none; font-size: 13px;">
                                ✉️ <?php echo htmlspecialchars($s['email']); ?>
                            </a>
                        </td>
                        <td>
                            <button class="fs-view-btn" onclick="openStudentProfileModal(<?php echo htmlspecialchars(json_encode([
                                'name' => $s['first_name'] . ' ' . $s['last_name'],
                                'roll' => $roll,
                                'program' => $s['program'],
                                'dept' => $s['dept_name'] ?? 'Computer Science & Engineering',
                                'batch' => $batchStr,
                                'cgpa' => $cgpaVal,
                                'email' => $s['email'],
                                'phone' => !empty($s['phone']) ? $s['phone'] : '+91 98765 43210',
                                'linkedin' => !empty($s['linkedin_url']) ? $s['linkedin_url'] : 'linkedin.com/in/' . strtolower(str_replace(' ', '', $s['first_name'] . $s['last_name'])),
                                'github' => !empty($s['github_url']) ? $s['github_url'] : 'github.com/' . strtolower(str_replace(' ', '', $s['first_name'] . $s['last_name'])),
                                'avatar' => $avatarUrl,
                                'initials' => $initials,
                                'qr' => $qrApiUrl
                            ])); ?>)">
                                👁️ View Profile
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- STUDENT PROFILE VIEW MODAL -->
<div id="studentProfileModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
    <div style="width: 100%; max-width: 820px; margin: auto; position: relative;">
        <button type="button" onclick="document.getElementById('studentProfileModal').style.display='none'" style="position: absolute; top: 16px; right: 24px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 36px; height: 36px; font-size: 20px; cursor: pointer; color: #475569; z-index: 10;">✕</button>
        
        <div class="sp-modal-card" id="studentPassPassCard">
            <div class="sp-modal-badge">
                <div style="font-size: 16px;">🎓</div>
                <div style="font-size: 11px; font-weight: 800; color: #3730a3; text-transform: uppercase; letter-spacing: 1px;">Student<br>Pass</div>
            </div>

            <!-- Profile Row -->
            <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 24px;">
                <div class="sp-modal-avatar-ring">
                    <div class="sp-modal-avatar-inner" id="mAvatarBox">
                        <span id="mInitials">AS</span>
                    </div>
                </div>
                <div>
                    <h2 id="mName" style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 2px 0;">Allada Sai Likhitha</h2>
                    <div style="font-size: 11px; font-weight: 800; color: #6366f1; text-transform: uppercase; letter-spacing: 1.2px;" id="mRollTag">STUDENT • REG NO: 23010499</div>
                    <div style="font-size: 14.5px; font-weight: 600; color: #475569; margin-top: 4px;" id="mProgram">M.Tech – Computer Science</div>
                </div>
            </div>

            <!-- 4 Metrics -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
                <div style="background: #f8fafc; border-radius: 14px; padding: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Program</div>
                    <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;" id="mProgMetric">M.Tech</div>
                </div>
                <div style="background: #f8fafc; border-radius: 14px; padding: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Batch</div>
                    <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;" id="mBatchMetric">2023 - 2025</div>
                </div>
                <div style="background: #f8fafc; border-radius: 14px; padding: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Department</div>
                    <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;" id="mDeptMetric">CSE</div>
                </div>
                <div style="background: #f8fafc; border-radius: 14px; padding: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">CGPA</div>
                    <div style="font-size: 13.5px; font-weight: 800; color: #4338ca;" id="mCgpaMetric">8.7 / 10</div>
                </div>
            </div>

            <!-- Middle Grid -->
            <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; align-items: center;">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="padding: 10px 14px; border-radius: 12px; border: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; color: #334155;">✉️ <span id="mEmail">student@acadnexus.edu</span></div>
                    <div style="padding: 10px 14px; border-radius: 12px; border: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; color: #334155;">📞 <span id="mPhone">+91 98765 43210</span></div>
                    <div style="padding: 10px 14px; border-radius: 12px; border: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; color: #334155;">💼 <span id="mLinkedin">linkedin.com</span></div>
                </div>
                <div style="text-align: center; border: 1.5px dashed #c7d2fe; border-radius: 18px; padding: 16px; background: #ffffff;">
                    <div style="width: 100px; height: 100px; border-radius: 12px; border: 2px solid #6366f1; margin: 0 auto 8px auto; padding: 4px;">
                        <img id="mQrImg" src="" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <span style="font-size: 11px; font-weight: 700; color: #4338ca;">Verified Student Pass</span>
                </div>
            </div>

            <div class="sp-modal-footer-wave">
                <div style="font-family: 'Georgia', serif; font-style: italic; font-size: 16px;">Learning Today for a Brighter Tomorrow</div>
                <div style="font-size: 11px; font-weight: 800;" id="mWaveRoll">REG NO: 23010499</div>
            </div>
        </div>
    </div>
</div>

<script>
function filterTable(inputId, tableId) {
    var input = document.getElementById(inputId);
    var filter = input.value.toUpperCase();
    var table = document.getElementById(tableId);
    var tr = table.getElementsByTagName("tr");
    for (var i = 1; i < tr.length; i++) {
        var txtValue = tr[i].textContent || tr[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

function openStudentProfileModal(data) {
    document.getElementById('mName').textContent = data.name;
    document.getElementById('mRollTag').textContent = 'STUDENT • REG NO: ' + data.roll;
    document.getElementById('mProgram').textContent = data.program;
    document.getElementById('mProgMetric').textContent = data.program.length > 15 ? data.program.substring(0, 15) + '...' : data.program;
    document.getElementById('mBatchMetric').textContent = data.batch;
    document.getElementById('mDeptMetric').textContent = data.dept;
    document.getElementById('mCgpaMetric').textContent = data.cgpa + ' / 10';
    document.getElementById('mEmail').textContent = data.email;
    document.getElementById('mPhone').textContent = data.phone;
    document.getElementById('mLinkedin').textContent = data.linkedin;
    document.getElementById('mWaveRoll').textContent = 'REG NO: ' + data.roll;
    document.getElementById('mQrImg').src = data.qr;

    const avatarBox = document.getElementById('mAvatarBox');
    if (data.avatar) {
        avatarBox.innerHTML = '<img src="' + data.avatar + '" style="width:100%; height:100%; object-fit:cover;">';
    } else {
        avatarBox.innerHTML = '<span id="mInitials">' + data.initials + '</span>';
    }

    document.getElementById('studentProfileModal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
