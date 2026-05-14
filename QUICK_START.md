# View Reports Module - Quick Start Guide

## ⚡ Quick Setup (5 minutes)

### 1. Create Database Table
```
Go to: http://localhost/xampp/htdocs/testing_portal/setup/create_test_reports_table.php
```
Click the link and verify: "✓ test_reports table created successfully!"

### 2. (Optional) Add Sample Data
```
Go to: http://localhost/xampp/htdocs/testing_portal/setup/insert_test_reports.php
```
This adds 5 sample reports for testing.

### 3. Access View Reports
1. Login to Admin Dashboard
2. Click **"View Reports"** in the sidebar
3. You should see your reports in a table

## 📁 File Structure

```
/admin/
  ├── view_reports.php              ← Main reports page
  ├── view_report_details.php        ← Individual report details
  └── sidebar.php                    ← Updated with View Reports link

/api/
  └── get_reports.php               ← JSON API endpoint

/actions/
  └── download_report.php           ← PDF download handler

/helpers/
  └── TestReportLogger.php           ← Integration helper class

/setup/
  ├── create_test_reports_table.php  ← Database migration
  └── insert_test_reports.php        ← Sample data

/
  ├── VIEW_REPORTS_SETUP.md          ← Full setup guide
  └── INTEGRATION_GUIDE.md           ← Developer integration guide
```

## 🔗 Integration with Your Test System

Add these 3 lines to your test execution code:

```php
require_once __DIR__ . '/../helpers/TestReportLogger.php';
$reporter = new TestReportLogger($conn);
$report_id = $reporter->logTestReport($test_link, $pdf_path);
```

**That's it!** Your reports will automatically appear in the View Reports page.

## ✨ Features

| Feature | Status |
|---------|--------|
| View all test reports | ✅ |
| Real-time data loading | ✅ |
| Report details page | ✅ |
| PDF download | ✅ |
| Responsive mobile design | ✅ |
| Admin session protection | ✅ |
| SQL injection prevention | ✅ |
| Error handling | ✅ |

## 📊 Database Schema

```sql
CREATE TABLE test_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_link VARCHAR(500) NOT NULL,
    execution_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    pdf_path VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_execution_date (execution_date),
    INDEX idx_test_link (test_link)
)
```

## 🎯 API Endpoint

**GET** `/api/get_reports.php`

Response:
```json
{
    "success": true,
    "reports": [
        {
            "id": 1,
            "test_link": "https://example.com",
            "execution_date": "2026-03-15 10:30:00",
            "pdf_path": "reports/test_report_001.pdf",
            "created_at": "2026-03-15 10:30:00"
        }
    ],
    "count": 1
}
```

## 🚀 Usage

1. **View Reports**: Click "View Reports" from sidebar
2. **View Details**: Click "View" button for detailed information
3. **Download PDF**: Click "Download" button to get the PDF file

## 🔐 Security

✅ Admin session required
✅ Prepared SQL statements
✅ File path validation
✅ Directory traversal prevention
✅ User input sanitization

## 📱 Responsive Design

- ✅ Desktop (1920px+)
- ✅ Tablet (768px - 1024px)
- ✅ Mobile (< 768px)
- ✅ Offcanvas sidebar on mobile

## ❓ Common Questions

**Q: How do I connect my test system to this module?**
A: Use the `TestReportLogger` class - see INTEGRATION_GUIDE.md

**Q: Where are PDF files stored?**
A: In the `reports/` directory, path stored in `pdf_path` column

**Q: Can I customize the columns?**
A: Yes, edit the API and UI pages - see INTEGRATION_GUIDE.md

**Q: How many reports can it handle?**
A: Optimized for up to 10,000 reports; consider pagination beyond that

**Q: Is it secure?**
A: Yes - prepared statements, session protection, file validation

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| Table not created | Run `setup/create_test_reports_table.php` |
| No reports showing | Check if data inserted and API working |
| PDF won't download | Verify file exists at `pdf_path` |
| Page not loading | Check admin is logged in |
| API error | Check database connection credentials |

## 📞 Need Help?

1. Check **VIEW_REPORTS_SETUP.md** for detailed setup
2. Check **INTEGRATION_GUIDE.md** for integration code examples
3. Review error logs in browser console
4. Verify database connection in `config/db.php`

## 🎓 Next Steps

1. ✅ Setup database table
2. ✅ Test with sample data
3. ✅ Integrate with your test system
4. ✅ Customize styling if needed
5. ✅ Deploy to production

---

**Happy Testing! 🎉**
