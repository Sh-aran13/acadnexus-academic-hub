-- ====================================================
-- MIGRATION 002: Integrity Constraints and Performance Indexes
-- Adds range checks and performance indexes safely
-- ====================================================

-- 1. Marks check constraints (valid ranges 0 to 100)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_marks_range') THEN
        ALTER TABLE marks ADD CONSTRAINT chk_marks_range CHECK (
            internal_marks >= 0 AND internal_marks <= 100 AND
            assignment_marks >= 0 AND assignment_marks <= 100 AND
            quiz_marks >= 0 AND quiz_marks <= 100 AND
            mid_exam >= 0 AND mid_exam <= 100 AND
            final_exam >= 0 AND final_exam <= 100 AND
            total_marks >= 0 AND total_marks <= 100
        );
    END IF;
END $$;

-- 2. Courses check constraints (valid credits and semester)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_courses_credits_sem') THEN
        ALTER TABLE courses ADD CONSTRAINT chk_courses_credits_sem CHECK (
            credits BETWEEN 1 AND 12 AND
            semester BETWEEN 1 AND 10
        );
    END IF;
END $$;

-- 3. Assignments check constraints
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_assignments_max_marks') THEN
        ALTER TABLE assignments ADD CONSTRAINT chk_assignments_max_marks CHECK (
            max_marks > 0 AND max_marks <= 1000
        );
    END IF;
END $$;

-- 4. Attendance check constraints
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_attendance_status') THEN
        ALTER TABLE attendance ADD CONSTRAINT chk_attendance_status CHECK (
            status IN ('Present', 'Absent')
        );
    END IF;
END $$;

-- 5. Appointments check constraints
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_appointments_status') THEN
        ALTER TABLE appointments ADD CONSTRAINT chk_appointments_status CHECK (
            status IN ('Pending', 'Confirmed', 'Rejected', 'Completed', 'Cancelled')
        );
    END IF;
END $$;

-- 6. Performance Indexes
CREATE INDEX IF NOT EXISTS idx_attendance_course_date ON attendance(course_id, date);
CREATE INDEX IF NOT EXISTS idx_faculty_courses_faculty ON faculty_courses(faculty_id);
CREATE INDEX IF NOT EXISTS idx_announcements_created ON announcements(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_projects_student ON projects(student_id);
CREATE INDEX IF NOT EXISTS idx_projects_advisor ON projects(faculty_advisor_id);
CREATE INDEX IF NOT EXISTS idx_appointments_student ON appointments(student_id);
CREATE INDEX IF NOT EXISTS idx_appointments_faculty ON appointments(faculty_id);
CREATE INDEX IF NOT EXISTS idx_materials_course ON materials(course_id);
