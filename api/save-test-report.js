/**
 * api/save-test-report.js
 * POST /api/save-test-report
 * Saves a test report to the database
 */

import { query } from '../config/db.js';

/**
 * Save test report to database
 * @param {Object} req - Express request
 * @param {Object} res - Express response
 */
export async function saveTestReport(req, res) {
  try {
    const { 
      test_link, 
      execution_date, 
      pdf_path, 
      report_html, 
      status,
      report_type,
      run_id,
      offer_name,
      browser_name,
      pass_count,
      fail_count
    } = req.body;

    // Validate required fields
    if (!test_link || !execution_date || (!pdf_path && !report_html)) {
      return res.status(400).json({
        success: false,
        error: 'Missing required fields: test_link, execution_date, and pdf_path or report_html',
      });
    }

    // Validate test_link is a valid URL
    try {
      new URL(test_link);
    } catch (e) {
      return res.status(400).json({
        success: false,
        error: 'Invalid test_link: must be a valid URL',
      });
    }

    // Validate execution_date is valid datetime
    const dateObj = new Date(execution_date);
    if (isNaN(dateObj.getTime())) {
      return res.status(400).json({
        success: false,
        error: 'Invalid execution_date: must be a valid datetime (ISO 8601)',
      });
    }

    // Validate report path is not empty
    if (pdf_path && pdf_path.trim().length === 0) {
      return res.status(400).json({
        success: false,
        error: 'pdf_path cannot be empty',
      });
    }

    // Validate pass_count and fail_count are non-negative integers if provided
    if (pass_count !== undefined && (isNaN(parseInt(pass_count, 10)) || parseInt(pass_count, 10) < 0)) {
      return res.status(400).json({
        success: false,
        error: 'pass_count must be a non-negative integer',
      });
    }

    if (fail_count !== undefined && (isNaN(parseInt(fail_count, 10)) || parseInt(fail_count, 10) < 0)) {
      return res.status(400).json({
        success: false,
        error: 'fail_count must be a non-negative integer',
      });
    }

    // Insert into database
    const sqlQuery = `
      INSERT INTO test_reports 
      (test_link, execution_date, pdf_path, report_html, report_type, run_id, offer_name, browser_name, pass_count, fail_count, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    const results = await query(sqlQuery, [
      test_link,
      execution_date,
      pdf_path || null,
      report_html || null,
      report_type || null,
      run_id || null,
      offer_name || null,
      browser_name || null,
      pass_count !== undefined ? parseInt(pass_count, 10) : 0,
      fail_count !== undefined ? parseInt(fail_count, 10) : 0,
      status || 'Completed',
    ]);

    // Success response
    return res.status(201).json({
      success: true,
      message: 'Report saved successfully',
      report_id: results.insertId,
      data: {
        id: results.insertId,
        test_link,
        execution_date,
        pdf_path,
        report_html,
        report_type: report_type || null,
        run_id: run_id || null,
        offer_name: offer_name || null,
        browser_name: browser_name || null,
        pass_count: pass_count !== undefined ? parseInt(pass_count, 10) : 0,
        fail_count: fail_count !== undefined ? parseInt(fail_count, 10) : 0,
        status: status || 'Completed',
      },
    });

  } catch (error) {
    console.error('Error saving test report:', error);
    return res.status(500).json({
      success: false,
      error: 'Failed to save report',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
}

export default saveTestReport;
