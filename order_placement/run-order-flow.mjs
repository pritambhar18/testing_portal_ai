import fs from 'fs/promises';
import path from 'path';
import { execFile } from 'child_process';
import { promisify } from 'util';
import { fileURLToPath } from 'url';
import tls from 'tls';
import { chromium, firefox, webkit } from 'playwright';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const execFileAsync = promisify(execFile);

function normalizeHeader(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '');
}

function parseBoolean(value) {
  if (value === true || value === 1) {
    return true;
  }

  if (typeof value === 'string') {
    return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
  }

  return false;
}

function csvSplit(line) {
  const cells = [];
  let current = '';
  let inQuotes = false;

  for (let index = 0; index < line.length; index += 1) {
    const char = line[index];
    const next = line[index + 1];

    if (char === '"' && inQuotes && next === '"') {
      current += '"';
      index += 1;
      continue;
    }

    if (char === '"') {
      inQuotes = !inQuotes;
      continue;
    }

    if (char === ',' && !inQuotes) {
      cells.push(current);
      current = '';
      continue;
    }

    current += char;
  }

  cells.push(current);
  return cells.map((cell) => cell.trim());
}

async function parseCsv(filePath) {
  const raw = await fs.readFile(filePath, 'utf8');
  const lines = raw
    .replace(/^\uFEFF/, '')
    .split(/\r?\n/)
    .filter((line) => line.trim() !== '');

  if (lines.length < 2) {
    throw new Error('CSV must contain a header row and at least one data row.');
  }

  const rawHeaders = csvSplit(lines[0]);
  const seenHeaders = new Map();
  const headers = rawHeaders.map((header) => {
    const normalizedHeader = normalizeHeader(header) || 'column';
    const seenCount = seenHeaders.get(normalizedHeader) || 0;
    seenHeaders.set(normalizedHeader, seenCount + 1);
    return seenCount === 0 ? header : `${header}__${seenCount + 1}`;
  });
  return lines.slice(1).map((line) => {
    const values = csvSplit(line);
    const row = {};
    headers.forEach((header, index) => {
      row[header] = values[index] ?? '';
    });
    return row;
  });
}

function getField(row, aliases, required = false) {
  const normalized = Object.entries(row).reduce((carry, [key, value]) => {
    carry[normalizeHeader(key)] = value;
    return carry;
  }, {});

  for (const alias of aliases) {
    const match = normalized[normalizeHeader(alias)];
    if (match !== undefined && String(match).trim() !== '') {
      return String(match).trim();
    }
  }

  if (required) {
    throw new Error(`Missing required CSV column. Expected one of: ${aliases.join(', ')}`);
  }

  return '';
}

function getStrictField(row, headerName, required = false) {
  const value = row[headerName];
  if (value !== undefined && String(value).trim() !== '') {
    return String(value).trim();
  }

  if (required) {
    throw new Error(`Missing required CSV column: ${headerName}`);
  }

  return '';
}

function getProspectData(row) {
  return {
    firstName: getStrictField(row, 'first name', true),
    lastName: getStrictField(row, 'lname', true),
    phoneNumber: getStrictField(row, 'phone number', true),
    email: getField(row, ['email'], false),
    address: getField(row, ['address', 'email__2', 'email2'], true),
    city: getStrictField(row, 'city', true),
    zipCode: getStrictField(row, 'zipcode', true),
    cardNumber: getStrictField(row, 'card number', true),
  };
}

function classifyCard(cardNumber) {
  const card = String(cardNumber).replace(/\D/g, '');
  const rules = {
    '1444444444444441': { status: 'decline', cardType: 'VISA' },
    '1444444444444445': { status: 'decline', cardType: 'Master' },
    '1444444444444442': { status: 'prepaid', cardType: 'VISA' },
    '1444444444444446': { status: 'prepaid', cardType: 'Master' },
    '1444444444444440': { status: 'regular', cardType: 'VISA' },
    '1444444444444443': { status: 'regular', cardType: 'Master' },
    '4147090000000001': { status: 'regular', cardType: 'VISA BSB' },
    '5156760000000001': { status: 'regular', cardType: 'Master BSB' },
  };
  return rules[card] || { status: 'unknown', cardType: 'Unknown' };
}

function getBrowserLauncher(browserName) {
  if (browserName === 'firefox') {
    return { launcher: firefox, options: {} };
  }
  if (browserName === 'webkit') {
    return { launcher: webkit, options: {} };
  }
  if (browserName === 'chrome') {
    return { launcher: chromium, options: { channel: 'chrome' } };
  }
  if (browserName === 'msedge') {
    return { launcher: chromium, options: { channel: 'msedge' } };
  }
  return { launcher: chromium, options: {} };
}

function timestamp() {
  return new Date().toISOString();
}

