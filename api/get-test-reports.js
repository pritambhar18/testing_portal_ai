/**
 * api/get-test-reports.js
 * GET /api/get-test-reports
 * Fetches all test reports from the database
 */

import { query } from '../config/db.js';
import fs from 'fs/promises';
import { existsSync } from 'fs';
import { dirname, join, resolve } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const rootDir = resolve(__dirname, '..');

function publicPathToAbsolute(publicPath) {
  const normalized = String(publicPath || '').replace(/\\/g, '/').replace(/^(\.\.\/)+/, '/');
  if (!normalized.startsWith('/')) {
    return null;
  }
  const absolute = resolve(rootDir, normalized.replace(/^\/+/, ''));
  return absolute.startsWith(rootDir) ? absolute : null;
}

function reportType(report) {
  const link = `${report.report_html || ''} ${report.pdf_path || ''}`.toLowerCase();
  return link.includes('/automation/results/') ? 'Quick Check' : '88startech';
}

async function readJsonIfExists(pathCandidate) {
  if (!pathCandidate || !existsSync(pathCandidate)) {
    return null;
  }
  try {
    return JSON.parse(await fs.readFile(pathCandidate, 'utf8'));
  } catch {
    return null;
  }
}

async function enrichReport(report) {
  const type = reportType(report);
  const enriched = {
    ...report,
    report_type: report.report_type || type,
    offer_name: report.offer_name || (type === '88startech' ? '88startech' : 'Quick Check'),
    browser_name: report.browser_name || (type === '88startech' ? '-' : 'Chromium'),
    pass_count: report.pass_count ?? null,
    fail_count: report.fail_count ?? null,
  };

  if (type === '88startech') {
    const artifact = publicPathToAbsolute(report.report_html || report.pdf_path);
    const match = String(artifact || '').match(/order_flow_reports[\\/]+([^\\/]+)/);
    const jsonPath = match?.[1] ? join(rootDir, 'uploads', 'order_flow_reports', match[1], 'report.json') : null;
    const payload = await readJsonIfExists(jsonPath);
    if (payload) {
      enriched.offer_name = payload.offer_name || enriched.offer_name;
      enriched.browser_name = payload.browser || enriched.browser_name;
      enriched.pass_count = enriched.pass_count ?? Number(payload.placed_orders || 0);
      enriched.fail_count = enriched.fail_count ?? Number(payload.failed_orders || 0);
    }
    return enriched;
  }

  const htmlPath = publicPathToAbsolute(report.report_html || '');
  const jsonPath = htmlPath ? htmlPath.replace(/\.html$/i, '.json') : publicPathToAbsolute(report.pdf_path || '')?.replace(/\.pdf$/i, '.json');
  const checks = await readJsonIfExists(jsonPath);
  if (Array.isArray(checks)) {
    const passCount = checks.filter((item) => String(item.status || '').toUpperCase() === 'PASS').length;
    const failCount = checks.filter((item) => String(item.status || '').toUpperCase() === 'FAIL').length;
    enriched.pass_count = enriched.pass_count ?? passCount;
    enriched.fail_count = enriched.fail_count ?? failCount;
  }
  return enriched;
}

async function getReportColumns() {
  const columns = await query('SHOW COLUMNS FROM test_reports');
  return new Set(columns.map((column) => column.Field));
}

function selectColumn(columns, columnName, fallback = 'NULL') {
  return columns.has(columnName)
    ? `\`${columnName}\``
    : `${fallback} AS \`${columnName}\``;
}

async function fetchReports() {
  const columns = await getReportColumns();
  const selectedColumns = [
    selectColumn(columns, 'id'),
    selectColumn(columns, 'test_link', "''"),
    selectColumn(columns, 'execution_date', 'CURRENT_TIMESTAMP'),
    selectColumn(columns, 'pdf_path'),
    selectColumn(columns, 'report_html'),
    selectColumn(columns, 'report_type'),
    selectColumn(columns, 'run_id'),
    selectColumn(columns, 'offer_name'),
    selectColumn(columns, 'browser_name'),
    selectColumn(columns, 'pass_count', '0'),
    selectColumn(columns, 'fail_count', '0'),
    selectColumn(columns, 'status', "'Completed'"),
    selectColumn(columns, 'created_at', 'CURRENT_TIMESTAMP'),
  ];
  const orderColumns = [
    columns.has('execution_date') ? '`execution_date` DESC' : null,
    columns.has('id') ? '`id` DESC' : null,
  ].filter(Boolean);

  return await query(`
    SELECT ${selectedColumns.join(', ')}
      FROM test_reports
      ${orderColumns.length ? `ORDER BY ${orderColumns.join(', ')}` : ''}
  `);
}

/**
 * Get all test reports from database
 * @param {Object} req - Express request
 * @param {Object} res - Express response
 */
export async function getTestReports(req, res) {
  try {
    const results = await fetchReports();
    const enrichedResults = await Promise.all(results.map(enrichReport));

    // Success response
    return res.status(200).json({
      success: true,
      message: 'Reports fetched successfully',
      count: enrichedResults.length,
      data: enrichedResults,
    });

  } catch (error) {
    console.error('Error fetching test reports:', error);
    return res.status(500).json({
      success: false,
      error: 'Failed to fetch reports',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
}

export default getTestReports;
