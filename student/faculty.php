<?php
// student/faculty.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();

// Fetch all faculty members with user email and department name
$stmt = $pdo->prepare("
    SELECT f.*, u.email, d.name as dept_name, d.code as dept_code
    FROM faculty_profiles f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN departments d ON f.department_id = d.id
    ORDER BY f.first_name ASC
");
$stmt->execute();
$facultyMembers = $stmt->fetchAll();

// Count stats & get unique departments
$totalFaculty = count($facultyMembers);
$departments = [];
foreach ($facultyMembers as $f) {
    if (!empty($f['dept_name']) && !in_array($f['dept_name'], $departments)) {
        $departments[] = $f['dept_name'];
    }
}
$deptCount = count($departments);

$pageTitle = 'Faculty Directory & Mentors';
$pageHeading = 'Faculty Directory';
$activeNav = 'faculty';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fac-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%);
    border-radius: 20px;
    padding: 32px 36px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}
.fac-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.18) 0%, rgba(99, 102, 241, 0) 70%);
    border-radius: 50%;
}
.fac-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 24px;
}
.fac-stat-card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.fac-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: rgba(99, 102, 241, 0.3);
}
.fac-stat-val {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.1;
}
.fac-stat-lbl {
    font-size: 12px;
    opacity: 0.85;
    font-weight: 500;
    margin-top: 2px;
}

.fac-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.fac-search-box {
    position: relative;
    flex: 1;
    min-width: 280px;
}
.fac-search-box input {
    width: 100%;
    padding: 12px 18px 12px 42px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
    background: #ffffff;
}
.fac-search-box input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.fac-search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 16px;
}
.fac-dept-select {
    padding: 12px 18px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
    outline: none;
    background: #ffffff;
    cursor: pointer;
    min-width: 200px;
}

.fac-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
}
.fac-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.fac-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    border-color: #c7d2fe;
}
.fac-avatar-wrap {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}
.fac-avatar {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.25);
    flex-shrink: 0;
}
.fac-name {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
    margin-bottom: 3px;
}
.fac-desig {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.fac-dept-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    margin-bottom: 14px;
}

.fac-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
    color: #475569;
    margin-bottom: 20px;
    background: #f8fafc;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
}
.fac-detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.fac-detail-icon {
    font-size: 14px;
    width: 18px;
    text-align: center;
}

.fac-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 11px 16px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
}
.fac-action-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
    color: #ffffff;
}

@media (max-width: 768px) {
    .fac-hero {
        padding: 24px 20px;
        border-radius: 20px;
        margin-bottom: 20px;
    }
    .fac-hero h2 {
        font-size: 22px !important;
    }
    .fac-stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-top: 18px;
    }
    .fac-controls {
        flex-direction: column;
        align-items: stretch;
    }
    .fac-search-box {
        min-width: 100%;
    }
    .fac-dept-select {
        width: 100%;
    }
    .fac-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .fac-card {
        padding: 20px 16px;
        border-radius: 18px;
    }
}
</style>

<div class="fac-hero">
    <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.9; margin-bottom: 6px;">🎓 Academic Faculty & Mentors</div>
    <h2 style="font-size: 26px; font-weight: 800; margin: 0 0 8px 0; letter-spacing: -0.5px;">Faculty Directory</h2>
    <p style="font-size: 14px; opacity: 0.88; max-width: 650px; line-height: 1.5; margin: 0;">Connect with department professors, explore research specializations, locate office rooms, and book 1-on-1 consultations.</p>

    <div class="fac-stats-grid">
        <div class="fac-stat-card">
            <div class="fac-stat-icon">👨‍🏫</div>
            <div>
                <div class="fac-stat-val"><?php echo $totalFaculty; ?></div>
                <div class="fac-stat-lbl">Active Professors</div>
            </div>
        </div>
        <div class="fac-stat-card">
            <div class="fac-stat-icon" style="background: rgba(16, 185, 129, 0.25);">🏢</div>
            <div>
                <div class="fac-stat-val"><?php echo $deptCount; ?></div>
                <div class="fac-stat-lbl">Academic Departments</div>
            </div>
        </div>
        <div class="fac-stat-card">
            <div class="fac-stat-icon" style="background: rgba(245, 158, 11, 0.25);">🗓️</div>
            <div>
                <div class="fac-stat-val">Open</div>
                <div class="fac-stat-lbl">Consultation Hours</div>
            </div>
        </div>
    </div>
