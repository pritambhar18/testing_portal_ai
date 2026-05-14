-- ============================================
-- QA Testing Portal Database Schema
-- Version: 1.0
-- Updated: 2026-05-08
-- ============================================

-- ============================================
-- 1. ADMIN TABLE
-- ============================================
-- Stores the default admin account(s)
-- Supports login alongside regular users
CREATE TABLE IF NOT EXISTS admin (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL COMMENT 'Password hash (password_hash or MD5 for legacy)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. USERS TABLE
-- ============================================
-- Stores portal user accounts created via admin panel
-- Uses password_hash() for secure password storage
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL COMMENT 'Password hash (password_hash format)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. TEST_REPORTS TABLE
-- ============================================
-- Stores test reports with execution metadata
-- Includes both HTML and PDF artifact locations, timestamps, and status
CREATE TABLE IF NOT EXISTS test_reports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_link VARCHAR(500) NOT NULL COMMENT 'URL of the test',
  execution_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the test was executed',
  pdf_path VARCHAR(1000) DEFAULT NULL COMMENT 'Relative path to generated PDF report (e.g., reports/generated/*.pdf)',
  report_html VARCHAR(1000) DEFAULT NULL COMMENT 'Relative path to generated HTML report (e.g., reports/generated/*.html or uploads fallback)',
  status VARCHAR(50) NOT NULL DEFAULT 'Completed' COMMENT 'Pending, Running, Completed, Failed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_execution_date (execution_date),
  INDEX idx_test_link (test_link),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default admin account (MD5 hash of 'admin123')
-- IMPORTANT: Change this password immediately after first login!
INSERT INTO admin (email, password)
SELECT 'admin@example.com', '0192023a7bbd73250516f069df18b500'
WHERE NOT EXISTS (SELECT 1 FROM admin WHERE email = 'admin@example.com');
