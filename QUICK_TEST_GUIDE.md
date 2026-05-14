# 🚀 Quick Testing Guide - Node.js QA Portal

## Step 1: Start the Server

```bash
cd c:\xampp\htdocs\testing_portal
npm install          # Only needed first time
npm start            # Or use: npm run dev
```

Expected output:
```
[timestamp] Server listening on port 3000
[timestamp] Available endpoints:
  • GET  /api/health
  • POST /api/save-test-report
  • GET  /api/get-test-reports
  • GET  /view-reports
```

---

## Step 2: Test Health Endpoint

In PowerShell:
```powershell
Invoke-WebRequest -Uri "http://localhost:3000/api/health"
```

Expected response:
```
Status: 200
Body:
{
  "status": "OK",
  "timestamp": "2026-03-15T10:30:45.123Z"
}
```

---

## Step 3: Test Save Report (POST)

In PowerShell:
```powershell
$body = @{
    test_link = "https://example.com/login"
    execution_date = "2026-03-15T10:30:00"
    pdf_path = "reports/test_report_20260315_103000.html"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
    -Method POST `
    -Headers @{"Content-Type"="application/json"} `
    -Body $body
```

Expected response (201 Created):
```json
{
  "success": true,
  "message": "Test report saved successfully",
  "report_id": 1,
  "data": {
    "id": 1,
    "test_link": "https://example.com/login",
    "execution_date": "2026-03-15T10:30:00",
    "pdf_path": "reports/test_report_20260315_103000.html",
    "created_at": "2026-03-15T10:35:20.456Z"
  }
}
```

---

## Step 4: Test Get Reports (GET)

In PowerShell:
```powershell
Invoke-WebRequest -Uri "http://localhost:3000/api/get-test-reports" | Select-Object -ExpandProperty Content | ConvertFrom-Json
```

Or in browser:
```
http://localhost:3000/api/get-test-reports
```

Expected response (200 OK):
```json
{
  "success": true,
  "message": "Reports retrieved successfully",
  "count": 1,
  "data": [
    {
      "id": 1,
      "test_link": "https://example.com/login",
      "execution_date": "2026-03-15T10:30:00",
      "pdf_path": "reports/test_report_20260315_103000.html",
      "created_at": "2026-03-15T10:35:20.456Z"
    }
  ]
}
```

---

## Step 5: View Dashboard

Open in browser:
```
http://localhost:3000/view-reports
```

You should see:
- A professional dashboard with navigation bar
- A table with 5 columns: ID, Test Link, Execution Date, Report PDF, Created At
- Your saved report(s) displayed in the table
- A "View Report" button for each report
- Auto-refresh every 30 seconds

---

## Validation Scenarios

### Scenario 1: Missing Required Field
```powershell
$body = @{
    test_link = "https://example.com"
    # missing execution_date and pdf_path
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
    -Method POST `
    -Headers @{"Content-Type"="application/json"} `
    -Body $body
```

Expected response (400 Bad Request):
```json
{
  "error": "Missing required fields: execution_date, pdf_path"
}
```

---

### Scenario 2: Invalid URL Format
```powershell
$body = @{
    test_link = "not-a-valid-url"
    execution_date = "2026-03-15T10:30:00"
    pdf_path = "reports/report.html"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
    -Method POST `
    -Headers @{"Content-Type"="application/json"} `
    -Body $body
```

Expected response (400 Bad Request):
```json
{
  "error": "Invalid test_link format. Must be a valid URL"
}
```

---

### Scenario 3: Invalid DateTime Format
```powershell
$body = @{
    test_link = "https://example.com"
    execution_date = "15/03/2026"  # Wrong format
    pdf_path = "reports/report.html"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
    -Method POST `
    -Headers @{"Content-Type"="application/json"} `
    -Body $body
```

Expected response (400 Bad Request):
```json
{
  "error": "Invalid execution_date format. Use ISO 8601: YYYY-MM-DDTHH:MM:SS"
}
```

---

### Scenario 4: Empty PDF Path
```powershell
$body = @{
    test_link = "https://example.com"
    execution_date = "2026-03-15T10:30:00"
    pdf_path = ""  # Empty not allowed
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
    -Method POST `
    -Headers @{"Content-Type"="application/json"} `
    -Body $body
```

Expected response (400 Bad Request):
```json
{
  "error": "pdf_path cannot be empty"
}
```

---

## Bulk Test (Multiple Reports)

