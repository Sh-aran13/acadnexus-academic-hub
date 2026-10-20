-- ====================================================
-- ACADNEXUS - DATABASE SCHEMA & SEED DATA (POSTGRESQL)
-- ====================================================

-- Drop tables if exists in cascade order
DROP TABLE IF EXISTS activity_logs CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS project_milestones CASCADE;
DROP TABLE IF EXISTS projects CASCADE;
DROP TABLE IF EXISTS appointments CASCADE;
DROP TABLE IF EXISTS announcements CASCADE;
DROP TABLE IF EXISTS marks CASCADE;
DROP TABLE IF EXISTS attendance CASCADE;
DROP TABLE IF EXISTS materials CASCADE;
DROP TABLE IF EXISTS assignment_submissions CASCADE;
DROP TABLE IF EXISTS assignments CASCADE;
DROP TABLE IF EXISTS course_enrollments CASCADE;
DROP TABLE IF EXISTS faculty_courses CASCADE;
DROP TABLE IF EXISTS courses CASCADE;
DROP TABLE IF EXISTS faculty_profiles CASCADE;
DROP TABLE IF EXISTS student_profiles CASCADE;
DROP TABLE IF EXISTS departments CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- 1. Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('student', 'faculty', 'admin')),
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Departments Table
CREATE TABLE departments (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Student Profiles Table
CREATE TABLE student_profiles (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    student_id_number VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender VARCHAR(20),
    dob DATE,
    phone VARCHAR(30),
    department_id INT REFERENCES departments(id) ON DELETE SET NULL,
    program VARCHAR(100) NOT NULL,
    year_of_study INT NOT NULL CHECK (year_of_study BETWEEN 1 AND 5),
    semester INT NOT NULL CHECK (semester BETWEEN 1 AND 10),
    section VARCHAR(10) DEFAULT 'A',
    admission_year INT NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    avatar VARCHAR(255) DEFAULT NULL,
    reg_number VARCHAR(50) DEFAULT NULL,
    cgpa NUMERIC(4,2) DEFAULT 8.50,
    bio TEXT DEFAULT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    github_url VARCHAR(255) DEFAULT NULL,
    graduation_year INT DEFAULT 2027,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Faculty Profiles Table
CREATE TABLE faculty_profiles (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    faculty_id_number VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender VARCHAR(20),
    dob DATE,
    phone VARCHAR(30),
    department_id INT REFERENCES departments(id) ON DELETE SET NULL,
    designation VARCHAR(100) NOT NULL,
    qualification VARCHAR(150),
    specialization VARCHAR(150),
    joining_date DATE,
    experience_years INT DEFAULT 0,
    office_room VARCHAR(50),
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Courses Table
CREATE TABLE courses (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    department_id INT REFERENCES departments(id) ON DELETE CASCADE,
    credits INT NOT NULL DEFAULT 3,
    semester INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Faculty Courses Allocation Table
CREATE TABLE faculty_courses (
    id SERIAL PRIMARY KEY,
    faculty_id INT REFERENCES faculty_profiles(id) ON DELETE CASCADE,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    academic_year VARCHAR(20) DEFAULT '2025-2026',
    semester INT NOT NULL,
    UNIQUE(faculty_id, course_id, academic_year)
);

-- 7. Course Enrollments Table
CREATE TABLE course_enrollments (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    academic_year VARCHAR(20) DEFAULT '2025-2026',
    semester INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, course_id, academic_year)
);

-- 8. Assignments Table
CREATE TABLE assignments (
    id SERIAL PRIMARY KEY,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    faculty_id INT REFERENCES faculty_profiles(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    max_marks INT NOT NULL DEFAULT 100,
    due_date TIMESTAMP NOT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Assignment Submissions Table
CREATE TABLE assignment_submissions (
    id SERIAL PRIMARY KEY,
    assignment_id INT REFERENCES assignments(id) ON DELETE CASCADE,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    submission_text TEXT,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    marks_obtained NUMERIC(5,2) DEFAULT NULL,
    feedback TEXT,
    graded_at TIMESTAMP DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Submitted' CHECK (status IN ('Pending', 'Submitted', 'Late', 'Under Review', 'Graded')),
    UNIQUE(assignment_id, student_id)
);

-- 10. Materials Table
CREATE TABLE materials (
    id SERIAL PRIMARY KEY,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    faculty_id INT REFERENCES faculty_profiles(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size BIGINT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Attendance Table
CREATE TABLE attendance (
    id SERIAL PRIMARY KEY,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('Present', 'Absent')),
    marked_by INT REFERENCES faculty_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(course_id, student_id, date)
);

-- 12. Marks / Results Table
CREATE TABLE marks (
    id SERIAL PRIMARY KEY,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    internal_marks NUMERIC(5,2) DEFAULT 0,
    assignment_marks NUMERIC(5,2) DEFAULT 0,
    quiz_marks NUMERIC(5,2) DEFAULT 0,
    mid_exam NUMERIC(5,2) DEFAULT 0,
    final_exam NUMERIC(5,2) DEFAULT 0,
    total_marks NUMERIC(5,2) DEFAULT 0,
    grade VARCHAR(5) DEFAULT 'F',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(course_id, student_id)
);

-- 13. Announcements Table
CREATE TABLE announcements (
    id SERIAL PRIMARY KEY,
    author_id INT REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    target_department_id INT REFERENCES departments(id) ON DELETE SET NULL,
    target_course_id INT REFERENCES courses(id) ON DELETE SET NULL,
    circular_image VARCHAR(255) DEFAULT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Normal' CHECK (priority IN ('Normal', 'Important', 'Urgent')),
    publish_date DATE DEFAULT CURRENT_DATE,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 14. Appointments Table
CREATE TABLE appointments (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    faculty_id INT REFERENCES faculty_profiles(id) ON DELETE CASCADE,
    appointment_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Confirmed', 'Rejected', 'Completed', 'Cancelled')),
    faculty_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 15. Projects Table
CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    faculty_advisor_id INT REFERENCES faculty_profiles(id) ON DELETE SET NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    technologies VARCHAR(255),
    status VARCHAR(30) NOT NULL DEFAULT 'Proposed' CHECK (status IN ('Proposed', 'Approved', 'In Progress', 'Rejected', 'Completed')),
    progress_percent INT DEFAULT 0 CHECK (progress_percent BETWEEN 0 AND 100),
    document_path VARCHAR(255) DEFAULT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 16. Project Milestones Table
CREATE TABLE project_milestones (
    id SERIAL PRIMARY KEY,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    due_date DATE,
    status VARCHAR(20) DEFAULT 'Pending' CHECK (status IN ('Pending', 'In Progress', 'Completed')),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 17. Notifications Table
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 18. Activity Logs Table
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 19. Login Attempts Table
CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    identifier VARCHAR(150) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_success BOOLEAN DEFAULT FALSE
);

-- 20. Schema Migrations History Table
CREATE TABLE IF NOT EXISTS schema_migrations (
    id SERIAL PRIMARY KEY,
    version VARCHAR(50) UNIQUE NOT NULL,
    migration_name VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time_ms INT DEFAULT 0
);

-- INDEXES for speed & searching
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_student_dept ON student_profiles(department_id);
CREATE INDEX idx_faculty_dept ON faculty_profiles(department_id);
CREATE INDEX idx_enrollments_student ON course_enrollments(student_id);
CREATE INDEX idx_enrollments_course ON course_enrollments(course_id);
CREATE INDEX idx_assignments_course ON assignments(course_id);
CREATE INDEX idx_submissions_assignment ON assignment_submissions(assignment_id);
CREATE INDEX idx_submissions_student ON assignment_submissions(student_id);
CREATE INDEX idx_attendance_student ON attendance(student_id);
CREATE INDEX idx_attendance_course ON attendance(course_id);
CREATE INDEX idx_attendance_course_date ON attendance(course_id, date);
CREATE INDEX idx_faculty_courses_faculty ON faculty_courses(faculty_id);
CREATE INDEX idx_announcements_created ON announcements(created_at DESC);
CREATE INDEX idx_projects_student ON projects(student_id);
CREATE INDEX idx_projects_advisor ON projects(faculty_advisor_id);
CREATE INDEX idx_appointments_student ON appointments(student_id);
CREATE INDEX idx_appointments_faculty ON appointments(faculty_id);
CREATE INDEX idx_materials_course ON materials(course_id);
CREATE INDEX idx_marks_student ON marks(student_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_login_attempts_ip_time ON login_attempts(ip_address, attempted_at);
CREATE INDEX idx_login_attempts_ident_time ON login_attempts(identifier, attempted_at);

-- Record baseline migrations
INSERT INTO schema_migrations (version, migration_name, execution_time_ms) VALUES
('001', 'baseline_schema', 1),
('002', 'integrity_constraints_and_indexes', 1),
('003', 'login_throttling_table', 1)
ON CONFLICT (version) DO NOTHING;



-- ====================================================
-- SEED DATA (REALISTIC DEMO ACCOUNTS AND DATA)
-- Password for all seed accounts: Password123
-- Hash: $2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG
-- ====================================================

-- 1. Departments
INSERT INTO departments (code, name, description) VALUES
('CS', 'Computer Science & Engineering', 'Department of CS focusing on AI, Software Development, and Systems.'),
('IT', 'Information Technology', 'Department of IT specializing in Web Tech, Cloud, and Networking.'),
('ECE', 'Electronics & Communication', 'Department of ECE covering Microprocessors, VLSI, and Signal Processing.'),
('ME', 'Mechanical Engineering', 'Department of Mechanical Engineering focusing on Robotics and Thermal Eng.'),
('CE', 'Civil Engineering', 'Department of Civil Engineering covering Structural and Environmental Eng.');

-- 2. Users (1 Admin, 3 Faculty, 5 Students)
INSERT INTO users (email, username, password_hash, role, status) VALUES
-- Admin
('admin@acadnexus.test', 'admin', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'admin', 'active'),

-- Faculty
('faculty@acadnexus.test', 'dr_alan', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'faculty', 'active'),
('sarah.jenkins@acadnexus.test', 'prof_sarah', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'faculty', 'active'),
('robert.chen@acadnexus.test', 'dr_robert', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'faculty', 'active'),

-- Students
('student@acadnexus.test', 'alex_rivera', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'student', 'active'),
('emily.watson@acadnexus.test', 'emily_w', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'student', 'active'),
('michael.brown@acadnexus.test', 'mbrown', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'student', 'active'),
('sophia.davis@acadnexus.test', 'sdavis', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'student', 'active'),
('daniel.kim@acadnexus.test', 'dkim', '$2y$12$8gCyjExdPhpWGmqjPbqCsOcr8LIJWFMJFUrOz3y7EzI0sO7ZmcZdG', 'student', 'active');

-- 3. Faculty Profiles
INSERT INTO faculty_profiles (user_id, faculty_id_number, first_name, last_name, gender, dob, phone, department_id, designation, qualification, specialization, joining_date, experience_years, office_room) VALUES
(2, 'FAC-CS-001', 'Alan', 'Turing', 'Male', '1980-06-23', '+1 555-0101', 1, 'Professor & Head', 'Ph.D in Computer Science', 'Distributed Systems & Database Architectures', '2015-08-15', 12, 'Tech Tower 402'),
(3, 'FAC-CS-002', 'Sarah', 'Jenkins', 'Female', '1985-11-14', '+1 555-0102', 1, 'Associate Professor', 'Ph.D in Software Engineering', 'Web Technologies & Cloud Computing', '2018-01-10', 8, 'Tech Tower 408'),
(4, 'FAC-IT-001', 'Robert', 'Chen', 'Male', '1982-03-30', '+1 555-0103', 2, 'Senior Assistant Professor', 'M.Tech & Ph.D in IT', 'Cybersecurity & Computer Networks', '2019-07-20', 6, 'IT Block 204');

-- 4. Student Profiles
INSERT INTO student_profiles (user_id, student_id_number, first_name, last_name, gender, dob, phone, department_id, program, year_of_study, semester, section, admission_year, address, city, state, postal_code) VALUES
(5, 'ANX6312', 'Alex', 'Rivera', 'Male', '2003-05-12', '+1 555-0201', 1, 'B.Tech Computer Science', 3, 6, 'A', 2023, '124 Innovation Way', 'Boston', 'MA', '02115'),
(6, 'ANX5600', 'Emily', 'Watson', 'Female', '2003-09-28', '+1 555-0202', 1, 'B.Tech Computer Science', 3, 6, 'A', 2023, '89 Beacon Street', 'Boston', 'MA', '02116'),
(7, 'ANX2718', 'Michael', 'Brown', 'Male', '2003-02-19', '+1 555-0203', 2, 'B.Tech Information Technology', 3, 6, 'B', 2023, '45 Harvard Ave', 'Cambridge', 'MA', '02138'),
(8, 'ANX5040', 'Sophia', 'Davis', 'Female', '2004-01-05', '+1 555-0204', 1, 'B.Tech Computer Science', 2, 4, 'A', 2024, '312 College Road', 'Newton', 'MA', '02458'),
(9, 'ANX6942', 'Daniel', 'Kim', 'Male', '2002-12-14', '+1 555-0205', 2, 'B.Tech Information Technology', 4, 8, 'A', 2022, '77 Tech Drive', 'Somerville', 'MA', '02144');

-- 5. Courses
INSERT INTO courses (code, name, department_id, credits, semester, description) VALUES
('CS-301', 'PHP & Web Development', 1, 4, 6, 'Comprehensive course on modern PHP backend engineering, PostgreSQL Integration, and REST API architecture.'),
('CS-302', 'Database Management Systems', 1, 4, 6, 'Deep dive into relational database concepts, indexing, SQL query optimization, and transactions.'),
('CS-303', 'Java Enterprise Programming', 1, 3, 6, 'Object-oriented application building using Java enterprise patterns and framework architecture.'),
('IT-304', 'Computer Networks & Security', 2, 4, 6, 'Network protocol analysis, routing algorithms, socket programming, and cryptographic primitives.'),
('CS-305', 'Software Engineering & Agile', 1, 3, 6, 'System design, microservices, SDLC modeling, agile workflow, and code metrics.');

-- 6. Faculty Courses Allocation
INSERT INTO faculty_courses (faculty_id, course_id, semester) VALUES
(1, 1, 6), -- Dr. Alan -> PHP
(1, 2, 6), -- Dr. Alan -> DBMS
(2, 3, 6), -- Prof. Sarah -> Java
(2, 5, 6), -- Prof. Sarah -> Software Eng
(3, 4, 6); -- Dr. Robert -> Networks

-- 7. Course Enrollments (Alex Rivera - student 1 - enrolled in 4 courses)
INSERT INTO course_enrollments (student_id, course_id, semester) VALUES
(1, 1, 6), -- Alex -> PHP
(1, 2, 6), -- Alex -> DBMS
(1, 3, 6), -- Alex -> Java
(1, 4, 6), -- Alex -> Networks
(2, 1, 6), -- Emily -> PHP
(2, 2, 6), -- Emily -> DBMS
(3, 4, 6), -- Michael -> Networks
(4, 1, 4), -- Sophia -> PHP
(5, 4, 8); -- Daniel -> Networks

-- 8. Assignments
INSERT INTO assignments (course_id, faculty_id, title, description, max_marks, due_date) VALUES
(1, 1, 'Assignment 1: Database-Driven PHP Authentication', 'Build a secure session-based authentication module using PDO PostgreSQL prepared statements and password_hash.', 100, NOW() + INTERVAL '5 days'),
(1, 1, 'Assignment 2: Dynamic Dashboard REST Service', 'Create PHP backend endpoints serving json statistics and performance metrics for Chart.js rendering.', 100, NOW() + INTERVAL '12 days'),
(2, 1, 'DBMS Project: B-Tree Indexing & Query Plan Optimization', 'Analyze query plans using EXPLAIN ANALYZE in PostgreSQL and construct indexes to minimize execution latency.', 100, NOW() + INTERVAL '8 days'),
(3, 2, 'Java Enterprise Microservice Implementation', 'Implement a RESTful product management service with input validation and exception handlers.', 50, NOW() + INTERVAL '3 days');

-- 9. Assignment Submissions (Alex Rivera submitted Assignment 1)
INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, marks_obtained, feedback, graded_at, status) VALUES
(1, 1, 'Implemented PDO session authentication with password_verify and CSRF protection token validation.', 95.00, 'Excellent security practice and clean database abstraction layer!', NOW(), 'Graded'),
(3, 1, 'Analyzed indexing performance on 10,000 rows. EXPLAIN ANALYZE attached.', NULL, NULL, NULL, 'Submitted');

-- 10. Materials
INSERT INTO materials (course_id, faculty_id, title, description, file_path, file_type, file_size) VALUES
(1, 1, 'Lecture 01: Modern PHP 8 Core Features & PDO', 'Comprehensive slide deck covering PDO prepared statements, error handling modes, and transaction blocks.', 'uploads/materials/php_pdo_lecture.pdf', 'pdf', 2450000),
(1, 1, 'Lecture 02: PostgreSQL Integration & Normalization', 'Guide on database normalization up to 3NF and PostgreSQL specific features.', 'uploads/materials/postgres_guide.pdf', 'pdf', 1850000),
(2, 1, 'DBMS Architecture Cheat Sheet', 'Quick reference guide for relational algebra and SQL optimization.', 'uploads/materials/dbms_cheatsheet.pdf', 'pdf', 1200000);

-- 11. Attendance Records for Alex Rivera (Student ID 1) in Course 1 & 2
INSERT INTO attendance (course_id, student_id, date, status, marked_by) VALUES
(1, 1, CURRENT_DATE - INTERVAL '10 days', 'Present', 1),
(1, 1, CURRENT_DATE - INTERVAL '8 days', 'Present', 1),
(1, 1, CURRENT_DATE - INTERVAL '6 days', 'Present', 1),
(1, 1, CURRENT_DATE - INTERVAL '4 days', 'Present', 1),
(1, 1, CURRENT_DATE - INTERVAL '2 days', 'Absent', 1),
(1, 1, CURRENT_DATE, 'Present', 1),

(2, 1, CURRENT_DATE - INTERVAL '9 days', 'Present', 1),
(2, 1, CURRENT_DATE - INTERVAL '7 days', 'Present', 1),
(2, 1, CURRENT_DATE - INTERVAL '5 days', 'Present', 1),
(2, 1, CURRENT_DATE - INTERVAL '3 days', 'Absent', 1),
(2, 1, CURRENT_DATE - INTERVAL '1 days', 'Present', 1);

-- 12. Marks / Results for Alex Rivera (Student ID 1)
INSERT INTO marks (course_id, student_id, internal_marks, assignment_marks, quiz_marks, mid_exam, final_exam, total_marks, grade) VALUES
(1, 1, 18.5, 24.0, 9.0, 26.5, 0.0, 78.0, 'A'),
(2, 1, 17.0, 22.5, 8.5, 25.0, 0.0, 73.0, 'B+'),
(3, 1, 19.0, 25.0, 10.0, 28.0, 0.0, 82.0, 'A+'),
(4, 1, 16.5, 21.0, 8.0, 24.0, 0.0, 69.5, 'B');

-- 13. Announcements
INSERT INTO announcements (author_id, title, content, priority, publish_date, expiry_date) VALUES
(1, 'End-Semester Project Guidelines Released', 'All final year and 3rd year students must submit project proposals approved by their respective faculty mentors before the end of the week.', 'Urgent', CURRENT_DATE - INTERVAL '2 days', CURRENT_DATE + INTERVAL '14 days'),
(2, 'Guest Lecture on Cloud Native Architecture', 'Dr. Alan Turing will host a guest lecture on Kubernetes & PostgreSQL clustering next Monday in Tech Auditorium 1 at 10:00 AM.', 'Important', CURRENT_DATE - INTERVAL '1 days', CURRENT_DATE + INTERVAL '7 days'),
(3, 'Library Mid-Term Extended Hours', 'The central academic library will remain open 24/7 during the upcoming examination period.', 'Normal', CURRENT_DATE, CURRENT_DATE + INTERVAL '20 days');

-- 14. Appointments
INSERT INTO appointments (student_id, faculty_id, appointment_date, time_slot, reason, status, faculty_notes) VALUES
(1, 1, CURRENT_DATE + INTERVAL '2 days', '10:30 AM - 11:00 AM', 'Discussion regarding Final Year Capstone Project proposal and database model.', 'Confirmed', 'Approved for meeting in Room 402.'),
(2, 1, CURRENT_DATE + INTERVAL '3 days', '02:00 PM - 02:30 PM', 'Doubt clearing on DBMS B-Tree indexing assignment.', 'Pending', NULL);

-- 15. Projects
INSERT INTO projects (student_id, faculty_advisor_id, title, description, technologies, status, progress_percent, feedback) VALUES
(1, 1, 'AcadNexus - Academic Management System', 'A high performance web platform connecting students and faculty for assignment submission, grading, and tracking.', 'PHP 8, PostgreSQL, JavaScript, HTML5', 'In Progress', 75, 'Great architecture. Ensure role security and input sanitization on all endpoints.');

-- 16. Project Milestones
INSERT INTO project_milestones (project_id, title, description, due_date, status) VALUES
(1, 'Database Schema & Connection Module', 'Design 3NF relational schema in PostgreSQL and build PDO connector.', CURRENT_DATE - INTERVAL '15 days', 'Completed'),
(1, 'Role-based Authentication & Dashboards', 'Implement student, faculty, and admin authorization levels and interactive UI.', CURRENT_DATE - INTERVAL '5 days', 'Completed'),
(1, 'Core Academic Modules Implementation', 'Build Assignments, Attendance, Materials, and Results tracking modules.', CURRENT_DATE + INTERVAL '10 days', 'In Progress');

-- 17. Notifications for Alex Rivera (User ID 5)
INSERT INTO notifications (user_id, title, message, link) VALUES
(5, 'Assignment Graded', 'Your submission for Assignment 1: Database-Driven PHP Authentication has been graded (95/100).', 'student/assignment_view.php?id=1'),
(5, 'Appointment Confirmed', 'Dr. Alan Turing confirmed your appointment for discussion on ' || (CURRENT_DATE + INTERVAL '2 days')::text, 'student/appointments.php'),
(5, 'New Announcement', 'End-Semester Project Guidelines Released by Administration.', 'student/announcements.php');

-- 18. Activity Logs
INSERT INTO activity_logs (user_id, action, ip_address) VALUES
(5, 'User Login Successful', '127.0.0.1'),
(5, 'Submitted Assignment 1', '127.0.0.1'),
(2, 'Faculty Login Successful', '127.0.0.1');
