# Database Schema — testing_portal

This document records the canonical schema for the test reports used by the testing_portal application and provides migration SQL to be run on production.

## test_reports (recommended schema)

Example CREATE TABLE (MySQL / MariaDB):

```
CREATE TABLE test_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  pdf_path VARCHAR(1000) DEFAULT NULL,
  report_html VARCHAR(2000) DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Completed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  notes TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Notes:
- report_html stores the generated HTML report (if HTML saving is enabled).
- pdf_path stores the path to the generated PDF file (if created).
- updated_at is updated automatically when a record is modified.
- VARCHAR lengths are conservative to allow long paths/URLs on some hosts.

## Migration SQL (run after backing up DB)

Run these statements to add the new columns and increase pdf_path length. Execute in a maintenance window and ensure backups.

```
ALTER TABLE test_reports ADD COLUMN report_html VARCHAR(2000) NULL;
ALTER TABLE test_reports MODIFY COLUMN pdf_path VARCHAR(1000);
ALTER TABLE test_reports ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

If MODIFY fails because the column does not exist or has a different type, inspect the current definition (SHOW CREATE TABLE test_reports) and adapt accordingly.

## Verification
1. Backup the database (mysqldump or phpMyAdmin export).
2. Run the ALTER statements.
3. Confirm with:
   - `DESCRIBE test_reports;`
   - `SELECT id, pdf_path, report_html, created_at, updated_at FROM test_reports LIMIT 5;`

## Application notes
- helpers/TestReportLogger.php is implemented to detect whether `report_html` exists and will insert it when present. No code change is required beyond running the migration.
- The application will prefer serving `pdf_path` when present, otherwise `report_html`, then fallback to generated file searches.

If you want, provide DB connection details (or run the SQL yourself) and I can generate a single SQL file for import or a step-by-step migration script.