function formatSystemDateTime(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

async function appendRunnerLog(logPath, message) {
  await fs.appendFile(logPath, `[${timestamp()}] ${message}\n`);
}

async function findWkhtmltopdf() {
  const candidates = [
    'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
    'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
    'wkhtmltopdf',
  ];

  for (const candidate of candidates) {
    try {
      const { stdout } = await execFileAsync('where', [candidate], { windowsHide: true });
      const resolved = String(stdout || '').split(/\r?\n/).find(Boolean);
      if (resolved) {
        return resolved.trim();
      }
    } catch (error) {
      try {
        await fs.access(candidate);
        return candidate;
      } catch (accessError) {
        // Continue to next candidate.
      }
    }
  }

  return '';
}

async function renderPdfWithPlaywright(pdfPath, htmlPath) {
  let browser = null;

  try {
    browser = await chromium.launch({
      headless: true,
      channel: 'msedge',
    });
  } catch (primaryError) {
    browser = await chromium.launch({
      headless: true,
    });
  }

  try {
    const page = await browser.newPage();
    await page.goto(`file:///${htmlPath.replace(/\\/g, '/')}`, { waitUntil: 'load' });
    await page.pdf({
      path: pdfPath,
      format: 'A4',
      printBackground: true,
      margin: {
        top: '14mm',
        right: '10mm',
        bottom: '14mm',
        left: '10mm',
      },
    });
  } finally {
    await browser.close().catch(() => {});
  }
}

async function renderPdfWithWkhtmltopdf(pdfPath, htmlPath) {
  const wkhtmltopdf = await findWkhtmltopdf();
  if (!wkhtmltopdf) {
    throw new Error('wkhtmltopdf executable not found.');
  }

  await execFileAsync(
    wkhtmltopdf,
    ['--enable-local-file-access', '--print-media-type', htmlPath, pdfPath],
    { windowsHide: true },
  );
}

async function renderPdfReport(pdfPath, htmlPath) {
  try {
    await renderPdfWithPlaywright(pdfPath, htmlPath);
    return {
      generated: true,
      format: 'pdf',
      path: pdfPath,
      error: '',
    };
  } catch (playwrightError) {
    try {
      await renderPdfWithWkhtmltopdf(pdfPath, htmlPath);
      return {
        generated: true,
        format: 'pdf',
        path: pdfPath,
        error: '',
      };
    } catch (wkhtmlError) {
      return {
        generated: false,
        format: 'html',
        path: htmlPath,
        error: sanitizeMessage(
          [
            `Playwright PDF failed: ${playwrightError.message}`,
            `wkhtmltopdf failed: ${wkhtmlError.message}`,
          ].join(' | '),
        ),
      };
    }
  }
}

function sanitizeMessage(value) {
  return String(value ?? '')
    .replace(/\x1B\[[0-9;]*m/g, '')
    .replace(/\[\d+m/g, '')
    .replace(/\[\d+;\d+m/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

class StopRequestedError extends Error {
  constructor(message = 'Order flow stopped by user.') {
    super(message);
    this.name = 'StopRequestedError';
  }
}

async function throwIfStopRequested(stopSignalPath, logPath = '') {
  if (!stopSignalPath) {
    return;
  }

  try {
    await fs.access(stopSignalPath);
    if (logPath) {
      await appendRunnerLog(logPath, 'Stop signal detected. Closing active browser session.');
    }
    throw new StopRequestedError();
  } catch (error) {
    if (error instanceof StopRequestedError) {
      throw error;
    }
  }
}

function createStopMonitor(session, stopSignalPath, logPath = '') {
  if (!stopSignalPath) {
    return () => {};
  }

  let closing = false;
  const timer = setInterval(async () => {
    if (closing) {
      return;
    }

    try {
      await fs.access(stopSignalPath);
      closing = true;
      if (logPath) {
        await appendRunnerLog(logPath, 'Stop monitor detected signal. Closing browser immediately.');
      }
      await session.page.close().catch(() => {});
      await session.context.close().catch(() => {});
      await session.browser.close().catch(() => {});
    } catch (error) {
      // No stop file yet.
    }
  }, 500);

  return () => clearInterval(timer);
}

function normalizeUrlForReport(rawUrl) {
  const parsed = new URL(rawUrl);
  parsed.hash = '';
  return parsed.toString();
}

function buildToolLink(base, label) {
  return `<a href="${escapeHtml(base)}" target="_blank" rel="noopener">${escapeHtml(label)}</a>`;
}

function formatCheckStatus(status) {
  if (status === 'PASS') {
    return '<span class="pass">PASS</span>';
  }
  if (status === 'FAIL') {
    return '<span class="fail">FAIL</span>';
  }
  if (status === 'SKIPPED') {
    return '<span class="manual">SKIPPED</span>';
  }
  if (status === 'MANUAL') {
    return '<span class="manual">REVIEW</span>';
  }
  return escapeHtml(status || 'REVIEW');
}

function classifyOrderIssue(item) {
  const text = `${item.status || ''} ${item.message || ''} ${item.expectation || ''}`.toLowerCase();
  if (/payment|card|decline|checkout|popup|submit|thank-you|thankyou|order/.test(text)) {
    return { severity: 'High', priority: 'P1' };
  }
  if (/validation|required|field|zip|phone|email|address/.test(text)) {
    return { severity: 'Medium', priority: 'P2' };
  }
  return { severity: 'Low', priority: 'P3' };
}

async function traceRedirects(url, maxRedirects = 10) {
  const redirects = [];
  let currentUrl = url;

  for (let index = 0; index < maxRedirects; index += 1) {
    const response = await fetch(currentUrl, {
      method: 'GET',
      redirect: 'manual',
    });

    const status = response.status;
    const location = response.headers.get('location');
    redirects.push({
      url: currentUrl,
      status,
      location: location || '',
    });

    if (status >= 300 && status < 400 && location) {
      currentUrl = new URL(location, currentUrl).toString();
      continue;
    }

    return {
      finalUrl: currentUrl,
      finalStatus: status,
      redirects,
      headers: Object.fromEntries(response.headers.entries()),
      body: await response.text().catch(() => ''),
    };
  }

  throw new Error(`Too many redirects while requesting ${url}`);
}

async function checkHttp2Support(url) {
  const parsed = new URL(url);
  const host = parsed.hostname;
  const port = Number(parsed.port || 443);

  return await new Promise((resolve) => {
    const socket = tls.connect({
      host,
      port,
      servername: host,
      ALPNProtocols: ['h2', 'http/1.1'],
      rejectUnauthorized: false,
    }, () => {
      const protocol = socket.alpnProtocol || '';
      socket.end();
      resolve(protocol === 'h2');
    });

    socket.setTimeout(10000, () => {
      socket.destroy();
      resolve(false);
    });

    socket.on('error', () => resolve(false));
  });
}

async function checkDomainExpiry(hostname) {
  try {
    const response = await fetch(`https://rdap.org/domain/${hostname}`, {
      method: 'GET',
      redirect: 'follow',
    });
    if (!response.ok) {
      throw new Error(`RDAP returned ${response.status}`);
    }

    const payload = await response.json();
    const events = Array.isArray(payload?.events) ? payload.events : [];
    const expiryEvent = events.find((event) => /expir/i.test(String(event?.eventAction || '')));
    const expiryDate = expiryEvent?.eventDate ? new Date(expiryEvent.eventDate) : null;

    if (!expiryDate || Number.isNaN(expiryDate.getTime())) {
      return {
        status: 'MANUAL',
        notes: 'Unable to determine expiry date automatically.',
      };
    }

    const daysRemaining = Math.floor((expiryDate.getTime() - Date.now()) / 86400000);
    return {
      status: daysRemaining >= 90 ? 'PASS' : 'FAIL',
      notes: `Expiry: ${expiryDate.toISOString().slice(0, 10)} (${daysRemaining} days remaining)`,
    };
  } catch (error) {
    return {
      status: 'MANUAL',
      notes: sanitizeMessage(error.message),
    };
  }
}

const SECURITY_VIEWPORT = { width: 1280, height: 720 };
const SECURITY_REPORT_PRESETS = {
  fast: {
    totalBudgetMs: 90000,
    maxTasks: 5,
    perTaskTimeoutMs: 20000,
    includeHeavyTasks: false,
    minRemainingMs: 5000,
  },
  balanced: {
    totalBudgetMs: 180000,
    maxTasks: 8,
    perTaskTimeoutMs: 30000,
    includeHeavyTasks: true,
    minRemainingMs: 8000,
  },
  full: {
    totalBudgetMs: 420000,
    maxTasks: Number.POSITIVE_INFINITY,
    perTaskTimeoutMs: 180000,
    includeHeavyTasks: true,
    minRemainingMs: 10000,
  },
};

function clampPositiveInteger(value, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function resolveSecurityReportOptions(rawOptions = {}) {
  const requestedMode = String(
    rawOptions.mode
    || process.env.ORDER_FLOW_SECURITY_MODE
    || 'balanced',
  ).trim().toLowerCase();
  const preset = SECURITY_REPORT_PRESETS[requestedMode] || SECURITY_REPORT_PRESETS.fast;

  const totalBudgetMs = clampPositiveInteger(
    rawOptions.totalBudgetMs || process.env.ORDER_FLOW_SECURITY_BUDGET_MS,
    preset.totalBudgetMs,
  );
  const perTaskTimeoutMs = clampPositiveInteger(
    rawOptions.perTaskTimeoutMs || process.env.ORDER_FLOW_SECURITY_TASK_TIMEOUT_MS,
    preset.perTaskTimeoutMs,
  );
  const maxTasks = rawOptions.maxTasks || process.env.ORDER_FLOW_SECURITY_MAX_TASKS;

  return {
    mode: SECURITY_REPORT_PRESETS[requestedMode] ? requestedMode : 'fast',
    totalBudgetMs,
    perTaskTimeoutMs,
    maxTasks: maxTasks === undefined || maxTasks === null || maxTasks === ''
      ? preset.maxTasks
      : clampPositiveInteger(maxTasks, preset.maxTasks),
    includeHeavyTasks: rawOptions.includeHeavyTasks === undefined
      ? preset.includeHeavyTasks
      : Boolean(rawOptions.includeHeavyTasks),
    minRemainingMs: clampPositiveInteger(rawOptions.minRemainingMs, preset.minRemainingMs),
  };
}

function createTimeoutError(message) {
  const error = new Error(message);
  error.name = 'TimeoutError';
  return error;
}

function withTimeout(promise, timeoutMs, message) {
  if (!Number.isFinite(timeoutMs) || timeoutMs <= 0) {
    return Promise.resolve(promise);
  }

  let timeoutId = null;
  const timeoutPromise = new Promise((_, reject) => {
    timeoutId = setTimeout(() => {
      reject(createTimeoutError(message));
    }, timeoutMs);
  });

  return Promise.race([promise, timeoutPromise]).finally(() => {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  });
}

function buildSecurityScreenshotPaths(reportId, screenshotDir, fileName) {
  const sanitizedReportId = (reportId || 'security').replace(/[^a-zA-Z0-9_-]/g, '_');
  const screenshotReportPath = `screenshots/${fileName}`;
  const screenshotPublicPath = `/uploads/order_flow_reports/${sanitizedReportId}/${screenshotReportPath}`;
  return {
    screenshotReportPath,
    screenshotPublicPath,
    fullScreenshotPath: path.join(screenshotDir, fileName),
  };
}

async function createSecurityPlaceholderScreenshot(reportId, screenshotDir, taskId, label, message) {
  const safeTaskId = String(taskId || 'security').replace(/[^a-zA-Z0-9_-]/g, '_');
  const fileName = `${safeTaskId}_placeholder.svg`;
  const paths = buildSecurityScreenshotPaths(reportId, screenshotDir, fileName);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="900" height="520" viewBox="0 0 900 520">
  <rect width="900" height="520" fill="#f8fafc"/>
  <rect x="36" y="36" width="828" height="448" rx="18" fill="#ffffff" stroke="#d8dee9" stroke-width="2"/>
  <text x="70" y="110" fill="#111827" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700">Screenshot not captured</text>
  <text x="70" y="162" fill="#344054" font-family="Arial, Helvetica, sans-serif" font-size="22">${escapeHtml(label || 'Security check')}</text>
  <foreignObject x="70" y="198" width="760" height="210">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:1.45;color:#667085;">
      ${escapeHtml(message || 'The external security tool did not render a usable screenshot before timeout.')}
    </div>
  </foreignObject>
  <text x="70" y="440" fill="#0f766e" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="700">QA Testing Portal</text>
</svg>`;
  await fs.writeFile(paths.fullScreenshotPath, svg, 'utf8');
  return paths;
}

async function waitForVisibleMatchingLocator(page, selector, timeout = 15000) {
  const deadline = Date.now() + timeout;

  while (Date.now() < deadline) {
    const locator = page.locator(selector);
    const count = await locator.count();
    for (let index = 0; index < count; index += 1) {
      const candidate = locator.nth(index);
      if (await candidate.isVisible()) {
        return candidate;
      }
    }
    await page.waitForTimeout(200);
  }

  throw new Error(`Selector did not become visible: ${selector}`);
}

async function fillFallbackInput(page, value, timeout) {
  const fallbackSelectors = [
    "input[type='search']",
    "input[type='url']",
    "input[type='text']",
    "textarea",
  ];

  for (const selector of fallbackSelectors) {
    try {
      const locator = await waitForVisibleMatchingLocator(page, selector, timeout);
      await locator.fill(value, { timeout });
      return;
    } catch (error) {
      // try next fallback
    }
  }

  if (await fillInputViaDomFallback(page, value)) {
    return;
  }

  throw new Error('Unable to fill any fallback input field.');
}

async function securityFill(page, selectors, value, options = {}) {
  const timeout = options.timeout ?? 15000;
  const candidateSelectors = Array.isArray(selectors) ? selectors : [selectors];

  for (const selector of candidateSelectors.filter(Boolean)) {
    try {
      const locator = await waitForVisibleMatchingLocator(page, selector, timeout);
      await locator.fill(value, { timeout });
      return;
    } catch (error) {
      // continue to next candidate
    }
  }

  await fillFallbackInput(page, value, timeout);
}

async function fillInputViaDomFallback(page, value) {
  const normalizedValue = String(value ?? '');
  const filled = await page.evaluate((inputValue) => {
    const elements = Array.from(document.querySelectorAll('input, textarea'));
    const visibleCandidates = elements.filter((element) => {
      if (element.disabled || element.readOnly) {
        return false;
      }
      const type = (element.getAttribute('type') || '').toLowerCase();
      if (type === 'hidden') {
        return false;
      }
      const style = window.getComputedStyle(element);
      if (style && (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0')) {
        return false;
      }
      const rect = element.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });

    if (!visibleCandidates.length) {
      return false;
    }

    const hints = ['domain', 'host', 'url', 'search', 'q'];
    const prioritized = visibleCandidates.find((element) => {
      const attributes = [
        element.id,
        element.name,
        element.placeholder,
        element.getAttribute('aria-label'),
        element.getAttribute('type'),
      ];
      return attributes.some((attr) => {
        if (!attr) {
          return false;
        }
        const normalized = attr.toLowerCase();
        return hints.some((hint) => normalized.includes(hint));
      });
    }) || visibleCandidates[0];

    prioritized.focus();
    prioritized.value = inputValue;
    prioritized.dispatchEvent(new Event('input', { bubbles: true }));
    prioritized.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }, normalizedValue);

  return Boolean(filled);
}

async function clickFallbackButton(page, timeout) {
  const fallbackSelectors = [
    "button[type='submit']",
    "button[type='button']",
    'button',
    "input[type='submit']",
    "input[type='button']",
    "a",
  ];

  for (const selector of fallbackSelectors) {
    try {
      const locator = await waitForVisibleMatchingLocator(page, selector, timeout);
      await locator.click({ timeout });
      return;
    } catch (error) {
      // continue iterating
    }
  }

  throw new Error('Unable to click any fallback button.');
}

async function securityClick(page, selectors, options = {}) {
  const timeout = options.timeout ?? 15000;
  const candidateSelectors = Array.isArray(selectors) ? selectors : [selectors];

  for (const selector of candidateSelectors.filter(Boolean)) {
    try {
      const locator = await waitForVisibleMatchingLocator(page, selector, timeout);
      await locator.click({ timeout });
      return;
    } catch (error) {
      // continue to next selector
    }
  }

  await clickFallbackButton(page, timeout);
}

function createSecurityTasks({ baseUrl, domain, domainRoot, httpUrl }) {
  const tasks = [];
  const encodedTestUrl = encodeURIComponent(baseUrl);
  const withoutWwwValue = domain.replace(/^www\./, '');

  tasks.push({
    id: '01_http2',
    label: '1. HTTP2 Report Link',
    url: 'https://tools.keycdn.com/http2-test',
    referenceUrl: 'https://tools.keycdn.com/http2-test',
    referenceLabel: 'KeyCDN HTTP2 Test',
    priority: 1,
    estimatedMs: 8000,
    heavy: false,
    action: async ({ page }) => {
      await securityFill(page, '#url', baseUrl);
      await securityClick(page, '#http2Btn');
      await page.waitForTimeout(2500);
      return { details: 'HTTP2 test executed and results captured on screen.' };
    },
  });

  tasks.push({
    id: '02_https_redirection',
    label: '2. HTTPS Redirection Report Link',
    url: 'https://wheregoes.com/',
    referenceUrl: 'https://wheregoes.com/',
    referenceLabel: 'WhereGoes.com',
    priority: 1,
    estimatedMs: 12000,
    heavy: false,
    action: async ({ page }) => {
      await securityFill(page, '#url', httpUrl);
      await securityClick(page, '#form_button');
      await page.waitForSelector('css=p.date i', { timeout: 20000 });
      await page.waitForTimeout(1000);
      return { details: 'HTTPS redirection trace captured.' };
    },
  });

  tasks.push({
    id: '04_safe_browsing',
    label: '4. Safe Browsing Site Status Report Link',
    url: 'https://transparencyreport.google.com/safe-browsing/search?hl=en',
    referenceUrl: 'https://transparencyreport.google.com/safe-browsing/search',
    referenceLabel: 'Google Transparency Report',
    priority: 3,
    estimatedMs: 18000,
    heavy: false,
    action: async ({ page }) => {
      await securityFill(page, "xpath=//input[@placeholder='Search by URL']", baseUrl);
      await securityClick(page, "xpath=//i[normalize-space()='search']");
      await page.waitForTimeout(4000);
      return { details: 'Safe Browsing lookup queued on Google Transparency Report.' };
    },
  });

  tasks.push({
    id: '05_robots',
    label: '5. Robots.txt Report Link',
    url: `https://${domainRoot || domain}/robots.txt`,
    referenceUrl: `https://${domainRoot || domain}/robots.txt`,
    referenceLabel: 'robots.txt',
    priority: 1,
    estimatedMs: 6000,
    heavy: false,
    action: async ({ page }) => {
      try {
        await page.goto(`https://${domainRoot || domain}/robots.txt`, { waitUntil: 'domcontentloaded', timeout: 15000 });
      } catch (error) {
        await page.goto(`https://www.${domainRoot || domain}/robots.txt`, { waitUntil: 'domcontentloaded', timeout: 15000 });
      }
      await page.waitForTimeout(1000);
      const snippet = await page.locator('body').innerText().catch(() => '');
      return { details: snippet ? `robots.txt snippet: ${snippet.slice(0, 200)}` : 'robots.txt opened.' };
    },
  });

  tasks.push({
    id: '06_domain_expiry',
    label: '6. Domain Expiry check Report Link',
    url: 'https://www.digicert.com/help/',
    referenceUrl: 'https://www.digicert.com/help/',
    referenceLabel: 'Digicert Help',
    priority: 3,
    estimatedMs: 20000,
    heavy: false,
    action: async ({ page }) => {
      await securityFill(
        page,
        [
          '#host',
          "xpath=//input[contains(translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'host')]",
          "xpath=//input[contains(translate(@placeholder,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'host')]",
          "xpath=//input[contains(translate(@aria-label,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'host')]",
        ],
        withoutWwwValue,
        { timeout: 25000 },
      );
      await securityClick(
        page,
        [
          '#check-server-button',
          "xpath=//button[contains(normalize-space(.),'Check')]",
          "xpath=//button[contains(normalize-space(.),'Lookup')]",
          "xpath=//input[@type='submit']",
        ],
        { timeout: 25000 },
      );
      await page.waitForSelector("xpath=//td[normalize-space()='OCSP Staple:']", { timeout: 30000 });
      await page.waitForSelector("xpath=//p[contains(text(),'The certificate expires')]", { timeout: 30000 });
      const expiryText = await page
        .locator("xpath=//p[contains(text(),'The certificate expires')]")
        .first()
        .innerText()
        .catch(() => '');
      return { details: expiryText || 'Domain expiry information captured.' };
    },
  });

  tasks.push({
    id: '07_ssllabs',
    label: '7. SSL Grade Report Link',
    url: 'https://www.ssllabs.com/ssltest',
    referenceUrl: 'https://www.ssllabs.com/ssltest',
    referenceLabel: 'SSL Labs',
    priority: 4,
    estimatedMs: 120000,
    heavy: true,
    action: async ({ page }) => {
      await securityFill(page, "xpath=//input[@name='d']", baseUrl);
      await securityClick(page, "xpath=//input[@value='Submit']", { timeout: 30000 });
      const ready = await waitForAnyVisibleSelector(
        page,
        [
          "css=a[href='index.html']",
          "xpath=//div[contains(normalize-space(.),'Grade')]",
          "xpath=//span[contains(normalize-space(.),'Grade')]",
        ],
        120000,
      );
      if (!ready) {
        throw new Error('SSL Labs grade details did not render in time.');
      }
      await page.waitForTimeout(1000);
      return { details: 'SSL Labs scan queued; check index.html for grade once ready.' };
    },
  });

  tasks.push({
    id: '08_pagespeed_mobile',
    label: '8. Google PageSpeed Mobile Report Link',
    url: 'https://pagespeed.web.dev/?utm_source=psi&utm_medium=redirect',
    referenceUrl: 'https://pagespeed.web.dev/',
    referenceLabel: 'PageSpeed Insights',
    priority: 5,
    estimatedMs: 180000,
    heavy: true,
    action: async ({ page }) => {
      await securityFill(page, '#i2', baseUrl);
      await securityClick(page, "xpath=//span[normalize-space()='Analyze']", { timeout: 60000 });
      await page.waitForSelector("xpath=(//div[@id='performance'])[1]", { timeout: 180000 });
      await securityClick(page, '#mobile_tab');
      await page.waitForSelector("xpath=//*[contains(normalize-space(text()),'Diagnose performance')]", { timeout: 60000 });
      return { details: 'PageSpeed mobile report loaded and diagnosis visible.' };
    },
  });

  tasks.push({
    id: '09_pagespeed_desktop',
    label: '9. Google PageSpeed Desktop Report Link',
    url: `https://pagespeed.web.dev/report?url=${encodedTestUrl}`,
    referenceUrl: 'https://pagespeed.web.dev/',
    referenceLabel: 'PageSpeed Insights',
    priority: 5,
    estimatedMs: 120000,
    heavy: true,
    action: async ({ page }) => {
      await page.waitForSelector("xpath=(//div[@id='performance'])[1]", { timeout: 180000 });
      await securityClick(page, '#desktop_tab');
      const diagnoseReady = await waitForAnyVisibleSelector(
        page,
        [
          "xpath=//*[contains(normalize-space(text()),'Diagnose performance')]",
          "xpath=//*[contains(normalize-space(text()),'Diagnose performance issues')]",
        ],
        90000,
      );
      if (!diagnoseReady) {
        throw new Error('Desktop diagnose button not visible.');
      }
      return { details: 'PageSpeed desktop diagnosis ready for review.' };
    },
  });

  tasks.push({
    id: '10_html_opt',
    label: '10. HTML Optimisation Report Link',
    url: 'https://validator.w3.org/nu/',
    referenceUrl: 'https://validator.w3.org/nu/',
    referenceLabel: 'W3C Validator',
    priority: 3,
    estimatedMs: 12000,
    heavy: false,
    action: async ({ page }) => {
      await securityFill(page, '#doc', baseUrl);
      await securityClick(page, '#submit');
      await page.waitForTimeout(4000);
      return { details: 'W3C HTML validator report queued.' };
    },
  });

  return tasks;
}

function selectSecurityTasks(tasks, options) {
  const skipped = [];
  const filtered = [];
  const maxTasks = Number.isFinite(options.maxTasks) ? options.maxTasks : Number.POSITIVE_INFINITY;
  let estimatedBudgetUsed = 0;

  for (const task of [...tasks].sort((left, right) => {
    const leftPriority = left.priority ?? Number.MAX_SAFE_INTEGER;
    const rightPriority = right.priority ?? Number.MAX_SAFE_INTEGER;
    if (leftPriority !== rightPriority) {
      return leftPriority - rightPriority;
    }
    return String(left.id || '').localeCompare(String(right.id || ''));
  })) {
    if (task.heavy && !options.includeHeavyTasks) {
      skipped.push({
        label: task.label,
        status: 'SKIPPED',
        details: `Skipped in ${options.mode} mode to reduce report generation time.`,
        source: buildToolLink(task.referenceUrl, task.referenceLabel || new URL(task.referenceUrl).hostname),
        currentUrl: task.url,
      });
      continue;
    }

    if (filtered.length >= maxTasks) {
      skipped.push({
        label: task.label,
        status: 'SKIPPED',
        details: `Skipped after reaching the ${options.mode} mode task limit.`,
        source: buildToolLink(task.referenceUrl, task.referenceLabel || new URL(task.referenceUrl).hostname),
        currentUrl: task.url,
      });
      continue;
    }

    const estimatedMs = task.estimatedMs ?? options.perTaskTimeoutMs;
    const projectedBudget = estimatedBudgetUsed + estimatedMs;
    if (filtered.length > 0 && projectedBudget > options.totalBudgetMs) {
      skipped.push({
        label: task.label,
        status: 'SKIPPED',
        details: `Skipped to stay within the ${Math.round(options.totalBudgetMs / 1000)}s report budget.`,
        source: buildToolLink(task.referenceUrl, task.referenceLabel || new URL(task.referenceUrl).hostname),
        currentUrl: task.url,
      });
      continue;
    }

    filtered.push(task);
    estimatedBudgetUsed += estimatedMs;
  }

  return { selected: filtered, skipped };
}

async function executeSecurityTasks(tasks, screenshotDir, reportId, options) {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-gpu'],
  });
  const context = await browser.newContext({
    viewport: SECURITY_VIEWPORT,
    ignoreHTTPSErrors: true,
  });
  const results = [];
  const startedAt = Date.now();

  try {
    for (const task of tasks) {
      const page = await context.newPage();
      const screenshotName = `${task.id}.png`;
      const { fullScreenshotPath, screenshotReportPath, screenshotPublicPath } = buildSecurityScreenshotPaths(
        reportId,
        screenshotDir,
        screenshotName,
      );
      try {
        const elapsedMs = Date.now() - startedAt;
        const remainingBudgetMs = options.totalBudgetMs - elapsedMs;
        const availableTaskBudgetMs = remainingBudgetMs - options.minRemainingMs;

        if (availableTaskBudgetMs <= 0) {
          results.push({
            label: task.label,
            status: 'SKIPPED',
            details: `Skipped because the ${Math.round(options.totalBudgetMs / 1000)}s report budget was exhausted.`,
            source: buildToolLink(task.referenceUrl, task.referenceLabel || new URL(task.referenceUrl).hostname),
          currentUrl: task.url,
        });
        continue;
      }

        const taskTimeoutMs = Math.max(
          5000,
          Math.min(task.timeoutMs ?? options.perTaskTimeoutMs, availableTaskBudgetMs),
        );
        page.setDefaultTimeout(taskTimeoutMs);
        page.setDefaultNavigationTimeout(taskTimeoutMs);

        await withTimeout(
          page.goto(task.url, { waitUntil: 'domcontentloaded', timeout: taskTimeoutMs }),
          taskTimeoutMs,
          `${task.label} timed out while opening ${task.url}.`,
        );
        const actionResult = task.action
          ? await withTimeout(
            task.action({ page }),
            taskTimeoutMs,
            `${task.label} timed out while collecting the report screenshot.`,
          )
          : {};
        let finalScreenshotReportPath = screenshotReportPath;
        let finalScreenshotPublicPath = screenshotPublicPath;
        try {
          await takeScreenshot(page, fullScreenshotPath);
        } catch (screenshotError) {
          const placeholder = await createSecurityPlaceholderScreenshot(
            reportId,
            screenshotDir,
            task.id,
            task.label,
            sanitizeMessage(screenshotError.message),
          );
          finalScreenshotReportPath = placeholder.screenshotReportPath;
          finalScreenshotPublicPath = placeholder.screenshotPublicPath;
        }
        results.push({
          label: task.label,
          status: 'MANUAL',
          details: actionResult?.details || `Screenshot captured: ${task.label}`,
          source: buildToolLink(task.referenceUrl, task.referenceLabel || new URL(task.referenceUrl).hostname),
          currentUrl: page.url(),
          screenshotReportPath: finalScreenshotReportPath,
          screenshotPublicPath: finalScreenshotPublicPath,
        });
      } catch (error) {
        let failedUrl = task.url;
        try {
          failedUrl = page.url();
        } catch {
          // keep fallback
        }
        let finalScreenshotReportPath = screenshotReportPath;
        let finalScreenshotPublicPath = screenshotPublicPath;
        try {
          await takeScreenshot(page, fullScreenshotPath);
        } catch {
          const placeholder = await createSecurityPlaceholderScreenshot(
            reportId,
            screenshotDir,
            task.id,
            task.label,
            sanitizeMessage(error.message),
          );
          finalScreenshotReportPath = placeholder.screenshotReportPath;
          finalScreenshotPublicPath = placeholder.screenshotPublicPath;
        }
        results.push({
          label: task.label,
          status: 'FAIL',
          details: `Security task failed: ${sanitizeMessage(error.message)}`,
          source: buildToolLink(task.referenceUrl, task.referenceLabel || new URL(task.referenceUrl).hostname),
          currentUrl: failedUrl,
          screenshotReportPath: finalScreenshotReportPath,
          screenshotPublicPath: finalScreenshotPublicPath,
        });
      } finally {
        await page.close().catch(() => {});
      }
    }
  } finally {
    await context.close().catch(() => {});
    await browser.close().catch(() => {});
  }

  return results;
}

async function collectSecurityPerformanceChecks(baseUrl, screenshotDir, reportId) {
  const normalizedUrl = normalizeUrlForReport(baseUrl);
  await fs.mkdir(screenshotDir, { recursive: true });
  const parsed = new URL(normalizedUrl);
  const domain = parsed.hostname;
  const domainRoot = domain.replace(/^(?:.*\.)?([^.]+\.[^.]+)$/, '$1');
  const httpUrl = normalizedUrl.replace(/^https:/i, 'http:');
  const options = resolveSecurityReportOptions();
  const tasks = createSecurityTasks({
    baseUrl: normalizedUrl,
    domain,
    domainRoot,
    httpUrl,
  });
  const { selected, skipped } = selectSecurityTasks(tasks, options);
  const executed = await executeSecurityTasks(selected, screenshotDir, reportId, options);

  return [
    ...executed,
    ...skipped,
  ];
}

async function createIsolatedSession(browserName, headless = false) {
  const { launcher, options } = getBrowserLauncher(browserName);
  const launchOptions = {
    headless: parseBoolean(headless),
    ...options,
  };

  const incognitoArgsByBrowser = {
    chromium: ['--incognito', '--start-maximized'],
    chrome: ['--incognito', '--start-maximized'],
    msedge: ['--incognito', '--start-maximized'],
    firefox: ['-private'],
  };
  const extraLaunchArgs = incognitoArgsByBrowser[browserName] || [];
  if (extraLaunchArgs.length > 0) {
    launchOptions.args = [...(launchOptions.args || []), ...extraLaunchArgs];
  }

  // Try launching requested launcher; on failure try fallbacks in order
  try {
    const browser = await launcher.launch(launchOptions);
    const context = await browser.newContext({
      viewport: { width: 1440, height: 1400 },
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();
    return { browser, context, page };
  } catch (launchErr) {
    // Analyze error and attempt safe fallbacks
    const msg = String((launchErr && launchErr.message) || '').toLowerCase();

    // If channel-specific executable missing, remove channel and try plain chromium
    const channelRequested = Boolean(launchOptions && launchOptions.channel);
    const missingPattern = /not found|run \"npx playwright install\"|executable doesn't exist/i;

    if (channelRequested && missingPattern.test(msg)) {
      try {
        const fallbackOptions = { ...launchOptions };
        delete fallbackOptions.channel;
        const browser = await chromium.launch(fallbackOptions);
        const context = await browser.newContext({ viewport: { width: 1440, height: 1400 }, ignoreHTTPSErrors: true });
        const page = await context.newPage();
        return { browser, context, page };
      } catch (errChromium) {
        // try other browser types
      }
    }

    // Try firefox
    try {
      const fwOptions = { headless: launchOptions.headless };
      if (launchOptions.args) fwOptions.args = launchOptions.args;
      const browser = await firefox.launch(fwOptions);
      const context = await browser.newContext({ viewport: { width: 1440, height: 1400 }, ignoreHTTPSErrors: true });
      const page = await context.newPage();
      return { browser, context, page };
    } catch (errFw) {
      // try webkit
    }

    try {
      const wkOptions = { headless: launchOptions.headless };
      if (launchOptions.args) wkOptions.args = launchOptions.args;
      const browser = await webkit.launch(wkOptions);
      const context = await browser.newContext({ viewport: { width: 1440, height: 1400 }, ignoreHTTPSErrors: true });
      const page = await context.newPage();
      return { browser, context, page };
    } catch (errWk) {
      // All fallbacks failed - rethrow original for higher-level handling
      throw launchErr;
    }
  }
}

async function safeClick(page, selector, timeout = 5000) {
  if (!selector) {
    return false;
  }

  const locator = page.locator(selector).first();
  if ((await locator.count()) === 0) {
    return false;
  }

  try {
    await locator.click({ timeout });
    return true;
  } catch (error) {
    return false;
  }
}

async function safeClickAny(page, selectors, timeout = 5000) {
  for (const selector of selectors) {
    if (await safeClick(page, selector, timeout)) {
      return true;
    }
  }

  return false;
}

async function waitForVisibleSelector(page, selector, timeout = 5000) {
  if (!selector) {
    return false;
  }

  try {
    await waitForVisibleMatchingLocator(page, selector, timeout);
    return true;
  } catch (error) {
    return false;
  }
}

async function waitForAnyVisibleSelector(page, selectors, timeout = 5000) {
  for (const selector of selectors) {
    if (await waitForVisibleSelector(page, selector, timeout)) {
      return true;
    }
  }

  return false;
}

async function takeScreenshot(page, screenshotPath) {
  await page.screenshot({ path: screenshotPath, fullPage: true });
}

async function setFieldValue(page, selector, value) {
  const locator = page.locator(selector).first();
  const tagName = await locator.evaluate((element) => element.tagName.toLowerCase());

  if (tagName === 'select') {
    const normalized = String(value);
    await locator.selectOption([
      { value: normalized },
      { label: normalized },
      { value: normalized.padStart(2, '0') },
      { label: normalized.padStart(2, '0') },
    ]).catch(async () => {
      await locator.selectOption({ index: Number.parseInt(normalized, 10) }).catch(() => {});
    });
    return;
  }

  await locator.fill(String(value));
}

async function typeFieldValue(page, selector, value, delay = 0) {
  const locator = page.locator(selector).first();
  await locator.click();
  await locator.fill('');
  await locator.type(String(value), { delay });
}

function getPopupSelectors(locators) {
  const popupSelectors = [
    locators.popups?.modalContent,
    '#mc_pop',
    '.disCardDec',
    "[role='dialog']",
  ].filter(Boolean);

  const dismissSelectors = [
    locators.popups?.noThanksButton,
    '#nomaster',
    "a#nomaster",
    "a:has-text('No thanks')",
    "a:has-text('No Thanks')",
    "button:has-text('No thanks')",
    "button:has-text('No Thanks')",
    "text=/^\\s*No thanks/i",
  ].filter(Boolean);

  return {
    popupSelectors,
    dismissSelectors,
  };
}

async function dismissCheckoutPopup(page, locators, screenshotPath, timeout = Number(locators.popups?.noThanksTimeoutMs || 15000)) {
  const { popupSelectors, dismissSelectors } = getPopupSelectors(locators);
  const popupVisible = await waitForAnyVisibleSelector(page, popupSelectors, timeout);

  if (!popupVisible) {
    return false;
  }

  await takeScreenshot(page, screenshotPath).catch(() => {});

  if (await safeClickAny(page, dismissSelectors, timeout)) {
    for (const popupSelector of popupSelectors) {
      await page.locator(popupSelector).first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    }

    return true;
  }

  for (const dismissSelector of dismissSelectors) {
    const locator = page.locator(dismissSelector).first();
    if ((await locator.count()) === 0) {
      continue;
    }

    const clicked = await locator.evaluate((element) => {
      if (element instanceof HTMLElement) {
        element.click();
        return true;
      }

      return false;
    }).catch(() => false);

    if (!clicked) {
      continue;
    }

    for (const popupSelector of popupSelectors) {
      await page.locator(popupSelector).first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    }

    return true;
  }

  return false;
}

async function waitForCheckoutReady(page, locators, timeout = 30000) {
  await page.waitForSelector(locators.navigation.checkoutReady, { timeout });

  const cardNumberLocator = page.locator(locators.checkout.cardNumber).first();
  await cardNumberLocator.waitFor({ state: 'visible', timeout });
  await cardNumberLocator.evaluate((element) => {
    if (!(element instanceof HTMLInputElement) && !(element instanceof HTMLTextAreaElement)) {
      throw new Error('Card number field is not an editable input.');
    }

    if (element.disabled || element.readOnly) {
      throw new Error('Card number field is not editable yet.');
    }
  });
}

async function enterCardNumberWithPopupHandling(page, locators, cardNumber, screenshotPath) {
  const normalizedCard = String(cardNumber);
  const cardTypingDelayMs = Number(locators.checkout.cardTypingDelayMs || 0);
  const popupTimeoutMs = Number(locators.popups.noThanksTimeoutMs || 15000);
  const earlyPopupTimeoutMs = Number(locators.popups.earlyCardPopupTimeoutMs || 5000);
  let popupDismissed = false;
  let popupSeen = false;

  if (normalizedCard === '4147090000000001') {
    for (let attempt = 0; attempt < 2; attempt += 1) {
      await typeFieldValue(page, locators.checkout.cardNumber, '41', cardTypingDelayMs);
      const popupAppeared = await waitForAnyVisibleSelector(page, getPopupSelectors(locators).popupSelectors, earlyPopupTimeoutMs);
      if (!popupAppeared) {
        break;
      }

      popupSeen = true;
      popupDismissed = await dismissCheckoutPopup(page, locators, screenshotPath, popupTimeoutMs) || popupDismissed;
      await page.waitForTimeout(500);
    }
  }

  await typeFieldValue(page, locators.checkout.cardNumber, normalizedCard, cardTypingDelayMs);
  const popupAppearedAfterCard = await waitForAnyVisibleSelector(page, getPopupSelectors(locators).popupSelectors, popupTimeoutMs);
  if (popupAppearedAfterCard) {
    popupSeen = true;
    popupDismissed = await dismissCheckoutPopup(page, locators, screenshotPath, popupTimeoutMs) || popupDismissed;
  }

  return {
    popupDismissed,
    popupSeen,
    popupAppearedAfterCard,
  };
}

async function extractOrderId(page, resultLocators) {
  const currentUrl = page.url();
  const urlPatterns = [
    /[?&](?:order_id|orderid|orderNumber|ordernumber|order)=([^&#]+)/i,
    /\/(?:thank-you|thankyou|thank-you\.php|order|orders)(?:\/|\?[^#]*[?&]order_id=)([A-Z0-9-]{4,})/i,
    /\/([A-Z0-9-]{6,})(?:\/)?$/i,
  ];

  for (const pattern of urlPatterns) {
    const match = currentUrl.match(pattern);
    if (match?.[1]) {
      return decodeURIComponent(match[1]).trim();
    }
  }

  for (const selector of resultLocators.orderIdSelectors || []) {
    const locator = page.locator(selector).first();
    if ((await locator.count()) === 0) {
      continue;
    }

    const text = (await locator.innerText().catch(() => '')) || '';
    const regex = resultLocators.orderIdRegex ? new RegExp(resultLocators.orderIdRegex, 'i') : null;
    if (regex) {
      const match = text.match(regex);
      if (match?.[1]) {
        return match[1].trim();
      }
    }

    if (text.trim() !== '') {
      return text.trim();
    }
  }

  const pageText = await page.locator('body').innerText().catch(() => '');
  if (resultLocators.orderIdRegex) {
    const match = pageText.match(new RegExp(resultLocators.orderIdRegex, 'i'));
    if (match?.[1]) {
      return match[1].trim();
    }
  }

  return '';
}

function isThankYouUrl(currentUrl) {
  return /thank-you(?:\.php)?|thankyou|order[_-]?confirmed/i.test(currentUrl);
}

function buildHtmlReport(report) {
  const hasResults = Array.isArray(report.results) && report.results.length > 0;
  const overallResult = report.failed_orders > 0 ? 'FAIL' : 'PASS';
  const visibleSecurityPerformance = Array.isArray(report.securityPerformance)
    ? report.securityPerformance.filter((item) => String(item.status || '').trim().toUpperCase() !== 'SKIPPED')
    : [];
  const rowTable = report.results.map((item, index) => `
    <tr>
      <td>${index + 1}</td>
      <td>${escapeHtml(item.email || '-')}</td>
      <td>${escapeHtml(item.orderId || '-')}</td>
      <td>${escapeHtml(item.result || '-')}</td>
      <td>${escapeHtml(item.status || '-')}</td>
      <td>${escapeHtml(item.cardType || '-')}</td>
      <td>${escapeHtml(item.cardLast4 || '-')}</td>
      <td>${escapeHtml(item.message || '-')}</td>
    </tr>
  `).join('');

  const resultSections = report.results.map((item, index) => `
    <section style="margin-bottom:24px;">
      <h3>Order no. ${index + 1}</h3>
      <p><strong>Email:</strong> ${escapeHtml(item.email || '-')}</p>
      <p><strong>Order ID:</strong> ${escapeHtml(item.orderId || '-')}</p>
      <p><strong>Result:</strong> <strong class="${String(item.result).toLowerCase() === 'pass' ? 'pass' : 'fail'}">${escapeHtml(item.result || '-')}</strong></p>
      <p><strong>Status:</strong> ${escapeHtml(item.status || '-')}</p>
      <p><strong>Browser:</strong> ${escapeHtml(report.browser || '-')}</p>
      <p><strong>Card Type:</strong> ${escapeHtml(item.cardType || '-')}</p>
      <p><strong>Card Last 4:</strong> ${escapeHtml(item.cardLast4 || '-')}</p>
      <p><strong>Expectation:</strong> ${escapeHtml(item.expectation || '-')}</p>
      <p><strong>Notes:</strong> ${escapeHtml(item.message || '-')}</p>
      ${(() => {
        const screenshotSrc = item.screenshotReportPath || item.screenshotPublicPath || '';
        const borderColor = item.status === 'PLACED' ? '#2a7b2a' : '#c12a2a';
        return screenshotSrc
          ? `<p><strong>Screenshot:</strong><br><img src="${escapeHtml(screenshotSrc)}" alt="Order no. ${index + 1} screenshot" style="display:block;max-width:340px;width:100%;height:auto;border:3px solid ${borderColor};padding:3px;box-sizing:border-box;margin-top:6px;" /></p>`
          : '<p><strong>Screenshot:</strong> Not available</p>';
      })()}
    </section>
  `).join('');

  const issueSections = report.results
    .map((item, index) => ({ item, rowNumber: index + 1 }))
    .filter(({ item }) => String(item.result || item.status || '').toUpperCase() !== 'PASS' && String(item.status || '').toUpperCase() !== 'PLACED')
    .map(({ item, rowNumber }, index) => {
      const issue = classifyOrderIssue(item);
      return `
      <section class="issue-card">
        <h3>${index + 1}. ${escapeHtml(item.email || 'Order flow issue')} <span>Severity: ${issue.severity} | Priority: ${issue.priority}</span></h3>
        <p><strong>Test step:</strong></p>
        <ul class="step-list">
          <li>Open the browser and navigate to ${escapeHtml(report.url)}.</li>
          <li>Load the landing page for CSV row ${rowNumber} and fill the prospect details.</li>
          <li>Continue to checkout, enter payment details, and submit the order.</li>
          <li>Verify the thank-you page, confirmation state, or expected decline result.</li>
        </ul>
        <p><strong>Test description:</strong> The automation could not complete the expected checkout journey for this row. Expected behavior: the page should accept the row data, move from landing page to checkout, process the configured test card, and return a clear order confirmation or expected decline result. Actual result: ${escapeHtml(item.message || 'The order flow did not complete successfully.')}</p>
      </section>
    `;
    }).join('');

  const securityCards = visibleSecurityPerformance.map((check) => {
    const screenshotSrc = check.screenshotReportPath
      ? escapeHtml(check.screenshotReportPath)
      : '';
    const screenshotHtml = screenshotSrc
      ? `<img src="${screenshotSrc}" alt="${escapeHtml(check.label || 'security check')} screenshot" />`
      : '';
    const statusText = String(check.status || '').trim().toUpperCase();
    const detailText = String(check.details || '').trim();
    const showStatus = statusText && !['MANUAL', 'SKIPPED'].includes(statusText);
    const detailNote = detailText && !/^Mode=/i.test(detailText) && !/budget=\d+s/i.test(detailText)
      ? `<p class="security-detail">${escapeHtml(check.details)}</p>`
      : '';
    const reportLinkHtml = check.source
      ? `<p class="security-label">REPORT LINK</p><p class="security-link">${check.source}</p>`
      : '';
    return `
      <div class="security-card">
        <p class="security-label">Check point</p>
        <p class="security-title">${escapeHtml(check.label || '-')}</p>
        ${showStatus ? `<p class="security-label">Status</p><p class="security-status">${formatCheckStatus(check.status)}</p>` : ''}
        ${reportLinkHtml}
        ${screenshotHtml ? `<p class="security-label">screen shot</p><div class="security-screenshot">${screenshotHtml}</div>` : ''}
        ${detailNote}
      </div>
    `;
  }).join('');

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Management QA Test Report</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#1f2937;margin:24px;line-height:1.45;background:#fff;}
    h1,h2,h3,h4{color:#0f172a;}
    h1{font-size:26px;margin:0 0 6px;}
    h2{font-size:17px;margin:24px 0 10px;border-bottom:1px solid #d8dee9;padding-bottom:6px;}
    table{width:100%;border-collapse:collapse;margin:12px 0;font-size:12.5px;}
    th,td{border:1px solid #d8dee9;padding:8px;text-align:left;vertical-align:top;}
    th{background:#f8fafc;color:#475467;text-transform:uppercase;font-size:11px;}
    .pass{color:#2a7b2a;font-weight:bold;}
    .fail{color:#c12a2a;font-weight:bold;}
    .manual{color:#9a6700;font-weight:bold;}
    .section{margin-bottom:24px;}
    .meta{margin:6px 0;}
    .cover{border:1px solid #d8dee9;border-left:5px solid #0f766e;border-radius:8px;padding:18px;margin-bottom:18px;}
    .cover .meta{color:#667085;}
    .summary-grid{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;}
    .summary-card{background:#f8fafc;border:1px solid #d8dee9;border-radius:8px;padding:12px;min-width:140px;}
    .summary-card span{display:block;color:#667085;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .summary-card strong{display:block;font-size:22px;color:#111827;}
    .security-cards{display:flex;flex-wrap:wrap;gap:16px;margin-top:20px;}
    .security-card{background:#fff;border:1px solid #ddd;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);padding:16px;width:calc(50% - 16px);box-sizing:border-box;}
    .security-label{font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#666;margin:8px 0 2px;}
    .security-title{font-size:1rem;font-weight:600;color:#222;margin:0;}
    .security-status{font-size:.9rem;margin:0;}
    .security-link a{color:#0b63ff;font-weight:600;}
    .security-screenshot{margin-top:8px;}
    .security-screenshot img{max-width:360px;width:100%;height:auto;border:3px solid #ccc;padding:3px;border-radius:8px;box-sizing:border-box;}
    .security-detail{font-size:.85rem;color:#555;margin-top:12px;}
    .security-placeholder{font-size:.85rem;color:#999;margin:0;}
    .issue-card{border:1px solid #fecdca;background:#fff7f6;border-radius:10px;padding:14px;margin:12px 0;}
    .issue-card h3{font-size:1rem;color:#b42318;margin:0 0 8px;}
    .issue-card h3 span{display:block;font-size:.78rem;color:#666;margin-top:3px;}
    .issue-card p{margin:6px 0;}
    .step-list{margin:6px 0 8px 18px;padding:0;}
    .step-list li{margin:4px 0;line-height:1.45;}
    @media (max-width:768px){.security-card{width:100%;}}
  </style>
</head>
<body>
  <section class="cover">
    <h1>Management QA Test Report</h1>
    <p class="meta"><strong>Offer Name:</strong> ${escapeHtml(report.offer_name || '-')}</p>
    <p class="meta"><strong>Tested URL:</strong> ${escapeHtml(report.url)}</p>
    <p class="meta"><strong>Test Date:</strong> ${escapeHtml(report.created_at)}</p>
    <p class="meta"><strong>Prepared By:</strong> QA Testing Portal</p>
    <p class="meta"><strong>Report ID:</strong> ${escapeHtml(report.id)}</p>
  </section>

  <section class="section">
    <h2>Executive Summary</h2>
    <div class="summary-grid">
      <div class="summary-card"><span>Overall Result</span><strong class="${overallResult === 'PASS' ? 'pass' : 'fail'}">${overallResult}</strong></div>
      <div class="summary-card"><span>Total Rows</span><strong>${report.total_rows}</strong></div>
      <div class="summary-card"><span>Placed Orders</span><strong>${report.placed_orders}</strong></div>
      <div class="summary-card"><span>Failed Orders</span><strong>${report.failed_orders}</strong></div>
    </div>
    <p>${report.failed_orders > 0 ? `${report.failed_orders} order-flow issue(s) require review before production release.` : 'All submitted order rows completed successfully in this automation run.'}</p>
  </section>

  <section class="section">
    <h2>Credentials</h2>
    <p><strong>Browser:</strong> ${escapeHtml(report.browser)}</p>
    <p><strong>Execution Type:</strong> Order Flow Automation</p>
    <p><strong>Browser Mode:</strong> ${escapeHtml(report.headless ? 'Headless' : 'Headed')}</p>
    <p><strong>Total Rows:</strong> ${report.total_rows}</p>
  </section>

  <section class="section">
    <h2>Product Map</h2>
    <p><strong>Primary Offer:</strong> ${escapeHtml(report.offer_name || '-')}</p>
    <p><strong>Landing URL:</strong> ${escapeHtml(report.url)}</p>
    <p><strong>Report Artifact:</strong> ${escapeHtml(report.report_format || 'html').toUpperCase()}</p>
  </section>

  <section class="section">
    <h2>Client Requirements</h2>
    <p>Checkout should load correctly, card information should be entered successfully, and each row should record the final order outcome.</p>
    <p>Required output fields retained in this report: Order ID, Result, Status, Card Type, Card Last 4, and Notes.</p>
  </section>

  <section class="section">
    <h2>Desktop and Mobile</h2>
    <p>This report captures automated checkout execution results. Mobile-specific layout validation is not part of this order-flow run unless separately configured.</p>
  </section>

  <section class="section">
    <h2>Usability</h2>
    <p>Checkout readiness, popup interruption handling, and form completion behavior were evaluated during the automated run.</p>
  </section>

  <section class="section">
    <h2>Tested Rows</h2>
  <table>
    <thead>
      <tr>
        <th>Row</th>
        <th>Email</th>
        <th>Order ID</th>
        <th>Result</th>
        <th>Status</th>
        <th>Card Type</th>
        <th>Card Last 4</th>
        <th>Notes</th>
      </tr>
    </thead>
    <tbody>${hasResults ? rowTable : '<tr><td colspan="8">No execution rows available.</td></tr>'}</tbody>
  </table>
  </section>

  <section class="section">
    <h2>Issues Found</h2>
    ${issueSections || '<p>No issues found.</p>'}
  </section>

  <section class="section">
    <h2>Execution Details</h2>
    <div>${hasResults ? resultSections : '<p>No row-level results were included in this report.</p>'}</div>
  </section>

  <section class="section">
    <h2>Security and Performance</h2>
    <p><strong>Summary:</strong> Total Rows: ${report.total_rows} | Placed Orders: ${report.placed_orders} | Failed Orders: ${report.failed_orders}</p>
    <div class="security-cards">
      ${securityCards || '<p class="security-placeholder">No security/performance checks available.</p>'}
    </div>
  </section>
</body>
</html>`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function normalizeLocatorInput(value) {
  if (!value) {
    return [];
  }

  if (Array.isArray(value)) {
    return value
      .map((item) => String(item ?? '').trim())
      .filter((item) => item.length > 0);
  }

  if (typeof value === 'string') {
    return value
      .split(',')
      .map((item) => item.trim())
      .filter((item) => item.length > 0);
  }

  return [];
}

async function resolveContinueButton(page, locatorValue) {
  const selectors = normalizeLocatorInput(locatorValue);
  for (const selector of selectors) {
    const locator = page.locator(selector);
    if ((await locator.count()) > 0) {
      return locator.first();
    }
  }

  const fallbackButton = page.getByRole('button', {
    name: /continue|next|proceed|submit|place order/i,
  }).first();
  if ((await fallbackButton.count()) > 0) {
    return fallbackButton;
  }

  return page.locator('button[type="submit"]').first();
}

async function disableButtonDecorations(locator) {
  await locator.evaluate((button) => {
    button.classList.remove('animated', 'pulse', 'infinite', 'form_sub');
    button.style.transition = 'none';
    button.style.animation = 'none';
    button.style.setProperty('animation-play-state', 'paused', 'important');
    button.style.setProperty('pointer-events', 'auto', 'important');
  });
}

async function clickButtonSafely(page, locator, opts = {}) {
  const timeout = opts.timeout ?? 15000;
  await disableButtonDecorations(locator);
  await locator.waitFor({ state: opts.state ?? 'visible', timeout });
  try {
    await locator.scrollIntoViewIfNeeded();
  } catch (_) {
    // ignore unstable scroll
  }

  if (!(await locator.isEnabled())) {
    await page.waitForTimeout(800);
  }

  await disableButtonDecorations(locator);

  try {
    await locator.click({ timeout });
    return;
  } catch (error) {
    try {
      await locator.click({ timeout, force: true });
      return;
    } catch (_) {
      // continue to Enter fallback
    }
    try {
      const handle = await locator.elementHandle();
      if (handle) {
        await page.evaluate((el) => el.click(), handle);
        await handle.dispose();
        return;
      }
    } catch (_) {
      // ignore evaluation errors
    }
    await locator.focus();
    await page.keyboard.press('Enter');
  }
}

async function runSingleOrder(page, baseUrl, locators, rowData, screenshotDir, rowIndex, stopSignalPath = '', logPath = '') {
  const cardMeta = classifyCard(rowData.cardNumber);
  const screenshotFileName = `row_${String(rowIndex + 1).padStart(3, '0')}_${rowData.cardNumber.slice(-4)}.png`;
  const screenshotPath = path.join(screenshotDir, screenshotFileName);

  await throwIfStopRequested(stopSignalPath, logPath);
  await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await throwIfStopRequested(stopSignalPath, logPath);
  await page.fill(locators.prospect.firstName, rowData.firstName);
  await page.fill(locators.prospect.lastName, rowData.lastName);
  await page.fill(locators.prospect.phoneNumber, rowData.phoneNumber);
  if (locators.prospect.email && rowData.email) {
    await page.fill(locators.prospect.email, rowData.email);
  }
  await page.fill(locators.prospect.address, rowData.address);
  await page.fill(locators.prospect.city, rowData.city);
  await page.fill(locators.prospect.zipCode, rowData.zipCode);
  await throwIfStopRequested(stopSignalPath, logPath);

  const continueButton = await resolveContinueButton(page, locators.prospect.continueButton);
  await clickButtonSafely(page, continueButton);

  await page.waitForLoadState('domcontentloaded');
  await waitForCheckoutReady(page, locators, 30000);
  await dismissCheckoutPopup(page, locators, screenshotPath, 3000).catch(() => {});
  await throwIfStopRequested(stopSignalPath, logPath);

  const cardEntryState = await enterCardNumberWithPopupHandling(page, locators, rowData.cardNumber, screenshotPath);
  const popupTimeoutMs = Number(locators.popups.noThanksTimeoutMs || 15000);
  if (cardMeta.status === 'decline' && cardEntryState.popupAppearedAfterCard) {
    return {
      email: rowData.email,
      orderId: '',
      status: 'FAILED',
      message: sanitizeMessage('Transaction declined popup appeared after card entry.'),
      cardType: cardMeta.cardType,
      cardLast4: rowData.cardNumber.slice(-4),
      screenshotFileName,
      expectation: cardMeta.status,
    };
  }

  const cardEntryWaitMs = Number(locators.checkout.afterCardNumberWaitMs || 0);
  if (cardEntryWaitMs > 0) {
    await page.waitForTimeout(cardEntryWaitMs);
  }
  await setFieldValue(page, locators.checkout.expMonth, '02');
  await setFieldValue(page, locators.checkout.expYear, '27');
  await page.fill(locators.checkout.cvv, '123');
  await dismissCheckoutPopup(page, locators, screenshotPath, 3000).catch(() => {});
  await throwIfStopRequested(stopSignalPath, logPath);
  await page.click(locators.checkout.submitButton);

  await page.waitForLoadState('domcontentloaded');
  const popupAppearedAfterSubmit = await waitForAnyVisibleSelector(page, getPopupSelectors(locators).popupSelectors, 3000);
  if (popupAppearedAfterSubmit) {
    await takeScreenshot(page, screenshotPath).catch(() => {});
  }
  const popupDismissedAfterSubmit = await dismissCheckoutPopup(page, locators, screenshotPath, popupTimeoutMs);

  const timeoutMs = Number(locators.result.thankYouTimeoutMs || locators.navigation.postSubmitTimeoutMs || 60000);
  let status = 'FAILED';
  let message = 'Order was not confirmed.';
  await throwIfStopRequested(stopSignalPath, logPath);

  try {
    await page.waitForSelector(locators.result.thankYouIndicator, { timeout: timeoutMs });
    status = 'PLACED';
    message = 'Order placed successfully.';
  } catch (error) {
    const currentUrl = page.url();
    if (isThankYouUrl(currentUrl) || /[?&]order_id=/i.test(currentUrl)) {
      status = 'PLACED';
      message = 'Popup was skipped; order confirmed from thank-you URL.';
    } else {
      if (locators.result.failureIndicator) {
        await page.waitForSelector(locators.result.failureIndicator, { timeout: 5000 }).catch(() => {});
      }
      message = 'Declined or not confirmed after submit.';
    }
  }

  await takeScreenshot(page, screenshotPath);
  const orderId = status === 'PLACED' ? await extractOrderId(page, locators.result) : '';
  const popupHandled = cardEntryState.popupDismissed || popupDismissedAfterSubmit;
  if (status === 'PLACED' && popupHandled && message === 'Order placed successfully.') {
    message = 'Order placed successfully.';
  }

  return {
    email: rowData.email,
    orderId,
    status,
    message: sanitizeMessage(message),
    cardType: cardMeta.cardType,
    cardLast4: rowData.cardNumber.slice(-4),
    screenshotFileName,
    expectation: cardMeta.status,
  };
}

async function main() {
  const configPath = process.argv[2];
  if (!configPath) {
    throw new Error('Missing run config path.');
  }

  const config = JSON.parse(await fs.readFile(configPath, 'utf8'));
  const locators = JSON.parse(await fs.readFile(config.locatorsPath, 'utf8'));
  const rows = await parseCsv(config.csvPath);
  const reportDir = config.reportDir;
  const screenshotDir = path.join(reportDir, 'screenshots');
  const logPath = path.join(reportDir, 'runner.log');
  await fs.mkdir(screenshotDir, { recursive: true });
  await fs.writeFile(logPath, `[${timestamp()}] Runner started for ${config.baseUrl}\n`);
  const reportId = config.reportId || path.basename(reportDir);

  const results = [];

  for (let index = 0; index < rows.length; index += 1) {
    const rowNumber = index + 1;
    await throwIfStopRequested(config.stopSignalPath, logPath);

    // Parse row data first so we can log and continue on CSV errors
    let rowData;
    try {
      rowData = getProspectData(rows[index]);
    } catch (err) {
      await appendRunnerLog(logPath, `Row ${rowNumber}: invalid CSV row - ${sanitizeMessage(err.message)}`);
      results.push({
        email: '',
        orderId: '',
        status: 'FAILED',
        message: 'Invalid CSV row: ' + sanitizeMessage(err.message),
        cardType: '',
        cardLast4: '',
        screenshotFileName: '',
        expectation: 'unknown',
      });
      continue;
    }

    // Try to create a browser session; if it fails, record per-row failure and continue
    let session = null;
    let stopMonitorCleanup = () => {};
    try {
      session = await createIsolatedSession(config.browser, config.headless);
      stopMonitorCleanup = createStopMonitor(session, config.stopSignalPath, logPath);
    } catch (error) {
      if (error instanceof StopRequestedError) {
        throw error;
      }
      await appendRunnerLog(logPath, `Row ${rowNumber}: browser launch failed: ${sanitizeMessage(error.message)}`);
      results.push({
        email: rowData.email || '',
        orderId: '',
        status: 'FAILED',
        message: 'Browser unavailable on server: ' + sanitizeMessage(error.message),
        cardType: '',
        cardLast4: rowData.cardNumber ? rowData.cardNumber.slice(-4) : '',
        screenshotFileName: '',
        expectation: 'unknown',
      });
      continue;
    }

    try {
      await appendRunnerLog(logPath, `Row ${rowNumber}: browser opened in private session for ${rowData.email || 'no-email'}.`);

      const result = await runSingleOrder(
        session.page,
        config.baseUrl,
        locators,
        rowData,
        screenshotDir,
        index,
        config.stopSignalPath,
        logPath,
      );
      results.push(result);

      await appendRunnerLog(
        logPath,
        `Row ${rowNumber}: completed with status=${result.status}, orderId=${result.orderId || '-'}, cardLast4=${result.cardLast4 || '-'}.`,
      );
    } catch (error) {
      if (error instanceof StopRequestedError) {
        throw error;
      }

      const fallbackFile = `row_${String(rowNumber).padStart(3, '0')}_error.png`;
      const fallbackPath = path.join(screenshotDir, fallbackFile);
      if (session) {
        await takeScreenshot(session.page, fallbackPath).catch(() => {});
      }
      const rawCard = getStrictField(rows[index], 'card number', false);
      const failureResult = {
        email: getStrictField(rows[index], 'email', false),
        orderId: '',
        status: 'FAILED',
        message: sanitizeMessage(error.message),
        cardType: classifyCard(rawCard).cardType,
        cardLast4: rawCard.slice(-4),
        screenshotFileName: session ? fallbackFile : '',
        expectation: classifyCard(rawCard).status,
      };

      results.push(failureResult);
      await appendRunnerLog(logPath, `Row ${rowNumber}: failed with error=${sanitizeMessage(error.message)}.`);
    } finally {
      stopMonitorCleanup();
      if (session) {
        await session.page.close().catch(() => {});
        await session.context.close().catch(() => {});
        await session.browser.close().catch(() => {});
      }
    }
    await appendRunnerLog(logPath, `Row ${rowNumber}: browser session closed.`);
  }

  const createdAt = formatSystemDateTime(new Date());
  const reportHtmlPath = path.join(reportDir, 'report.html');
  await throwIfStopRequested(config.stopSignalPath, logPath);
  const securityPerformance = await collectSecurityPerformanceChecks(config.baseUrl, screenshotDir, reportId).catch((error) => ([
    {
      label: 'Security and Performance checks',
      status: 'FAIL',
      details: sanitizeMessage(error.message),
      source: '',
    },
  ]));
  await fs.writeFile(reportHtmlPath, buildHtmlReport({
    id: reportId,
    offer_name: config.offerName || '',
    url: config.baseUrl,
    browser: config.browser,
    created_at: createdAt,
    total_rows: results.length,
    placed_orders: results.filter((item) => item.status === 'PLACED').length,
    failed_orders: results.filter((item) => item.status !== 'PLACED').length,
    report_dir: reportDir,
    securityPerformance,
    results: results.map((item) => ({
      ...item,
      result: item.status === 'PLACED' ? 'PASS' : 'FAIL',
      screenshotReportPath: item.screenshotFileName ? `screenshots/${item.screenshotFileName}` : '',
      screenshotPublicPath: item.screenshotFileName ? `/uploads/order_flow_reports/${reportId}/screenshots/${item.screenshotFileName}` : '',
    })),
  }));
  const pdfPath = path.join(reportDir, 'report.pdf');
  const reportArtifact = await renderPdfReport(pdfPath, reportHtmlPath);
  const reportPublicPath = `/uploads/order_flow_reports/${reportId}/${reportArtifact.generated ? 'report.pdf' : 'report.html'}`;

  if (!reportArtifact.generated && reportArtifact.error) {
    await appendRunnerLog(
      logPath,
      `Report artifact fallback engaged. Using HTML because PDF generation failed: ${reportArtifact.error}`,
    );
  }

  const report = {
    id: reportId,
    offer_name: config.offerName || '',
    url: config.baseUrl,
    browser: config.browser,
    headless: Boolean(config.headless),
    created_at: createdAt,
    total_rows: results.length,
    placed_orders: results.filter((item) => item.status === 'PLACED').length,
    failed_orders: results.filter((item) => item.status !== 'PLACED').length,
    report_dir: reportDir,
    report_format: reportArtifact.generated ? 'pdf' : 'html',
    report_error: reportArtifact.error,
    securityPerformance,
    view_url: reportPublicPath,
    download_url: reportPublicPath,
    results: results.map((item) => ({
      ...item,
      result: item.status === 'PLACED' ? 'PASS' : 'FAIL',
      screenshotReportPath: item.screenshotFileName ? `screenshots/${item.screenshotFileName}` : '',
      screenshotPublicPath: item.screenshotFileName ? `/uploads/order_flow_reports/${reportId}/screenshots/${item.screenshotFileName}` : '',
    })),
  };

  await fs.writeFile(path.join(reportDir, 'report.json'), JSON.stringify(report, null, 2));

  process.stdout.write(JSON.stringify({
    success: true,
    report: {
      id: report.id,
      offer_name: report.offer_name,
      url: report.url,
      browser: report.browser,
      created_at: report.created_at,
      total_rows: report.total_rows,
      placed_orders: report.placed_orders,
      failed_orders: report.failed_orders,
      report_format: report.report_format,
      report_error: report.report_error,
      view_url: report.view_url,
      download_url: report.download_url,
      report_dir: report.report_dir,
      log_path: logPath,
    },
  }));
}

main().catch((error) => {
  if (error instanceof StopRequestedError) {
    process.stdout.write(JSON.stringify({
      success: false,
      stopped: true,
      error: error.message,
    }));
    process.exit(0);
  }

  process.stdout.write(JSON.stringify({
    success: false,
    error: error.message,
    stack: error.stack,
  }));
  process.exit(1);
});
