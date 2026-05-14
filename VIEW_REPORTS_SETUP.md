# View Reports Module - Setup and Implementation Guide

## Overview

The View Reports module adds a comprehensive reporting interface to the Admin Testing Portal, allowing administrators to view, manage, and download test execution reports in a centralized location.

## Features Implemented

✅ **View Reports Page** - Displays all test reports in a responsive data table
✅ **Real-time Data Loading** - Fetches reports via JSON API endpoint
✅ **Detailed Report View** - Shows complete report information
✅ **PDF Download** - Download test reports directly from the interface
✅ **Sidebar Navigation** - "View Reports" menu item with PDF icon
✅ **Session Protection** - Admin authentication required
✅ **Responsive Design** - Works on desktop, tablet, and mobile devices
✅ **Error Handling** - Graceful error messages and empty state handling

## Files Created

### Database
- **setup/create_test_reports_table.php** - Database table migration

### UI Pages
- **admin/view_reports.php** - Main reports listing page
- **admin/view_report_details.php** - Individual report details page

### API Endpoints
- **api/get_reports.php** - REST API for fetching reports (JSON)

### Action Handlers
- **actions/download_report.php** - PDF download handler

### Helper Scripts
- **setup/insert_test_reports.php** - Sample data insertion (for testing)

### Modified Files
- **admin/sidebar.php** - Added "View Reports" navigation link

## Database Schema

```sql
CREATE TABLE test_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_link VARCHAR(500) NOT NULL,
    execution_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pdf_path VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_execution_date (execution_date),
    INDEX idx_test_link (test_link)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Setup Instructions

### Step 1: Create Database Table
1. Open your browser and navigate to:
   ```
   http://localhost/xampp/htdocs/testing_portal/setup/create_test_reports_table.php
   ```
2. You should see a message: "✓ test_reports table created successfully!"
3. The table structure will be displayed below

### Step 2: (Optional) Insert Sample Data for Testing
If you want to test the module before running actual tests:

1. Navigate to:
   ```
   http://localhost/xampp/htdocs/testing_portal/setup/insert_test_reports.php
   ```
2. This will insert 5 sample test reports into the database
3. You can then test the View Reports functionality

### Step 3: Access View Reports
1. Login to your Admin Dashboard
2. Click "View Reports" in the left sidebar
3. You should see a table with your test reports

## Usage

### Viewing Reports

1. **Access View Reports Page**: Click "View Reports" from the admin sidebar
2. **View Report Details**: Click the "View" button next to any report to see full details
3. **Download PDF**: Click the "Download" button to download the test report PDF

### Data Display

The View Reports page shows:
- **ID** - Unique report identifier
- **Test Link** - The URL that was tested (clickable to open in new tab)
- **Execution Date** - When the test was executed
- **Actions** - View and Download buttons

### Detailed Report Page

Shows:
- Complete report information
- Test link with a link to open it
- Execution date and time
- Created timestamp
- PDF file path
- Direct download link

## API Endpoints

### GET /api/get_reports.php

**Response Format:**
```json
{
    "success": true,
    "reports": [
        {
            "id": 1,
            "test_link": "https://www.example.com",
            "execution_date": "2026-03-15 10:30:00",
            "pdf_path": "reports/test_report_20260315_103000.pdf",
            "created_at": "2026-03-15 10:30:00"
        }
    ],
    "count": 1
}
```

**Error Response:**
```json
{
    "success": false,
    "error": "Error message describing what went wrong"
}
```

## Integration with Existing System

The View Reports module integrates seamlessly with your existing Test Configuration system:

1. **Test Execution Flow**:
   - User enters test links in Test Configuration page
   - Tests execute and generate PDF reports
   - Each test link creates an entry in `test_reports` table
   - PDF path is stored for download

2. **Database Connection**:
   - Uses existing `config/db.php` for MySQL connection
   - Maintains same credential structure

3. **Authentication**:
   - Uses existing session-based admin authentication
   - Session check required before accessing any reports

4. **Styling**:
   - Uses existing Bootstrap 5.3.0 stylesheet
   - Custom CSS in `assets/css/style.css`
   - Maintains consistent look and feel with dashboard

## Security Features

✅ **Session Protection** - Admin login required
✅ **SQL Injection Prevention** - Prepared statements used throughout
✅ **Path Traversal Prevention** - File path validation in download handler
✅ **Access Control** - Only authenticated admins can view/download reports
✅ **File Validation** - PDF existence and readability checks

## Customization

### Adding More Report Fields

Edit `api/get_reports.php` to include additional fields:
```php
// In the SELECT query
$query = "
    SELECT 
        id,
        test_link,
        execution_date,
        pdf_path,
        created_at,
        new_field_name  // Add here
    FROM test_reports
    ...
```

### Changing Sort Order

Edit `api/get_reports.php`:
```php
// Change from DESC (newest first) to ASC (oldest first)
ORDER BY execution_date ASC
```

### Limiting Results

Edit `api/get_reports.php`:
```php
// Change from 1000 to desired limit
LIMIT 500
```

### Styling Customization

Edit styles in:
- **View Reports Page**: Styles within `admin/view_reports.php`
- **Detail Page**: Styles within `admin/view_report_details.php`
- **Global Styles**: `assets/css/style.css`

## Troubleshooting

### "Report not found" error
- Verify the report ID exists in the database
- Check if the database connection is working
- Ensure admin session is active

### PDF download fails
- Verify the PDF file exists at the path stored in database
- Check file permissions on the reports directory
- Ensure the path in `pdf_path` column is correct

### API returns no reports
- Tables might be empty; use `insert_test_reports.php` to add sample data
- Check database connection credentials in `config/db.php`
- Verify `test_reports` table was created successfully

### Page not loading
- Ensure admin is logged in (session check)
- Verify `api/get_reports.php` is accessible
- Check browser console for JavaScript errors
- Ensure Bootstrap CSS is loading from CDN

## File Permissions

Ensure these directories have write permissions:
- `reports/` - Where PDF files are stored
- `api/` - Where query results are generated

```bash
chmod 755 reports/
chmod 755 api/
```

## Performance Notes

- API returns up to 1000 reports per request
- Reports are ordered by execution_date (newest first)
- Indexes on `execution_date` and `test_link` for fast queries
- Consider pagination for databases with >10,000 reports

## Future Enhancements

Possible additions to consider:
- Pagination for large datasets
- Search/filter by test link
- Date range filtering
- Bulk report deletion
- Report export (CSV, Excel)
- Email report notifications
- Report categorization by project
- Success/failure status tracking
- Test execution time metrics

## Support

If you encounter issues:
1. Check the browser console for JavaScript errors
2. Review PHP error logs in `logs/` directory
3. Verify database credentials and connectivity
4. Ensure all files were created in correct locations
5. Check that session is active and admin is logged in

## Next Steps

After setup is complete:
1. Run tests from Test Configuration page
2. Verify reports appear in View Reports
3. Test download functionality
4. Check detailed report pages
5. Customize styling as needed
6. Consider adding additional report metadata based on your needs