</div>

<div class="fac-toolbar">
    <div class="fac-search-box">
        <span class="fac-search-icon">🔍</span>
        <input type="text" id="facSearchInput" onkeyup="filterFaculty()" placeholder="Search professor name, designation or research area...">
    </div>

    <select class="fac-dept-select" id="facDeptSelect" onchange="filterFaculty()">
        <option value="all">All Departments (<?php echo $deptCount; ?>)</option>
        <?php foreach ($departments as $dept): ?>
            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="fac-grid" id="facGrid">
    <?php if (empty($facultyMembers)): ?>
        <div class="card-box" style="grid-column: 1 / -1; text-align: center; padding: 48px; color: #94a3b8;">
            <div style="font-size: 40px; margin-bottom: 12px;">👨‍🏫</div>
            <h4 style="margin: 0 0 6px 0; color: #475569; font-size: 18px;">No Faculty Found</h4>
            <p style="margin: 0; font-size: 14px;">There are currently no faculty members registered in the directory.</p>
        </div>
    <?php else: ?>
        <?php foreach ($facultyMembers as $f): 
            $fullName = 'Prof. ' . $f['first_name'] . ' ' . $f['last_name'];
            $initials = strtoupper(substr($f['first_name'], 0, 1) . substr($f['last_name'], 0, 1));
            $deptName = $f['dept_name'] ?? 'Department of Technology';
            $specialization = $f['specialization'] ?? 'Academic Research & Pedagogy';
            $searchData = strtolower($fullName . ' ' . $f['designation'] . ' ' . $deptName . ' ' . $specialization . ' ' . $f['email']);
        ?>
            <div class="fac-card" data-dept="<?php echo htmlspecialchars($deptName); ?>" data-search="<?php echo htmlspecialchars($searchData); ?>">
                <div>
                    <div class="fac-avatar-wrap">
                        <div class="fac-avatar"><?php echo $initials; ?></div>
                        <div>
                            <div class="fac-name"><?php echo htmlspecialchars($fullName); ?></div>
                            <div class="fac-desig"><?php echo htmlspecialchars($f['designation']); ?></div>
                        </div>
                    </div>

                    <div class="fac-dept-badge">
                        🏢 <?php echo htmlspecialchars($deptName); ?>
                    </div>

                    <div class="fac-details">
                        <div class="fac-detail-item">
                            <span class="fac-detail-icon">🔬</span>
                            <span><strong>Focus:</strong> <?php echo htmlspecialchars($specialization); ?></span>
                        </div>
                        <div class="fac-detail-item">
                            <span class="fac-detail-icon">📍</span>
                            <span><strong>Office:</strong> Room <?php echo htmlspecialchars($f['office_room']); ?></span>
                        </div>
                        <div class="fac-detail-item">
                            <span class="fac-detail-icon">✉️</span>
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px;" title="<?php echo htmlspecialchars($f['email']); ?>">
                                <?php echo htmlspecialchars($f['email']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <a href="appointments.php?faculty_id=<?php echo $f['id']; ?>" class="fac-action-btn">
                    <span>Book Consultation</span>
                    <span>→</span>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function filterFaculty() {
    const searchVal = document.getElementById('facSearchInput').value.toLowerCase().trim();
    const selectedDept = document.getElementById('facDeptSelect').value;
    const cards = document.querySelectorAll('.fac-card');

    cards.forEach(card => {
        const cardSearch = card.getAttribute('data-search');
        const cardDept = card.getAttribute('data-dept');

        const matchesSearch = searchVal === '' || cardSearch.includes(searchVal);
        const matchesDept = selectedDept === 'all' || cardDept === selectedDept;

        if (matchesSearch && matchesDept) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

