-- ====================================================
-- MIGRATION 003: Login Throttling & Brute-Force Tracking
-- ====================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    identifier VARCHAR(150) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_success BOOLEAN DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts(ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ident_time ON login_attempts(identifier, attempted_at);
