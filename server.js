import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import { spawn } from 'child_process';
import crypto from 'crypto';
import fs from 'fs/promises';
import { existsSync } from 'fs';
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import { dirname, join, resolve, basename } from 'path';
import { saveTestReport } from './api/save-test-report.js';
import { getTestReports } from './api/get-test-reports.js';
import { login, requireAuth } from './api/auth.js';
import { ensurePortalSchema, query } from './config/db.js';

const app = express();
const PORT = process.env.PORT || 3000;
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const frontendPath = join(__dirname, 'frontend');
const uploadsPath = join(__dirname, 'uploads');
const orderReportsPath = join(uploadsPath, 'order_flow_reports');
const quickReportsPath = join(__dirname, 'automation', 'results');
const orderRunnerPath = join(__dirname, 'order_placement', 'run-order-flow.mjs');
const quickRunnerPath = join(__dirname, 'automation', 'form-functional-checks.mjs');
const locatorsPath = join(__dirname, 'order_placement', 'locators.json');
const activeRuns = new Map();
const runProcesses = new Map();
const startingRunTypes = new Set();

app.use(cors({
  origin: process.env.CORS_ORIGIN || '*',
  methods: ['GET', 'POST', 'PUT', 'DELETE'],
  allowedHeaders: ['Content-Type', 'Authorization'],
}));
app.use(express.json({ limit: '25mb' }));
app.use(express.urlencoded({ limit: '25mb', extended: true }));

app.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.path}`);
  next();
});

app.use(express.static(frontendPath));
app.use('/reports', express.static(join(__dirname, 'reports')));
app.use('/uploads', express.static(uploadsPath));
app.use('/automation/results', express.static(quickReportsPath));

function publicPathFromAbsolute(filePath) {
  const normalized = filePath.replace(/\\/g, '/');
  const root = __dirname.replace(/\\/g, '/');
  return normalized.startsWith(root)
    ? normalized.slice(root.length).replace(/^\/?/, '/')
    : filePath;
}

function timestampForFile(date = new Date()) {
  return date
    .toISOString()
    .replace(/[-:]/g, '')
    .replace('T', '_')
    .replace(/\..+$/, '');
}

function createRunId(prefix) {
  return `${prefix}_${timestampForFile()}_${crypto.randomBytes(4).toString('hex')}`;
}

function startNodeScript(scriptPath, args, onClose) {
  const child = spawn(process.execPath, [scriptPath, ...args], {
    cwd: __dirname,
    env: process.env,
    windowsHide: true,
  });

  let stdout = '';
  let stderr = '';

  child.stdout.on('data', (chunk) => {
    stdout += chunk.toString();
  });

  child.stderr.on('data', (chunk) => {
    stderr += chunk.toString();
  });

  child.on('close', (code) => {
    onClose({ code, stdout, stderr });
  });

  return child;
}

function parseLastJson(output) {
  const matches = String(output).match(/\{[\s\S]*\}/g);
  if (!matches || matches.length === 0) {
    return null;
  }

  try {
    return JSON.parse(matches[matches.length - 1]);
  } catch {
    return null;
  }
}

async function createAutomationLog(payload) {
  try {
    const result = await query(
      `INSERT INTO automation_logs
        (run_type, status, input_url, report_id, report_path, log_path, message, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        payload.runType,
        payload.status || 'Running',
        payload.inputUrl || null,
        payload.reportId || null,
        payload.reportPath || null,
        payload.logPath || null,
        payload.message || null,
        payload.createdBy || null,
      ],
    );
    return result.insertId;
  } catch (error) {
    console.warn('Automation log insert skipped:', error.message);
    return null;
  }
}

async function updateAutomationLog(id, payload) {
  if (!id) {
    return;
  }

  try {
    await query(
      `UPDATE automation_logs
       SET status = ?, report_id = ?, report_path = ?, log_path = ?, message = ?, finished_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [
        payload.status,
        payload.reportId || null,
        payload.reportPath || null,
        payload.logPath || null,
        payload.message || null,
        id,
      ],
    );
  } catch (error) {
    console.warn('Automation log update skipped:', error.message);
  }
}

async function saveReportRecord({ testLink, pdfPath, htmlPath, status, reportType, runId, offerName, browserName, passCount, failCount }) {
  try {
    const result = await query(
      `INSERT INTO test_reports (test_link, execution_date, pdf_path, report_html, report_type, run_id, offer_name, browser_name, pass_count, fail_count, status)
       VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        testLink,
        pdfPath || null,
        htmlPath || null,
        reportType || null,
        runId || null,
        offerName || null,
        browserName || null,
        passCount !== undefined ? parseInt(passCount, 10) : 0,
        failCount !== undefined ? parseInt(failCount, 10) : 0,
        status || 'Completed',
      ],
    );
    return result.insertId;
  } catch (error) {
    console.warn('Report DB insert skipped:', error.message);
    return null;
  }
}

