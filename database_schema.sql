-- ============================================================
-- QA Testing Portal - Node.js MySQL Schema
-- Runtime: Express.js + JWT + Playwright
-- Updated: 2026-05-19
--
-- Beginner hosting notes:
-- 1. Create the database first in your host dashboard, then run this file.
-- 2. This file is safe to run more than once for fresh installs because it
--    uses CREATE TABLE IF NOT EXISTS and an idempotent default admin insert.
-- 3. If you already have older tables, read the "Existing database migration"
--    block near the bottom before importing.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Create/select the application database for tools that import SQL without
-- choosing a default schema first, such as MySQL Workbench restore/import.
-- If your hosting provider gives you a different database name, change both
-- lines below and set DB_NAME in your environment to the same value.
CREATE DATABASE IF NOT EXISTS testing_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE testing_portal;

-- ============================================================
-- 1. ADMIN TABLE
-- ============================================================
-- Used by POST /api/login.
-- The current Node auth layer supports the legacy MD5 seed below.
-- Replace this seed password immediately in production.
CREATE TABLE IF NOT EXISTS admin (
  id INT NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'Password hash. Legacy MD5 seed is supported by api/auth.js.',
  name VARCHAR(255) NOT NULL DEFAULT 'Administrator',
  status VARCHAR(50) NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email),
  KEY idx_admin_status (status),
  KEY idx_admin_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. USERS TABLE
-- ============================================================
-- Used by POST /api/login for non-admin portal users.
CREATE TABLE IF NOT EXISTS users (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'Password hash.',
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  status VARCHAR(50) NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status),
  KEY idx_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. TEST_REPORTS TABLE
