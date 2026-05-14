<?php
/**
 * helpers/TestReportLogger.php
 * Logs test reports to the test_reports table for the View Reports module
 */

class TestReportLogger {
    private $conn;

    public function __construct($mysqli_connection) {
        $this->conn = $mysqli_connection;
    }

    /**
     * Insert a test report entry
     *
     * @param string $test_link - The URL that was tested
     * @param string $pdf_path - Relative path to the PDF report
     * @return bool|int - Report ID on success, false on failure
     */
    public function logTestReport($test_link, $pdf_path = null, $report_html = null) {
        if (!$this->conn || !($this->conn instanceof mysqli)) {
            return false;
        }

        // Validate inputs
        if (empty($test_link)) {
            return false;
        }

        try {
            // Prepare insert including optional report_html column if present in schema
            $columns = ['test_link', 'execution_date', 'pdf_path'];
            $placeholders = ['?', 'NOW()', '?'];
            $types = 'ss';
            $values = [$test_link, $pdf_path];

            // Detect if report_html column exists by attempting a SHOW COLUMNS query (best-effort)
            $hasReportHtml = false;
            try {
                $check = $this->conn->query("SHOW COLUMNS FROM test_reports LIKE 'report_html'");
                if ($check && $check->num_rows > 0) {
                    $hasReportHtml = true;
                }
            } catch (Exception $e) {
                // ignore - proceed without report_html
            }

            if ($hasReportHtml) {
                $columns[] = 'report_html';
                $placeholders[] = '?';
                $types = 'sss';
                $values[] = $report_html;
            }

            $sql = 'INSERT INTO test_reports (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                return false;
            }

            // Bind params dynamically
            $stmt->bind_param($types, ...$values);

            if ($stmt->execute()) {
                $reportId = $stmt->insert_id;
                $stmt->close();
                return $reportId;
            }

            $stmt->close();
            return false;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update an existing test report with PDF path
     *
     * @param int $report_id - The report ID to update
     * @param string $pdf_path - Relative path to the PDF report
     * @return bool - True on success, false on failure
     */
    public function updateReportPdfPath($report_id, $pdf_path) {
        if (!$this->conn || !($this->conn instanceof mysqli)) {
            return false;
        }

        if (empty($report_id) || empty($pdf_path)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE test_reports
                SET pdf_path = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('si', $pdf_path, $report_id);

            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }

            $stmt->close();
            return false;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get recent test reports
     *
     * @param int $limit - Number of recent reports to fetch
     * @return array - Array of report records
     */
    public function getRecentReports($limit = 10) {
        if (!$this->conn || !($this->conn instanceof mysqli)) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    id,
                    test_link,
                    execution_date,
                    pdf_path,
                    created_at
                FROM test_reports
                ORDER BY execution_date DESC
                LIMIT ?
            ");

            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $reports = [];
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }

            $stmt->close();
            return $reports;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get test reports by test link
     *
     * @param string $test_link - The test link to search for
     * @param int $limit - Maximum results to return
     * @return array - Array of report records
     */
    public function getReportsByLink($test_link, $limit = 100) {
        if (!$this->conn || !($this->conn instanceof mysqli)) {
            return [];
        }

        if (empty($test_link)) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    id,
                    test_link,
                    execution_date,
                    pdf_path,
                    created_at
                FROM test_reports
                WHERE test_link = ?
                ORDER BY execution_date DESC
                LIMIT ?
            ");

            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('si', $test_link, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $reports = [];
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }

            $stmt->close();
            return $reports;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get report count
     *
     * @return int - Total number of reports
     */
    public function getReportCount() {
        if (!$this->conn || !($this->conn instanceof mysqli)) {
            return 0;
        }

        try {
            $result = $this->conn->query("SELECT COUNT(*) as total FROM test_reports");

            if ($result) {
                $row = $result->fetch_assoc();
                return (int)$row['total'];
            }

            return 0;

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Delete old test reports (cleanup)
     *
     * @param int $days - Delete reports older than this many days
     * @return int - Number of reports deleted
     */
    public function deleteOldReports($days = 30) {
        if (!$this->conn || !($this->conn instanceof mysqli)) {
            return 0;
        }

        try {
            $stmt = $this->conn->prepare("
                DELETE FROM test_reports
                WHERE execution_date < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            if (!$stmt) {
                return 0;
            }

            $stmt->bind_param('i', $days);
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();

            return $affectedRows > 0 ? $affectedRows : 0;

        } catch (Exception $e) {
            return 0;
        }
    }
}
?>