function toPublicPath(filePath) {
  return publicPathFromAbsolute(filePath).replace(/\\/g, '/');
}

function canonicalPublicPath(publicPath) {
  const normalized = String(publicPath || '').replace(/\\/g, '/').replace(/^(\.\.\/)+/, '/');
  if (!normalized) {
    return '';
  }
  return normalized.startsWith('/') ? normalized : `/${normalized}`;
}

function artifactExists(publicPath) {
  const artifact = resolvePublicArtifact(publicPath);
  return Boolean(artifact && existsSync(artifact));
}

function publicPathForResolvedArtifact(absolutePath) {
  if (!absolutePath) {
    return '';
  }
  return toPublicPath(absolutePath);
}

function pdfPublicPathFromHtml(htmlPublicPath) {
  const normalized = canonicalPublicPath(htmlPublicPath);
  return normalized && /\.html$/i.test(normalized)
    ? normalized.replace(/\.html$/i, '.pdf')
    : '';
}

function quickReportBaseFromReport(report) {
  const artifactPath = canonicalPublicPath(report.report_html || report.pdf_path || '');
  const quickMatch = artifactPath.match(/\/automation\/results\/([^/.]+)/);
  return quickMatch?.[1] ? basename(quickMatch[1]) : '';
}

async function rebuildQuickReportPdf(report) {
  const base = quickReportBaseFromReport(report);
  if (!base) {
    return '';
  }

  const jsonPath = join(quickReportsPath, `${base}.json`);
  const htmlPath = join(quickReportsPath, `${base}.html`);
  const pdfPath = join(quickReportsPath, `${base}.pdf`);
  if (!existsSync(jsonPath)) {
    return '';
  }

  let checks = [];
  try {
    checks = JSON.parse(await fs.readFile(jsonPath, 'utf8'));
  } catch {
    return '';
  }

  await fs.writeFile(htmlPath, buildQuickReportHtml({
    runId: base,
    baseUrl: report.test_link || '',
    checks: Array.isArray(checks) ? checks : [],
  }), 'utf8');

  const generated = await renderHtmlToPdf(htmlPath, pdfPath);
  return generated && existsSync(pdfPath) ? toPublicPath(pdfPath) : '';
}

async function ensurePdfArtifact(report) {
  const rebuiltQuickPdf = await rebuildQuickReportPdf(report);
  if (rebuiltQuickPdf) {
    return rebuiltQuickPdf;
  }

  const pdfPath = canonicalPublicPath(report.pdf_path || '');
  if (pdfPath && /\.pdf$/i.test(pdfPath) && artifactExists(pdfPath)) {
    return pdfPath;
  }

  const htmlPath = canonicalPublicPath(report.report_html || '');
  const absoluteHtml = resolvePublicArtifact(htmlPath);
  if (!htmlPath || !absoluteHtml || !existsSync(absoluteHtml)) {
    return '';
  }

  const targetPublicPath = pdfPublicPathFromHtml(htmlPath);
  const absolutePdf = resolvePublicArtifact(targetPublicPath);
  if (!targetPublicPath || !absolutePdf) {
    return '';
  }

  if (existsSync(absolutePdf)) {
    return targetPublicPath;
  }

  const generated = await renderHtmlToPdf(absoluteHtml, absolutePdf);
  return generated && existsSync(absolutePdf) ? targetPublicPath : '';
}

async function resolveReportArtifact(report, preferred = 'view') {
  if (preferred === 'view' || preferred === 'download') {
    const pdfArtifact = await ensurePdfArtifact(report);
    if (pdfArtifact) {
      return pdfArtifact;
    }
  }

  const candidates = preferred === 'download'
    ? [report.pdf_path, report.report_html]
    : [report.pdf_path, report.report_html];

  for (const candidate of candidates.map(canonicalPublicPath).filter(Boolean)) {
    if (artifactExists(candidate)) {
      return candidate;
    }
  }

  const artifactPath = canonicalPublicPath(report.report_html || report.pdf_path || '');
  const orderMatch = artifactPath.match(/\/uploads\/order_flow_reports\/([^/]+)/);
  if (orderMatch?.[1]) {
    const base = `/uploads/order_flow_reports/${basename(orderMatch[1])}`;
    const fallbackCandidates = preferred === 'download' || preferred === 'view'
      ? [`${base}/report.pdf`, `${base}/report.html`]
      : [`${base}/report.html`, `${base}/report.pdf`];
    return fallbackCandidates.find(artifactExists) || '';
  }

  const quickMatch = artifactPath.match(/\/automation\/results\/([^/.]+)/);
  if (quickMatch?.[1]) {
    const base = `/automation/results/${basename(quickMatch[1])}`;
    const fallbackCandidates = preferred === 'download' || preferred === 'view'
      ? [`${base}.pdf`, `${base}.html`, `${base}.json`]
      : [`${base}.html`, `${base}.pdf`, `${base}.json`];
    return fallbackCandidates.find(artifactExists) || '';
  }

  return '';
}

