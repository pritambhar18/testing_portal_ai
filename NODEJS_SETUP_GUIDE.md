# Node.js QA Testing Portal - Setup & Installation Guide

## 📋 Project Overview

This is a modern Node.js/Express backend for a QA Testing Portal that:
- Accepts POST requests to save Selenium test reports to MySQL
- Provides GET API to fetch all stored test reports
- Displays reports in a professional admin dashboard with table UI
- Uses connection pooling for optimal database performance
- Includes comprehensive error handling and validation

---

## 🚀 Quick Start

### Prerequisites
- **Node.js** 16.x or higher (download from https://nodejs.org)
- **MySQL** 5.7+ (already installed with XAMPP)
- **npm** (comes with Node.js)

### Installation Steps

#### 1. Navigate to Project Directory
```bash
cd c:\xampp\htdocs\testing_portal
```

#### 2. Install Dependencies
```bash
npm install
```

This will install:
- `express` - Web framework
- `mysql2` - MySQL database driver with async support
- `cors` - Cross-origin request handling
- `body-parser` - Request parsing middleware
- `dotenv` - Environment variable management

#### 3. Create Environment File (Optional)
Create a `.env` file in the project root if you need to override database credentials:

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=testing_portal
PORT=3000
NODE_ENV=development
```

**Note:** Default values are already set in `config/db.js`:
- Host: `localhost`
- User: `root`
- Password: (empty)
- Database: `testing_portal`
- Port: `3000`

#### 4. Start the Server

**Development Mode (with auto-reload):**
```bash
npm run dev
```

**Production Mode:**
```bash
npm start
```

The server will start on `http://localhost:3000`

---

## 📊 File Structure

```
testing_portal/
├── server.js                    # Express app entry point
├── package.json                 # Project dependencies
├── config/
│   └── db.js                   # MySQL connection pool & query helper
├── api/
│   ├── save-test-report.js     # POST endpoint to save reports
│   └── get-test-reports.js     # GET endpoint to fetch reports
├── frontend/
│   └── view-reports.html       # Admin dashboard with report table
└── reports/                     # Storage for PDF reports
    └── generated/              # Auto-generated HTML reports
```

---

## 🔌 API Endpoints

### Health Check
**Endpoint:** `GET /api/health`
**Purpose:** Verify server is running

**Response:**
```json
{
  "status": "OK",
  "timestamp": "2026-03-15T10:30:45.123Z"
}
```

### Save Test Report
**Endpoint:** `POST /api/save-test-report`
**Purpose:** Save a test report to the database

**Request Body:**
```json
{
  "test_link": "https://example.com/login",
  "execution_date": "2026-03-15T10:30:00",
  "pdf_path": "reports/test_report_20260315_103000.html"
}
```

**Required Fields:**
- `test_link` (string, valid URL) - The test script/URL that was executed
- `execution_date` (string, ISO 8601 format) - When the test was executed
- `pdf_path` (string, non-empty) - Path to the generated report file

**Response (Success - 201 Created):**
```json
{
  "success": true,
  "message": "Test report saved successfully",
  "report_id": 5,
  "data": {
    "id": 5,
    "test_link": "https://example.com/login",
    "execution_date": "2026-03-15T10:30:00",
    "pdf_path": "reports/test_report_20260315_103000.html",
    "created_at": "2026-03-15T10:35:20.456Z"
  }
}
```

**Response (Validation Error - 400 Bad Request):**
```json
{
  "error": "Missing required fields: test_link, execution_date, pdf_path",
  "received": { "test_link": "..." }
}
```

### Fetch Test Reports
**Endpoint:** `GET /api/get-test-reports`
**Purpose:** Retrieve all test reports from the database

**Response (Success - 200 OK):**
```json
{
  "success": true,
  "message": "Reports retrieved successfully",
  "count": 3,
  "data": [
    {
      "id": 3,
      "test_link": "https://example.com/checkout",
      "execution_date": "2026-03-15T12:00:00.000Z",
      "pdf_path": "reports/test_report_20260315_120000.html",
      "created_at": "2026-03-15T12:05:30.000Z"
    },
    {
      "id": 2,
      "test_link": "https://example.com/payment",
      "execution_date": "2026-03-15T11:30:00.000Z",
      "pdf_path": "reports/test_report_20260315_113000.html",
      "created_at": "2026-03-15T11:35:20.000Z"
    },
    {
      "id": 1,
      "test_link": "https://example.com/login",
      "execution_date": "2026-03-15T11:00:00.000Z",
      "pdf_path": "reports/test_report_20260315_110000.html",
      "created_at": "2026-03-15T11:05:10.000Z"
    }
  ]
}
```

---

## 🧪 Testing with cURL

### Test 1: Health Check
Verify the server is running:
```bash
curl http://localhost:3000/api/health
```

### Test 2: Save a Test Report (POST)
```bash
curl -X POST http://localhost:3000/api/save-test-report \
  -H "Content-Type: application/json" \
  -d "{
    \"test_link\": \"https://example.com/login\",
    \"execution_date\": \"2026-03-15T10:30:00\",
    \"pdf_path\": \"reports/test_report_20260315_103000.html\"
  }"
```

### Test 3: Fetch All Reports (GET)
```bash
curl http://localhost:3000/api/get-test-reports
```

### Test 4: View Admin Dashboard
Open in browser:
```
http://localhost:3000/view-reports
```

---

## 🐛 Common Issues & Troubleshooting

### Issue: "Cannot find module 'express'"
**Solution:** Run `npm install` first to install all dependencies

### Issue: "Error: connect ECONNREFUSED 127.0.0.1:3306"
**Solution:** 
- Ensure MySQL is running (start XAMPP MySQL service)
- Check database host/credentials in config/db.js
- Verify database `testing_portal` exists

### Issue: "POST request returns 400 Bad Request"
**Solution:**
- Verify request body is valid JSON
- Check all required fields are present: `test_link`, `execution_date`, `pdf_path`
- Ensure `test_link` is a valid URL (starts with http:// or https://)
- Ensure `execution_date` is in ISO 8601 format (YYYY-MM-DDTHH:MM:SS)

### Issue: "Table test_reports doesn't exist"
**Solution:**
- Create the table by running:
```sql
CREATE TABLE test_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_link TEXT NOT NULL,
  execution_date DATETIME NOT NULL,
  pdf_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Issue: "Port 3000 already in use"
**Solution:**
- Change PORT in `.env` file to different port (e.g., 3001, 8000)
- Or kill the process using port 3000

---

## 📱 Frontend Dashboard

### Access the Reports Dashboard
Navigate to: `http://localhost:3000/view-reports`

### Features
- ✅ Display all test reports in a professional table
- ✅ 5 columns: ID, Test Link, Execution Date, Report PDF, Created At
- ✅ Clickable report PDF download buttons
- ✅ Auto-refresh every 30 seconds
- ✅ Loading spinner while fetching
- ✅ Empty state when no reports
- ✅ Responsive design for mobile
- ✅ Color-coded alerts for success/error messages

---

## 🔐 Database Configuration

### Database Schema

```sql
CREATE TABLE test_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_link TEXT NOT NULL COMMENT 'URL of the test script',
  execution_date DATETIME NOT NULL COMMENT 'When the test was executed',
  pdf_path VARCHAR(255) NOT NULL COMMENT 'Path to the report file',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When record was created'
);
```

### Connection Pool Settings
- **Max Connections:** 10 (prevents resource exhaustion)
- **Connection Timeout:** Default (30 seconds)
- **Keep-Alive:** Enabled
- **Lazy Connection:** Connections created on first use

---

## 🚨 Security Practices Implemented

1. ✅ **SQL Injection Prevention** - All queries use parameterized statements with `?` placeholders
2. ✅ **Input Validation** - All API inputs are validated before database operations
3. ✅ **CORS Support** - Configure in server.js to allow specific origins
4. ✅ **Error Messages** - Production mode hides detailed error information
5. ✅ **Body Size Limits** - JSON payload limited to 10MB to prevent abuse

---

## 📝 Logging

The server includes comprehensive request logging:
- Every API request is logged with timestamp
- Format: `[HH:MM:SS] METHOD /path`
- Useful for debugging and monitoring

View logs in the terminal where you started the server.

---

## 🔄 Integration with Selenium Tests

To save reports from your Selenium test runner:

```javascript
// After test completes, make POST request to save report
const reportData = {
  test_link: "https://example.com/login",
  execution_date: new Date().toISOString(),
  pdf_path: "reports/test_report_20260315_103000.html"
};

fetch('http://localhost:3000/api/save-test-report', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(reportData)
})
.then(r => r.json())
.then(data => console.log('Report saved:', data.report_id))
.catch(err => console.error('Error saving report:', err));
```

---

## 📈 Performance Optimization

### Connection Pooling
- 10 concurrent database connections prevent connection exhaustion
- Connections are reused across requests
- Reduces connection overhead

### Request Compression
- Configure nginx/Apache to enable gzip compression
- Reduces response payload size by 70-80%

### Caching Strategy
- Frontend caches reports locally (JavaScript)
- Use HTTP caching headers for static files
- Consider Redis for frequently accessed data

---

## 🛠️ Development vs Production

### Development Mode
```bash
npm run dev
```
- Auto-restart on file changes
- Verbose error messages
- Full error details in API responses

### Production Mode
```bash
npm start
```
- Single process, no auto-restart
- Minimal error messages (security)
- Monitor with PM2 for process management:
```bash
npm install -g pm2
pm2 start server.js --name "testing-portal"
pm2 logs testing-portal
```

---

## 📚 Additional Resources

- [Express.js Documentation](https://expressjs.com/)
- [MySQL2 Documentation](https://github.com/sidorares/node-mysql2)
- [Node.js Best Practices](https://nodejs.org/en/docs/guides/nodejs-performance/)

---

## ✅ Checklist Before Going Live

- [ ] MySQL server is running
- [ ] Database `testing_portal` is created
- [ ] Table `test_reports` exists with correct schema
- [ ] `npm install` completed without errors
- [ ] `.env` file configured (or using defaults)
- [ ] Server starts with `npm start` without errors
- [ ] Health check endpoint returns 200: `curl http://localhost:3000/api/health`
- [ ] POST API accepts test reports: Test with cURL above
- [ ] GET API returns saved reports: Test with cURL above
- [ ] Dashboard is accessible: `http://localhost:3000/view-reports`
- [ ] Reports display correctly in the table
- [ ] Selenium tests can connect and save reports

---

**Last Updated:** March 15, 2026
**Version:** 1.0
