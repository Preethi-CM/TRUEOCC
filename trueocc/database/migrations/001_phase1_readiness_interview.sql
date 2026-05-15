-- Phase 1: Career readiness columns + interview session tracking
-- Run once on existing DB: mysql -u root true_occupation < database/migrations/001_phase1_readiness_interview.sql

USE true_occupation;

CREATE TABLE IF NOT EXISTS interview_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    attempt_number INT UNSIGNED NOT NULL,
    target_role VARCHAR(200) NULL,
    avg_ai_score DECIMAL(5,2) NULL,
    questions_count SMALLINT UNSIGNED NULL,
    summary_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    KEY idx_int_sess_user_open (user_id, completed_at)
) ENGINE=InnoDB;

ALTER TABLE job_fit_scores
    ADD COLUMN readiness_score DECIMAL(5,2) NULL DEFAULT NULL AFTER total_fit_score,
    ADD COLUMN readiness_breakdown TEXT NULL DEFAULT NULL AFTER readiness_score;