async function renderHtmlToPdf(htmlPath, pdfPath) {
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto(`file://${htmlPath.replace(/\\/g, '/')}`, { waitUntil: 'networkidle' });
    await page.pdf({
      path: pdfPath,
      format: 'A4',
      printBackground: true,
      margin: { top: '16mm', right: '12mm', bottom: '16mm', left: '12mm' },
    });
    return true;
  } catch (error) {
    console.warn('PDF generation skipped:', error.message);
    return false;
  } finally {
    if (browser) {
      await browser.close().catch(() => {});
    }
  }
}

function htmlEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function describeQuickStep(check, baseUrl) {
  const label = String(check.label || 'Validation check');
  return `Open the browser, navigate to ${baseUrl}, locate the relevant form or page element, run the "${label}" validation, capture the observed page state, and compare it with the expected behavior.`;
}

function describeQuickResult(check) {
  const status = String(check.status || '').toUpperCase();
  const detail = String(check.detail || 'No additional detail was returned by the runner.');
  if (status === 'PASS') {
    return `The validation passed. The page behavior matched the expected rule. Runner detail: ${detail}`;
  }
  return `The validation failed or needs attention. Expected behavior: the page should show clear validation, enforce the field rule, or expose the required form element. Actual runner detail: ${detail}`;
}

function classifyQuickIssue(check) {
  const label = String(check.label || '').toLowerCase();
  const detail = String(check.detail || '').toLowerCase();
  if (/card|cvv|payment|checkout|submit|order|security/.test(`${label} ${detail}`)) {
    return { severity: 'High', priority: 'P1' };
  }
  if (/required|blank|phone|zip|email|validation|form/.test(`${label} ${detail}`)) {
    return { severity: 'Medium', priority: 'P2' };
  }
  return { severity: 'Low', priority: 'P3' };
}

function quickScreenshotSrc(check) {
  if (!check?.screenshot_path) {
    return '';
  }

  const absolutePath = resolve(String(check.screenshot_path));
  const relativeToQuickReports = absolutePath.startsWith(quickReportsPath)
    ? absolutePath.slice(quickReportsPath.length).replace(/^[\\/]+/, '').replace(/\\/g, '/')
    : '';

  return relativeToQuickReports || publicPathForResolvedArtifact(absolutePath);
}

