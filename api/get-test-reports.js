/**
 * api/get-test-reports.js
 * GET /api/get-test-reports
 * Fetches all test reports from the database
 */

import { query } from '../config/db.js';

/**
 * Get all test reports from database
 * @param {Object} req - Express request
 * @param {Object} res - Express response
 */
export async function getTestReports(req, res) {
  try {
    const sqlQuery = `
      SELECT
        id,
        test_link,
        execution_date,
        pdf_path,
        created_at
      FROM test_reports
      ORDER BY id DESC
    `;

    const results = await query(sqlQuery);

    // Success response
    return res.status(200).json({
      success: true,
      message: 'Reports fetched successfully',
      count: results.length,
      data: results,
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