Save 3 reports quickly:

```powershell
$timestamps = @(
    "2026-03-15T08:00:00",
    "2026-03-15T08:30:00", 
    "2026-03-15T09:00:00"
)

$i = 1
foreach ($ts in $timestamps) {
    $body = @{
        test_link = "https://example.com/test$i"
        execution_date = $ts
        pdf_path = "reports/test_report_$i.html"
    } | ConvertTo-Json
    
    $response = Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
        -Method POST `
        -Headers @{"Content-Type"="application/json"} `
        -Body $body
    
    Write-Host "Report $i saved:"
    $response.Content | ConvertFrom-Json
    Write-Host ""
    
    $i++
}
```

Then fetch all:
```powershell
Invoke-WebRequest -Uri "http://localhost:3000/api/get-test-reports" | Select-Object -ExpandProperty Content | ConvertFrom-Json | Select-Object -ExpandProperty data | Format-Table
```

---

## Complete End-to-End Test Script

Save as `test-api.ps1`:

```powershell
# Colors for output
$colors = @{
    Green = 'Green'
    Red = 'Red'
    Yellow = 'Yellow'
    Cyan = 'Cyan'
}

function Write-Section ($title) {
    Write-Host "`n" -ForegroundColor $colors.Cyan
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor $colors.Cyan
    Write-Host " $title" -ForegroundColor $colors.Cyan
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor $colors.Cyan
}

function Write-Success ($msg) {
    Write-Host "✓ $msg" -ForegroundColor $colors.Green
}

function Write-Error-Msg ($msg) {
    Write-Host "✗ $msg" -ForegroundColor $colors.Red
}

Write-Section "1. Testing Health Endpoint"
try {
    $response = Invoke-WebRequest -Uri "http://localhost:3000/api/health"
    Write-Success "Health check passed (Status: $($response.StatusCode))"
} catch {
    Write-Error-Msg "Health check failed: $_"
    exit
}

Write-Section "2. Testing Save Report (POST)"
$body = @{
    test_link = "https://example.com/login"
    execution_date = "2026-03-15T10:30:00"
    pdf_path = "reports/test_report.html"
} | ConvertTo-Json

try {
    $response = Invoke-WebRequest -Uri "http://localhost:3000/api/save-test-report" `
        -Method POST `
        -Headers @{"Content-Type"="application/json"} `
        -Body $body
    
    $data = $response.Content | ConvertFrom-Json
    Write-Success "Report saved successfully (ID: $($data.report_id))"
} catch {
    Write-Error-Msg "Failed to save report: $_"
}

Write-Section "3. Testing Get Reports (GET)"
try {
    $response = Invoke-WebRequest -Uri "http://localhost:3000/api/get-test-reports"
    $data = $response.Content | ConvertFrom-Json
    Write-Success "Retrieved $($data.count) report(s) successfully"
    
    if ($data.data) {
        Write-Host "`nReports:" -ForegroundColor $colors.Yellow
        $data.data | Format-Table -Property id, test_link, execution_date
    }
} catch {
    Write-Error-Msg "Failed to get reports: $_"
}

Write-Section "4. Testing Dashboard"
Write-Host "Open in browser: http://localhost:3000/view-reports" -ForegroundColor $colors.Yellow

Write-Section "✓ All tests completed!"
```

Run it:
```powershell
powershell -ExecutionPolicy Bypass -File test-api.ps1
```

---

## Troubleshooting Test Failures

| Error | Cause | Solution |
|-------|-------|----------|
| Connection refused | Server not running | Run `npm start` first |
| 404 Not Found | Wrong endpoint | Check spelling of `/api/save-test-report` |
| 400 Bad Request | Invalid JSON or missing fields | Verify request body format and all required fields |
| 500 Server Error | Database connection failed | Check MySQL is running, database exists |
| Invalid URL error | test_link not valid | Must start with http:// or https:// |
| Invalid DateTime error | Wrong date format | Use ISO 8601: YYYY-MM-DDTHH:MM:SS |

---

## Next Steps

Once all tests pass:
1. ✅ Server is working correctly
2. ✅ Database connection is established
3. ✅ APIs are accepting and storing data
4. ✅ Frontend dashboard displays reports
5. 🔄 Configure your Selenium test runner to POST to `/api/save-test-report`
6. 📊 Monitor reports in real-time at `/view-reports`

---

**Need Help?** Check `NODEJS_SETUP_GUIDE.md` for detailed documentation.