-- ============================================================
-- Used by:
-- - POST /api/save-test-report
-- - GET  /api/reports
-- - GET  /api/get-test-reports
-- - GET  /reports/:id
-- - GET  /reports/:id/download
--
-- Important:
-- pdf_path and report_html store public app paths, not local Windows paths.
-- Example: /automation/results/quick_20260517_120000_abcd.pdf
CREATE TABLE IF NOT EXISTS test_reports (
  id INT NOT NULL AUTO_INCREMENT,
  test_link VARCHAR(1000) NOT NULL COMMENT 'URL that was tested.',
  execution_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the test was executed.',
  pdf_path VARCHAR(1000) DEFAULT NULL COMMENT 'Public path to PDF report artifact.',
  report_html VARCHAR(1000) DEFAULT NULL COMMENT 'Public path to HTML or JSON report artifact.',
  report_type VARCHAR(50) DEFAULT NULL COMMENT 'order-flow, quick-check, 88startech, or Quick Check. Runtime can infer this from artifact paths when null.',
  run_id VARCHAR(255) DEFAULT NULL COMMENT 'Automation run id such as ofr_* or quick_*.',
  offer_name VARCHAR(255) DEFAULT NULL COMMENT 'Offer or report display name.',
  browser_name VARCHAR(100) DEFAULT NULL COMMENT 'Browser used by the automation where available.',
  pass_count INT NOT NULL DEFAULT 0 COMMENT 'Passed checks/orders count, if known.',
  fail_count INT NOT NULL DEFAULT 0 COMMENT 'Failed checks/orders count, if known.',
  status VARCHAR(50) NOT NULL DEFAULT 'Completed' COMMENT 'Pending, Running, Completed, Failed, Stopped.',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_test_reports_execution_date (execution_date),
  KEY idx_test_reports_status (status),
  KEY idx_test_reports_report_type (report_type),
  KEY idx_test_reports_run_id (run_id),
  KEY idx_test_reports_created_at (created_at),
  KEY idx_test_reports_test_link (test_link(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. AUTOMATION_LOGS TABLE
-- ============================================================
-- Used by:
-- - POST /api/run-automation
-- - GET  /api/quick-check
-- - GET  /api/automation-logs
CREATE TABLE IF NOT EXISTS automation_logs (
  id INT NOT NULL AUTO_INCREMENT,
  run_type VARCHAR(50) NOT NULL COMMENT 'order-flow or quick-check.',
  status VARCHAR(50) NOT NULL DEFAULT 'Running' COMMENT 'Running, Completed, Failed, Stopped.',
  input_url VARCHAR(1000) DEFAULT NULL,
  report_id VARCHAR(255) DEFAULT NULL,
  report_path VARCHAR(1000) DEFAULT NULL,
  log_path VARCHAR(1000) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL DEFAULT NULL,
  created_by VARCHAR(255) DEFAULT NULL COMMENT 'Email from JWT payload when available.',
  PRIMARY KEY (id),
  KEY idx_automation_logs_run_type (run_type),
  KEY idx_automation_logs_status (status),
  KEY idx_automation_logs_started_at (started_at),
  KEY idx_automation_logs_report_id (report_id),
  KEY idx_automation_logs_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXISTING DATABASE MIGRATION NOTES
-- ============================================================
-- If you already imported an older schema and columns are missing, the Node
-- app will try to add the missing additive columns on startup. If your DB user
-- does not have ALTER permission, run only the ALTER statements you need below.
-- Some MySQL hosts do not support "ADD COLUMN IF NOT EXISTS", so these are
-- commented out intentionally.
--
-- ALTER TABLE test_reports ADD COLUMN report_type VARCHAR(50) DEFAULT NULL COMMENT 'order-flow, quick-check, 88startech, or Quick Check.';
-- ALTER TABLE test_reports ADD COLUMN run_id VARCHAR(255) DEFAULT NULL COMMENT 'Automation run id such as ofr_* or quick_*.';
-- ALTER TABLE test_reports ADD COLUMN offer_name VARCHAR(255) DEFAULT NULL COMMENT 'Offer or report display name.';
-- ALTER TABLE test_reports ADD COLUMN browser_name VARCHAR(100) DEFAULT NULL COMMENT 'Browser used by the automation where available.';
-- ALTER TABLE test_reports ADD COLUMN pass_count INT NOT NULL DEFAULT 0 COMMENT 'Passed checks/orders count, if known.';
-- ALTER TABLE test_reports ADD COLUMN fail_count INT NOT NULL DEFAULT 0 COMMENT 'Failed checks/orders count, if known.';
-- ALTER TABLE test_reports ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
--
-- ALTER TABLE automation_logs ADD COLUMN created_by VARCHAR(255) DEFAULT NULL COMMENT 'Email from JWT payload when available.';
-- ALTER TABLE automation_logs ADD COLUMN finished_at DATETIME NULL DEFAULT NULL;
--
-- Optional indexes for older databases after adding the columns above.
-- Run only indexes that do not already exist.
--
-- ALTER TABLE test_reports ADD INDEX idx_test_reports_report_type (report_type);
-- ALTER TABLE test_reports ADD INDEX idx_test_reports_run_id (run_id);
-- ALTER TABLE automation_logs ADD INDEX idx_automation_logs_created_by (created_by);

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Default admin account:
-- email:    admin@example.com
-- password: admin123
--
-- The password is stored as an MD5 compatibility seed because api/auth.js
-- currently supports the migrated legacy password format.
-- Change this account immediately after first login.
INSERT INTO admin (email, password)
SELECT 'admin@example.com', '0192023a7bbd73250516f069df18b500'
WHERE NOT EXISTS (
  SELECT 1 FROM admin WHERE email = 'admin@example.com'
);

-- Optional demo user account:
-- email:    tester@example.com
-- password: tester123
-- Uncomment only for demos, then change/remove it before production use.
-- INSERT INTO users (name, email, password, role, status)
-- SELECT 'QA Tester', 'tester@example.com', '8e607a4752fa2e59413e5790536f2b42', 'user', 'Active'
-- WHERE NOT EXISTS (
--   SELECT 1 FROM users WHERE email = 'tester@example.com'
-- );