function buildFailureReportHtml({ title, runId, baseUrl, message }) {
  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>${htmlEscape(title)} - ${htmlEscape(runId)}</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:28px;color:#111827;background:#fff;}
    h1{font-size:24px;margin:0 0 10px;}
    .meta{color:#667085;margin:0 0 18px;line-height:1.5;}
    .issue{border:1px solid #fecdca;background:#fff7f6;border-radius:8px;padding:14px;}
    .issue h2{font-size:16px;color:#b42318;margin:0 0 8px;}
    .issue p{margin:8px 0;line-height:1.5;}
  </style>
</head>
<body>
  <h1>${htmlEscape(title)}</h1>
  <div class="meta">Run ID: ${htmlEscape(runId)}<br>URL: ${htmlEscape(baseUrl)}<br>Generated: ${new Date().toLocaleString()}</div>
  <section class="issue">
    <h2>Automation did not complete</h2>
    <p><strong>Test step:</strong> Open the browser, navigate to ${htmlEscape(baseUrl)}, load the test flow, execute the configured automation, and generate the final report artifacts.</p>
    <p><strong>Test description:</strong> The runner stopped or failed before a full report could be generated. Actual runner detail: ${htmlEscape(message || 'No runner detail was returned.')}</p>
  </section>
</body>
</html>`;
}

function buildQuickReportHtml({ runId, baseUrl, checks }) {
  const passedCount = checks.filter((item) => String(item.status).toUpperCase() === 'PASS').length;
  const failedCount = checks.filter((item) => String(item.status).toUpperCase() === 'FAIL').length;
  const reviewCount = Math.max(checks.length - passedCount - failedCount, 0);
  const issueRows = checks
    .filter((check) => String(check.status || '').toUpperCase() === 'FAIL')
    .map((check, index) => {
      const issue = classifyQuickIssue(check);
      const screenshot = quickScreenshotSrc(check);
      return `
      <section class="issue">
        <h3>${index + 1}. ${htmlEscape(check.label || 'Validation issue')} <span>Severity: ${issue.severity} | Priority: ${issue.priority}</span></h3>
        <p><strong>Test step:</strong></p>
        <ul class="step-list">
          <li>Open the browser and navigate to ${htmlEscape(baseUrl)}.</li>
          <li>Locate the relevant form or page element.</li>
          <li>Run the "${htmlEscape(check.label || 'Validation check')}" validation and capture the observed page state.</li>
          <li>Compare the result with the expected behavior.</li>
        </ul>
        <p><strong>Test description:</strong> ${htmlEscape(describeQuickResult(check))}</p>
        ${screenshot ? `<div class="screenshot-frame"><img class="report-screenshot" src="${htmlEscape(screenshot)}" alt=""></div>` : ''}
      </section>
    `;
    }).join('');
  const rows = checks.map((check) => {
    const screenshot = quickScreenshotSrc(check);
    return `
      <tr>
        <td><span class="badge ${String(check.status).toLowerCase()}">${htmlEscape(check.status)}</span></td>
        <td><strong>Test step:</strong> ${htmlEscape(describeQuickStep(check, baseUrl))}</td>
        <td><strong>Test description:</strong> ${htmlEscape(describeQuickResult(check))}</td>
      </tr>
      ${screenshot ? `<tr class="screenshot-row"><td colspan="3"><div class="screenshot-frame"><img class="report-screenshot" src="${htmlEscape(screenshot)}" alt=""></div></td></tr>` : ''}
    `;
  }).join('');

  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Quick Check Report - ${htmlEscape(runId)}</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:28px;color:#111827;background:#fff;line-height:1.45;}
    h1{font-size:26px;margin:0 0 6px;color:#0f172a;}
    h2{font-size:17px;margin:24px 0 10px;color:#0f172a;border-bottom:1px solid #d8dee9;padding-bottom:6px;}
    .cover{border:1px solid #d8dee9;border-left:5px solid #0f766e;border-radius:8px;padding:18px;margin-bottom:18px;}
    .meta{color:#667085;margin-top:8px;}
    .summary{display:flex;gap:12px;margin:18px 0;flex-wrap:wrap;}
    .card{border:1px solid #d8dee9;border-radius:8px;padding:12px;min-width:130px;background:#f8fafc;}
    .card span{display:block;color:#667085;font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.04em;}
    .card strong{font-size:24px;color:#111827;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{border-bottom:1px solid #d8dee9;padding:10px;text-align:left;vertical-align:top;}
    th{background:#f8fafc;color:#667085;text-transform:uppercase;font-size:11px;}
    .screenshot-row td{background:#fff;padding:12px 10px 18px;}
    .screenshot-frame{width:min(100%,560px);margin:8px auto 0;padding:8px;background:#fff;border:1px solid #d8dee9;border-radius:8px;box-sizing:border-box;page-break-inside:avoid;text-align:center;}
    .report-screenshot{display:inline-block;width:auto;height:auto;max-width:100%;max-height:300px;object-fit:contain;border:0;border-radius:4px;margin:0 auto;}
    .badge{border-radius:999px;padding:4px 8px;font-weight:700;background:#ecfdf3;color:#067647;}
    .badge.fail,.badge.failed{background:#fff1f0;color:#b42318;}
    .issue{border:1px solid #fecdca;background:#fff7f6;border-radius:8px;padding:14px;margin:12px 0;page-break-inside:avoid;}
    .issue h3{font-size:15px;margin:0 0 8px;color:#b42318;}
    .issue h3 span{display:block;color:#667085;font-size:12px;margin-top:3px;}
    .issue p{margin:6px 0;}
    .step-list{margin:6px 0 8px 18px;padding:0;}
    .step-list li{margin:4px 0;line-height:1.45;}
    .note{color:#475467;font-size:13px;margin:8px 0 0;}
  </style>
</head>
<body>
  <section class="cover">
    <h1>Quick Check Test Report</h1>
    <div class="meta">Run ID: ${htmlEscape(runId)}<br>Test URL: ${htmlEscape(baseUrl)}<br>Generated: ${new Date().toLocaleString()}<br>Prepared by: QA Testing Portal</div>
    <p class="note">This report summarizes automated form validation checks, observed behavior, and screenshot evidence for management review.</p>
  </section>
  <div class="summary">
    <div class="card"><span>Total</span><strong>${checks.length}</strong></div>
    <div class="card"><span>Passed</span><strong>${passedCount}</strong></div>
    <div class="card"><span>Failed</span><strong>${failedCount}</strong></div>
    <div class="card"><span>Review</span><strong>${reviewCount}</strong></div>
  </div>
  <h2>Executive Summary</h2>
  <p>${failedCount > 0 ? `The automation found ${failedCount} issue(s) that require review before release.` : 'No failing validation issues were found in this run.'}</p>
  <h2>Issue Register</h2>
  ${issueRows || '<p>No issues found.</p>'}
  <h2>Detailed Test Results</h2>
  <table>
    <thead><tr><th>Status</th><th>Test Step</th><th>Test Description</th></tr></thead>
    <tbody>${rows || '<tr><td colspan="3">No checks returned.</td></tr>'}</tbody>
  </table>
</body>
</html>`;
}

function setRun(runId, data) {
  activeRuns.set(runId, {
    run_id: runId,
    status: 'Running',
    started_at: new Date().toISOString(),
    ...data,
  });
}

function updateRun(runId, data) {
  const existing = activeRuns.get(runId) || { run_id: runId };
  activeRuns.set(runId, { ...existing, ...data });
}

function findRunningRun(type = '') {
  return Array.from(activeRuns.values()).find((run) => (
    (!type || run.type === type) && String(run.status || '').toLowerCase() === 'running'
  ));
}

function isRunTypeBusy(type) {
  return startingRunTypes.has(type) || Boolean(findRunningRun(type));
}

function resolvePublicArtifact(publicPath) {
  const normalized = String(publicPath || '').replace(/\\/g, '/').replace(/^(\.\.\/)+/, '/');
  if (!normalized.startsWith('/')) {
    return null;
  }

  const relativePath = normalized.replace(/^\/+/, '');
  const absolutePath = resolve(__dirname, relativePath);
  const allowedRoots = [
    uploadsPath,
    quickReportsPath,
    join(__dirname, 'reports'),
  ];

  return allowedRoots.some((root) => absolutePath.startsWith(root))
    ? absolutePath
    : null;
}

app.get('/api/health', (req, res) => {
  res.json({
    success: true,
    message: 'Node.js server is running',
    timestamp: new Date().toISOString(),
    environment: process.env.NODE_ENV || 'development',
  });
});

app.post('/api/login', login);
app.post('/api/save-test-report', requireAuth, saveTestReport);
app.get('/api/get-test-reports', requireAuth, getTestReports);
app.get('/api/reports', requireAuth, getTestReports);

app.delete('/api/reports/:id', requireAuth, async (req, res) => {
  try {
    const rows = await query('SELECT * FROM test_reports WHERE id = ? LIMIT 1', [req.params.id]);

    if (rows.length === 0) {
      console.warn(`[Delete] Report not found: id=${req.params.id}`);
      return res.status(404).json({
        success: false,
        error: 'Report not found',
      });
    }

    const report = rows[0];
    await query('DELETE FROM test_reports WHERE id = ?', [req.params.id]);
    console.log(`[Delete] Deleted report from database: id=${req.params.id}`);

    const artifactPath = report.report_html || report.pdf_path || '';
    const match = String(artifactPath).match(/\/uploads\/order_flow_reports\/([^/]+)/);
    if (match?.[1]) {
      const reportDir = join(orderReportsPath, basename(match[1]));
      if (reportDir.startsWith(orderReportsPath) && existsSync(reportDir)) {
        await fs.rm(reportDir, { recursive: true, force: true });
        console.log(`[Delete] Removed order-flow report directory: ${reportDir}`);
      }
    } else {
      const quickMatch = String(artifactPath).match(/\/automation\/results\/([^/.]+)/);
      if (quickMatch?.[1]) {
        const base = basename(quickMatch[1]);
        const quickJson = join(quickReportsPath, `${base}.json`);
        try {
          const checks = JSON.parse(await fs.readFile(quickJson, 'utf8'));
          if (Array.isArray(checks)) {
            for (const check of checks) {
              const screenshot = check?.screenshot_path ? resolve(String(check.screenshot_path)) : '';
              if (screenshot && screenshot.startsWith(quickReportsPath) && existsSync(screenshot)) {
                await fs.rm(screenshot, { force: true });
              }
            }
          }
        } catch {
          // Keep report deletion resilient even if the JSON sidecar is missing.
        }
        const quickArtifacts = [
          join(quickReportsPath, `${base}.html`),
          join(quickReportsPath, `${base}.pdf`),
          quickJson,
        ];
        for (const artifact of quickArtifacts) {
          if (artifact.startsWith(quickReportsPath) && existsSync(artifact)) {
            await fs.rm(artifact, { recursive: true, force: true });
          }
        }
        console.log(`[Delete] Removed quick-check report artifacts for: ${base}`);
      }
      for (const pathCandidate of [report.report_html, report.pdf_path]) {
        const artifact = resolvePublicArtifact(pathCandidate);
        if (artifact && existsSync(artifact)) {
          await fs.rm(artifact, { force: true });
          console.log(`[Delete] Removed artifact: ${artifact}`);
        }
      }
    }

    return res.json({
      success: true,
      message: 'Report deleted',
      id: Number(req.params.id),
    });
  } catch (error) {
    console.error('[Delete] Error:', error);
    return res.status(500).json({
      success: false,
      error: 'Failed to delete report',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.get('/api/automation-logs', requireAuth, async (req, res) => {
  try {
    const logs = await query(
      `SELECT id, run_type, status, input_url, report_id, report_path, log_path, message,
              started_at, finished_at, created_by
       FROM automation_logs
       ORDER BY id DESC
       LIMIT 50`,
    );
    res.json({ success: true, count: logs.length, data: logs });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: 'Failed to fetch automation logs',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.get('/api/runs', requireAuth, (req, res) => {
  const runs = Array.from(activeRuns.values())
    .sort((a, b) => String(b.started_at).localeCompare(String(a.started_at)));
  res.json({
    success: true,
    count: runs.length,
    data: runs,
  });
});

app.post('/api/runs/:id/stop', requireAuth, async (req, res) => {
  const run = activeRuns.get(req.params.id);
  if (!run) {
    return res.status(404).json({ success: false, error: 'Run not found' });
  }

  try {
    if (run.stopSignalPath) {
      await fs.mkdir(dirname(run.stopSignalPath), { recursive: true });
      await fs.writeFile(run.stopSignalPath, `Stop requested at ${new Date().toISOString()}\n`);
    }

    const child = runProcesses.get(req.params.id);
    if (child && !child.killed && run.type === 'quick-check') {
      child.kill();
    }

    updateRun(req.params.id, {
      stop_requested: true,
      message: run.type === 'quick-check'
        ? 'Stop requested. Quick check is closing.'
        : 'Stop requested. The current browser step will finish before the runner exits.',
    });

    return res.json({ success: true, message: 'Stop requested', run_id: req.params.id });
  } catch (error) {
    return res.status(500).json({
      success: false,
      error: 'Failed to request stop',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.post('/api/run-automation', requireAuth, async (req, res) => {
  const {
    baseUrl,
    offerName = '88startech',
    browser = 'chromium',
    headless = true,
    csvPath,
    csvContent,
  } = req.body;

  if (!baseUrl) {
    return res.status(400).json({ success: false, error: 'baseUrl is required' });
  }

  const runType = 'order-flow';
  const runningRun = findRunningRun(runType);
  if (isRunTypeBusy(runType)) {
    return res.status(409).json({
      success: false,
      error: `${runningRun?.label || 'This test'} is already starting or running. Stop it or wait for it to finish before starting another run.`,
      run_id: runningRun?.run_id,
    });
  }
  startingRunTypes.add(runType);

  const reportId = createRunId('ofr');
  const reportDir = join(orderReportsPath, reportId);
  const inputDir = join(reportDir, 'input');
  const resolvedCsvPath = csvPath ? resolve(__dirname, csvPath) : join(inputDir, 'orders.csv');
  const stopSignalPath = join(uploadsPath, 'order_flow_stops', `${reportId}.stop`);
  const configPath = join(reportDir, 'run-config.json');
  const logId = await createAutomationLog({
    runType: 'order-flow',
    inputUrl: baseUrl,
    reportId,
    createdBy: req.user?.email,
  });

  try {
    await fs.mkdir(inputDir, { recursive: true });
    await fs.mkdir(dirname(stopSignalPath), { recursive: true });

    if (!csvPath) {
      if (!csvContent) {
        await updateAutomationLog(logId, {
          status: 'Failed',
          reportId,
          message: 'csvPath or csvContent is required',
        });
        startingRunTypes.delete(runType);
        return res.status(400).json({
          success: false,
          error: 'csvPath or csvContent is required',
        });
      }
      await fs.writeFile(resolvedCsvPath, csvContent, 'utf8');
    }

    if (!existsSync(resolvedCsvPath)) {
      await updateAutomationLog(logId, {
        status: 'Failed',
        reportId,
        message: `CSV file not found: ${resolvedCsvPath}`,
      });
      startingRunTypes.delete(runType);
      return res.status(400).json({
        success: false,
        error: `CSV file not found: ${resolvedCsvPath}`,
      });
    }

    const runConfig = {
      reportId,
      offerName,
      baseUrl,
      browser,
      headless: Boolean(headless),
      csvPath: resolvedCsvPath,
      locatorsPath,
      reportDir,
      stopSignalPath,
    };

    await fs.writeFile(configPath, JSON.stringify(runConfig, null, 2));
    setRun(reportId, {
      type: 'order-flow',
      label: '88startech',
      input_url: baseUrl,
      report_id: reportId,
      stopSignalPath,
      log_id: logId,
      message: '88startech automation is running.',
    });
    startingRunTypes.delete(runType);

    const child = startNodeScript(orderRunnerPath, [configPath], async (result) => {
      runProcesses.delete(reportId);
      const payload = parseLastJson(result.stdout) || {};
      const report = payload.report || {};
      const htmlPath = `/uploads/order_flow_reports/${reportId}/report.html`;
      let pdfPath = `/uploads/order_flow_reports/${reportId}/report.pdf`;
      const absolutePdf = join(reportDir, 'report.pdf');
      const absoluteHtml = join(reportDir, 'report.html');
      if (!existsSync(absoluteHtml)) {
        await fs.writeFile(absoluteHtml, buildFailureReportHtml({
          title: '88startech Automation Report',
          runId: reportId,
          baseUrl,
          message: payload.error || result.stderr || 'Automation did not complete.',
        }), 'utf8');
      }
      if (!existsSync(absolutePdf) && existsSync(absoluteHtml)) {
        const generated = await renderHtmlToPdf(absoluteHtml, absolutePdf);
        pdfPath = generated ? `/uploads/order_flow_reports/${reportId}/report.pdf` : htmlPath;
      }
      const reportPath = existsSync(absolutePdf) ? pdfPath : htmlPath;
      const status = payload.success ? 'Completed' : payload.stopped ? 'Stopped' : 'Failed';

      const reportDbId = await saveReportRecord({
        testLink: baseUrl,
        pdfPath,
        htmlPath,
        status,
        reportType: '88startech',
        runId: reportId,
        offerName: report.offer_name || 'Order Flow Automation',
        browserName: report.browser || 'Chromium',
        passCount: report.placed_orders || 0,
        failCount: report.failed_orders || 0,
      });
      await updateAutomationLog(logId, {
        status,
        reportId,
        reportPath,
        logPath: `/uploads/order_flow_reports/${reportId}/runner.log`,
        message: payload.error || result.stderr || 'Automation finished',
      });
      updateRun(reportId, {
        status,
        finished_at: new Date().toISOString(),
        report,
        report_path: reportPath,
        pdf_path: pdfPath,
        html_path: htmlPath,
        report_db_id: reportDbId,
        error: payload.error,
        message: payload.error || '88startech automation finished.',
      });
    });
    runProcesses.set(reportId, child);
    updateRun(reportId, { pid: child.pid });

    return res.status(202).json({
      success: true,
      run_id: reportId,
      status: 'Running',
      message: '88startech automation started',
    });
  } catch (error) {
    startingRunTypes.delete(runType);
    await updateAutomationLog(logId, {
      status: 'Failed',
      reportId,
      message: error.message,
    });
    return res.status(500).json({
      success: false,
      error: 'Automation failed',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.get('/api/quick-check', requireAuth, async (req, res) => {
  const baseUrl = req.query.url || req.query.baseUrl;
  if (!baseUrl) {
    return res.status(400).json({ success: false, error: 'url query parameter is required' });
  }

  const runType = 'quick-check';
  const runningRun = findRunningRun(runType);
  if (isRunTypeBusy(runType)) {
    return res.status(409).json({
      success: false,
      error: `${runningRun?.label || 'This test'} is already starting or running. Stop it or wait for it to finish before starting another run.`,
      run_id: runningRun?.run_id,
    });
  }
  startingRunTypes.add(runType);

  const runId = createRunId('quick');
  const reportPath = join(quickReportsPath, `${runId}.json`);
  const logId = await createAutomationLog({
    runType: 'quick-check',
    inputUrl: baseUrl,
    reportId: runId,
    createdBy: req.user?.email,
  });

  try {
    await fs.mkdir(quickReportsPath, { recursive: true });
    setRun(runId, {
      type: 'quick-check',
      label: 'Quick Check',
      input_url: baseUrl,
      report_id: runId,
      log_id: logId,
      message: 'Quick check is running.',
    });
    startingRunTypes.delete(runType);

    const child = startNodeScript(quickRunnerPath, ['', baseUrl, reportPath], async (result) => {
      runProcesses.delete(runId);
      const payload = parseLastJson(result.stdout) || {};
      const stopRequested = Boolean(activeRuns.get(runId)?.stop_requested);
      const status = stopRequested ? 'Stopped' : result.code === 0 ? 'Completed' : 'Failed';
      let checks = [];
      try {
        checks = JSON.parse(await fs.readFile(reportPath, 'utf8'));
      } catch {
        checks = [];
      }

      const htmlPath = join(quickReportsPath, `${runId}.html`);
      const pdfPath = join(quickReportsPath, `${runId}.pdf`);
      await fs.writeFile(htmlPath, buildQuickReportHtml({ runId, baseUrl, checks }), 'utf8');
      const pdfGenerated = await renderHtmlToPdf(htmlPath, pdfPath);
      const publicHtmlPath = toPublicPath(htmlPath);
      const publicPdfPath = pdfGenerated ? toPublicPath(pdfPath) : publicHtmlPath;

      const passCount = checks.filter((item) => String(item.status || '').toUpperCase() === 'PASS').length;
      const failCount = checks.filter((item) => String(item.status || '').toUpperCase() === 'FAIL').length;

      const reportDbId = await saveReportRecord({
        testLink: baseUrl,
        pdfPath: publicPdfPath,
        htmlPath: publicHtmlPath,
        status,
        reportType: 'Quick Check',
        runId,
        offerName: 'Quick Check',
        browserName: 'Chromium',
        passCount,
        failCount,
      });
      await updateAutomationLog(logId, {
        status,
        reportId: runId,
        reportPath: publicPdfPath,
        message: result.stderr || `Quick check wrote ${payload.count || checks.length} result(s)`,
      });
      updateRun(runId, {
        status,
        finished_at: new Date().toISOString(),
        report_path: publicPdfPath,
        pdf_path: publicPdfPath,
        json_path: toPublicPath(reportPath),
        report_db_id: reportDbId,
        count: checks.length,
        data: checks,
        error: status === 'Completed' ? undefined : result.stderr || (stopRequested ? 'Quick check stopped by user.' : 'Quick check failed'),
        message: status === 'Stopped' ? 'Quick check stopped.' : `Quick check finished with ${checks.length} result(s).`,
      });
    });
    runProcesses.set(runId, child);
    updateRun(runId, { pid: child.pid });

    return res.status(202).json({
      success: true,
      run_id: runId,
      status: 'Running',
      message: 'Quick check started',
    });
  } catch (error) {
    startingRunTypes.delete(runType);
    await updateAutomationLog(logId, {
      status: 'Failed',
      reportId: runId,
      message: error.message,
    });
    return res.status(500).json({
      success: false,
      error: 'Quick check failed',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.get('/', (req, res) => {
  res.sendFile(join(frontendPath, 'index.html'));
});

app.get('/view-reports', (req, res) => {
  res.sendFile(join(frontendPath, 'view-reports.html'));
});

app.get('/reports/:id/download', async (req, res) => {
  try {
    const rows = await query('SELECT * FROM test_reports WHERE id = ? LIMIT 1', [req.params.id]);
    if (rows.length === 0) {
      console.warn(`[Download] Report not found: id=${req.params.id}`);
      return res.status(404).json({ success: false, error: 'Report not found' });
    }

    const artifact = await resolveReportArtifact(rows[0], 'download');
    const absolute = resolvePublicArtifact(artifact);
    if (!artifact || !absolute) {
      console.error(`[Download] Artifact resolution failed for id=${req.params.id}`, {
        artifact,
        absolute,
        report: rows[0],
      });
      return res.status(404).json({ success: false, error: 'Report file not available' });
    }

    console.log(`[Download] Serving report: id=${req.params.id}, file=${absolute}`);
    return res.download(absolute);
  } catch (error) {
    console.error('[Download] Error:', error);
    return res.status(500).json({
      success: false,
      error: 'Failed to download report',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.get('/reports/:id', async (req, res) => {
  try {
    const rows = await query('SELECT * FROM test_reports WHERE id = ? LIMIT 1', [req.params.id]);
    if (rows.length === 0) {
      console.warn(`[View] Report not found: id=${req.params.id}`);
      return res.status(404).json({ success: false, error: 'Report not found' });
    }

    const reportPath = await resolveReportArtifact(rows[0], 'view');
    if (!reportPath) {
      console.error(`[View] Artifact resolution failed for id=${req.params.id}`, {
        report: rows[0],
      });
      return res.status(404).json({ success: false, error: 'Report file not available' });
    }

    console.log(`[View] Redirecting to report: id=${req.params.id}, path=${reportPath}`);
    return res.redirect(reportPath);
  } catch (error) {
    console.error('[View] Error:', error);
    return res.status(500).json({
      success: false,
      error: 'Failed to open report',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
});

app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'Route not found',
    path: req.path,
    method: req.method,
  });
});

app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(500).json({
    success: false,
    error: 'Internal server error',
    message: process.env.NODE_ENV === 'development' ? err.message : undefined,
  });
});

async function startServer() {
  try {
    await ensurePortalSchema();
  } catch (error) {
    console.warn('[DB] Startup schema check skipped:', error.message);
  }

  app.listen(PORT, () => {
    console.log(`QA Testing Portal Node server running on http://localhost:${PORT}`);
    console.log('Routes: GET /, POST /api/login, POST /api/run-automation, GET /api/reports, GET /api/quick-check');
  });
}

startServer();

export default app;
