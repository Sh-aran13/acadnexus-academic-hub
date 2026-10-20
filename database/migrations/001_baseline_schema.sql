-- ====================================================
-- MIGRATION 001: Baseline Schema Alignment
-- Ensures all columns, tables, and types match the canonical data model.
-- ====================================================

-- 1. Ensure student_profiles has all required institutional and profile columns
ALTER TABLE student_profiles 
    ADD COLUMN IF NOT EXISTS reg_number VARCHAR(50),
    ADD COLUMN IF NOT EXISTS cgpa NUMERIC(4,2) DEFAULT 8.50,
    ADD COLUMN IF NOT EXISTS bio TEXT,
    ADD COLUMN IF NOT EXISTS linkedin_url VARCHAR(255),
    ADD COLUMN IF NOT EXISTS github_url VARCHAR(255),
    ADD COLUMN IF NOT EXISTS graduation_year INT DEFAULT 2027;

-- 2. Ensure announcements has circular_image
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS circular_image VARCHAR(255) DEFAULT NULL;

-- 3. Ensure course_enrollments table exists with academic_year
CREATE TABLE IF NOT EXISTS course_enrollments (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES student_profiles(id) ON DELETE CASCADE,
    course_id INT REFERENCES courses(id) ON DELETE CASCADE,
    academic_year VARCHAR(20) DEFAULT '2025-2026',
    semester INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, course_id, academic_year)
);
