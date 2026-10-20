<?php
// student/register.php
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

    $studentIdNum = trim($_POST['student_id_number'] ?? '');
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $program = trim($_POST['program'] ?? '');
    $yearOfStudy = (int)($_POST['year_of_study'] ?? 1);
    $semester = (int)($_POST['semester'] ?? 1);
    $section = trim($_POST['section'] ?? 'A');
    $admissionYear = (int)($_POST['admission_year'] ?? date('Y'));

    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($studentIdNum) || empty($program)) {
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
                // Check existing student ID
                $chk2 = $pdo->prepare("SELECT id FROM student_profiles WHERE student_id_number = ?");
                $chk2->execute([$studentIdNum]);
                if ($chk2->fetch()) {
                    $errorMsg = 'Student ID / Roll Number is already registered.';
                    setToast('error', $errorMsg);
                } else {
                    // Create User & Profile transaction
                    $pdo->beginTransaction();

                    $username = strtolower($firstName . '_' . substr($studentIdNum, -4));
                    $pwdHash = password_hash($password, PASSWORD_BCRYPT);

                    $userStmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active') RETURNING id");
                    $userStmt->execute([$email, $username, $pwdHash]);
                    $userId = $userStmt->fetchColumn();

                    $profileStmt = $pdo->prepare("
                        INSERT INTO student_profiles (
                            user_id, student_id_number, first_name, last_name, gender, dob, phone,
                            department_id, program, year_of_study, semester, section, admission_year,
                            address, city, state, postal_code
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $profileStmt->execute([
                        $userId, $studentIdNum, $firstName, $lastName, $gender, $dob, $phone,
                        $departmentId, $program, $yearOfStudy, $semester, $section, $admissionYear,
                        $address, $city, $state, $postalCode
                    ]);

                    $pdo->commit();

                    logActivity($userId, 'Student account registered');
                    $registeredSuccess = true;
                    setToast('success', 'Registration Successful! Your student account has been created successfully. Redirecting to login...');
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Student Registration Error: " . $e->getMessage());
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
    <title>Student Registration | AcadNexus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-navy: #0f172a;
            --accent-blue: #2563eb;
            --accent-gradient: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
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
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
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
            color: var(--accent-blue);
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

        .full-width { grid-column: span 2; }

        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            font-size: 14px;
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--accent-blue);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
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
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25);
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.35);
        }

        .back-login {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-muted);
        }
        .back-login a { color: var(--accent-blue); text-decoration: none; font-weight: 700; }

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
            .form-input, .form-select, .form-textarea {
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
            <div class="brand-logo">
                <div class="brand-icon">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path></svg>
                </div>
                AcadNexus
            </div>
            <div>
                <span style="background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;">STUDENT REGISTRATION</span>
            </div>
        </div>

        <div class="reg-body">
            <form action="register.php" method="POST" id="studentRegForm">
                <?php echo csrfField(); ?>

                <!-- 1. Personal Info -->
                <div class="section-title">1. Personal Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="e.g. Alex">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required placeholder="e.g. Rivera">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-input" required placeholder="student@university.edu">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-input" placeholder="+1 555-0199">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
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

                <!-- 2. Academic Info -->
                <div class="section-title">2. Academic Details</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Student ID / Roll Number *</label>
                        <input type="text" name="student_id_number" class="form-input" required placeholder="STU-2026-099">
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
                        <label class="form-label">Program / Course *</label>
                        <input type="text" name="program" class="form-input" required placeholder="B.Tech Computer Science">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year of Study</label>
                        <select name="year_of_study" class="form-select">
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3" selected>Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <input type="number" name="semester" min="1" max="10" value="6" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Section & Admission Year</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="section" value="A" class="form-input" placeholder="Section">
                            <input type="number" name="admission_year" value="2024" class="form-input" placeholder="Year">
                        </div>
                    </div>
                </div>

                <!-- 3. Address Info -->
                <div class="section-title">3. Address & Location</div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Street Address</label>
                        <input type="text" name="address" class="form-input" placeholder="123 Academic Way">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input" placeholder="Boston">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State / Postal Code</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="state" class="form-input" placeholder="MA">
                            <input type="text" name="postal_code" class="form-input" placeholder="02115">
                        </div>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="terms" required style="accent-color: var(--accent-blue);">
                    <label for="terms">I agree to the Terms & Conditions and Academic Honor Code</label>
                </div>

                <button type="submit" class="btn-submit">CREATE STUDENT ACCOUNT</button>

                <div class="back-login">
                    Already have an account? <a href="../login.php">Sign In Here</a>
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
