<?php
// faculty/register.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

redirectIfAuthenticated();

$pdo = getDBConnection();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

$registeredSuccess = false;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = $_POST['dob'] ?? null;
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $facultyIdNum = trim($_POST['faculty_id_number'] ?? '');
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $designation = trim($_POST['designation'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $joiningDate = $_POST['joining_date'] ?? null;
    $experienceYears = (int)($_POST['experience_years'] ?? 0);
    $officeRoom = trim($_POST['office_room'] ?? '');

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($facultyIdNum) || empty($designation)) {
        $errorMsg = 'Please fill in all required fields.';
        setToast('error', $errorMsg);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please provide a valid email address.';
        setToast('error', $errorMsg);
    } elseif ($password !== $confirmPassword) {
        $errorMsg = 'Passwords do not match.';
        setToast('error', $errorMsg);
    } elseif (strlen($password) < 8) {
        $errorMsg = 'Password must be at least 8 characters long.';
        setToast('error', $errorMsg);
    } else {
        try {
            // Check existing email
            $chk1 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chk1->execute([$email]);
            if ($chk1->fetch()) {
                $errorMsg = 'Email is already registered.';
                setToast('error', $errorMsg);
            } else {
                // Check existing faculty ID
                $chk2 = $pdo->prepare("SELECT id FROM faculty_profiles WHERE faculty_id_number = ?");
                $chk2->execute([$facultyIdNum]);
                if ($chk2->fetch()) {
                    $errorMsg = 'Faculty ID is already registered.';
                    setToast('error', $errorMsg);
                } else {
                    $pdo->beginTransaction();

                    $username = strtolower('prof_' . $firstName . '_' . substr($facultyIdNum, -4));
                    $pwdHash = password_hash($password, PASSWORD_BCRYPT);

                    $userStmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (?, ?, ?, 'faculty', 'active') RETURNING id");
                    $userStmt->execute([$email, $username, $pwdHash]);
                    $userId = $userStmt->fetchColumn();

                    $profileStmt = $pdo->prepare("
                        INSERT INTO faculty_profiles (
                            user_id, faculty_id_number, first_name, last_name, gender, dob, phone,
                            department_id, designation, qualification, specialization, joining_date,
                            experience_years, office_room
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $profileStmt->execute([
                        $userId, $facultyIdNum, $firstName, $lastName, $gender, $dob, $phone,
                        $departmentId, $designation, $qualification, $specialization, $joiningDate,
                        $experienceYears, $officeRoom
                    ]);

                    $pdo->commit();

                    logActivity($userId, 'Faculty account registered');
                    $registeredSuccess = true;
                    setToast('success', 'Registration Successful! Your faculty account has been created successfully. Redirecting to login...');
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Faculty Registration Error: " . $e->getMessage());
            $errorMsg = 'Database error during registration. Please try again.';
            setToast('error', $errorMsg);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Registration | AcadNexus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-navy: #0f172a;
            --accent-purple: #7c3aed;
            --accent-gradient: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
            --bg-light: #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .reg-container {
            width: 100%;
            max-width: 850px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .reg-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #311b92 100%);
            color: #ffffff;
            padding: 32px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 800;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--accent-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reg-body { padding: 40px; }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--accent-purple);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-input, .form-select {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            font-size: 14px;
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--accent-purple);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.25);
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(124, 58, 237, 0.35);
        }

        .back-home-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .back-home-btn:hover {
            background: rgba(255, 255, 255, 0.22);
            transform: translateX(-2px);
            color: #ffffff;
        }

        .back-login {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        .back-login a { color: var(--accent-purple); text-decoration: none; font-weight: 700; }
        .back-index-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted) !important;
            font-size: 13px;
            font-weight: 600 !important;
            transition: color 0.2s ease;
        }
        .back-index-link:hover {
            color: var(--accent-purple) !important;
        }

        @media (max-width: 768px) {
            body {
                padding: 16px 12px;
                align-items: flex-start;
            }
            .reg-container {
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            }
            .reg-header {
                padding: 24px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .reg-body {
                padding: 24px 18px;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-bottom: 24px;
            }
            .full-width {
                grid-column: span 1;
            }
            .form-input, .form-select {
                padding: 13px 14px;
                font-size: 15px;
            }
            .btn-submit {
                padding: 15px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/toast.php'; ?>

    <div class="reg-container">
        <div class="reg-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <a href="../index.php" class="back-home-btn" title="Back to AcadNexus Home">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Back to Home</span>
                </a>
                <div class="brand-logo">
                    <div class="brand-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path></svg>
                    </div>
                    AcadNexus
                </div>
            </div>
            <div>
                <span style="background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px;">FACULTY REGISTRATION</span>
            </div>
        </div>

        <div class="reg-body">
            <form action="register.php" method="POST" id="facultyRegForm">
                <?php echo csrfField(); ?>

                <!-- 1. Personal Information -->
                <div class="section-title">1. Personal Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="e.g. Sarah">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required placeholder="e.g. Jenkins">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-input" required placeholder="faculty@acadnexus.test">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-input" placeholder="+1 555-0102">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required placeholder="••••••••">
                    </div>
                </div>

                <!-- 2. Professional & Academic Info -->
                <div class="section-title">2. Professional Details</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Faculty ID *</label>
                        <input type="text" name="faculty_id_number" class="form-input" required placeholder="e.g. FAC8492">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select name="department_id" class="form-select" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?> (<?php echo $d['code']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Designation *</label>
                        <input type="text" name="designation" class="form-input" required placeholder="Associate Professor">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Highest Qualification</label>
                        <input type="text" name="qualification" class="form-input" placeholder="Ph.D in Software Engineering">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Specialization Area</label>
                        <input type="text" name="specialization" class="form-input" placeholder="Web Tech & Distributed Systems">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Joining Date & Experience</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="date" name="joining_date" class="form-input">
                            <input type="number" name="experience_years" value="5" class="form-input" placeholder="Years">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Office / Room Number</label>
                        <input type="text" name="office_room" class="form-input" placeholder="Tech Tower 408">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="terms" required style="accent-color: var(--accent-purple);">
                    <label for="terms">I agree to the Terms & Conditions and Institutional Faculty Code</label>
                </div>

                <button type="submit" class="btn-submit">CREATE FACULTY ACCOUNT</button>

                <div class="back-login">
                    <div>Already have an account? <a href="../login.php">Sign In Here</a></div>
                    
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php if ($registeredSuccess): ?>
        setTimeout(function() {
            window.location.href = "../login.php";
        }, 3000); // Redirect after EXACTLY 3 SECONDS
        <?php endif; ?>
    </script>
</body>
</html>
