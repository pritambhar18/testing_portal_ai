/**
 * server.js
 * Express.js Server for QA Testing Portal
 * Runs on port 3000 with proper middleware setup
 */

import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { saveTestReport } from './api/save-test-report.js';
import { getTestReports } from './api/get-test-reports.js';

const app = express();
const PORT = process.env.PORT || 3000;

// Get __dirname equivalent in ES modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// ============================================
// MIDDLEWARE SETUP
// ============================================

// Enable CORS
app.use(cors({
  origin: '*',
  methods: ['GET', 'POST', 'PUT', 'DELETE'],
  allowedHeaders: ['Content-Type', 'Authorization'],
}));

// Body parsing middleware
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ limit: '10mb', extended: true }));
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Request logging middleware
app.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.path}`);
  next();
});

// ============================================
// STATIC FILES
// ============================================

// Serve static files (HTML, CSS, JS, etc.)
app.use(express.static(join(__dirname, 'frontend')));
app.use('/reports', express.static(join(__dirname, 'reports')));

// ============================================
// API ROUTES
// ============================================

/**
 * Health check endpoint
 */
app.get('/api/health', (req, res) => {
  res.status(200).json({
    success: true,
    message: 'Server is running',
    timestamp: new Date().toISOString(),
    environment: process.env.NODE_ENV || 'development',
  });
});

/**
 * POST /api/save-test-report
 * Save a new test report
 */
app.post('/api/save-test-report', saveTestReport);

/**
 * GET /api/get-test-reports
 * Fetch all test reports
 */
app.get('/api/get-test-reports', getTestReports);

// ============================================
// FRONTEND ROUTES
// ============================================

/**
 * Serve frontend pages
 */
app.get('/', (req, res) => {
  res.sendFile(join(__dirname, 'frontend', 'index.html'));
});

app.get('/view-reports', (req, res) => {
  res.sendFile(join(__dirname, 'frontend', 'view-reports.html'));
});

// ============================================
// ERROR HANDLING
// ============================================

/**
 * 404 Not Found handler
 */
app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'Route not found',
    path: req.path,
    method: req.method,
  });
});

/**
 * Global error handler
 */
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(500).json({
    success: false,
    error: 'Internal server error',
    message: process.env.NODE_ENV === 'development' ? err.message : undefined,
  });
});

// ============================================
// SERVER STARTUP
// ============================================

app.listen(PORT, () => {
  console.log('');
  console.log('╔════════════════════════════════════════════════════╗');
  console.log('║     QA Testing Portal - Express Server Started      ║');
  console.log('╠════════════════════════════════════════════════════╣');
  console.log(`║ 🚀 Server running on http://localhost:${PORT}${' '.repeat(10-PORT.toString().length)}║`);
  console.log('║ 📝 API Documentation:                              ║');
  console.log('║   POST   /api/save-test-report   - Save report      ║');
  console.log('║   GET    /api/get-test-reports   - Fetch reports    ║');
  console.log('║ 🌐 Frontend:                                        ║');
  console.log('║   GET    /view-reports           - View reports UI  ║');
  console.log('║   GET    /api/health             - Health check     ║');
  console.log('║ 💾 Database:                                        ║');
  console.log('║   Host:  localhost               Table: test_reports ║');
  console.log('╚════════════════════════════════════════════════════╝');
  console.log('');
});

export default app;
