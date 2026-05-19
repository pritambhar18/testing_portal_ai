/**
 * config/db.js
 * MySQL Database Connection Pool Configuration
 * Using mysql2/promise for async/await support
 */

import 'dotenv/config';
import mysql from 'mysql2/promise.js';

const poolConfig = {
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'testing_portal',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  enableKeepAlive: true,
};

if (process.env.DATABASE_URL) {
  poolConfig.uri = process.env.DATABASE_URL;
}

if (process.env.DB_SSL === 'true') {
  poolConfig.ssl = {
    rejectUnauthorized: process.env.DB_SSL_REJECT_UNAUTHORIZED !== 'false',
  };
}

// Create connection pool
const pool = mysql.createPool(poolConfig);

const schemaRepairs = {
  test_reports: [
    ['report_type', "VARCHAR(50) DEFAULT NULL COMMENT 'order-flow, quick-check, 88startech, or Quick Check.'"],
    ['run_id', "VARCHAR(255) DEFAULT NULL COMMENT 'Automation run id such as ofr_* or quick_*.'"],
    ['offer_name', "VARCHAR(255) DEFAULT NULL COMMENT 'Offer or report display name.'"],
    ['browser_name', "VARCHAR(100) DEFAULT NULL COMMENT 'Browser used by the automation where available.'"],
    ['pass_count', "INT NOT NULL DEFAULT 0 COMMENT 'Passed checks/orders count, if known.'"],
    ['fail_count', "INT NOT NULL DEFAULT 0 COMMENT 'Failed checks/orders count, if known.'"],
    ['updated_at', 'DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
  ],
  automation_logs: [
    ['finished_at', 'DATETIME NULL DEFAULT NULL'],
    ['created_by', "VARCHAR(255) DEFAULT NULL COMMENT 'Email from JWT payload when available.'"],
  ],
};

async function columnExists(connection, tableName, columnName) {
  const [rows] = await connection.execute(
    `SELECT 1
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1`,
    [tableName, columnName],
  );
  return rows.length > 0;
}

async function tableExists(connection, tableName) {
  const [rows] = await connection.execute(
    `SELECT 1
       FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
      LIMIT 1`,
    [tableName],
  );
  return rows.length > 0;
}

async function ensureColumn(connection, tableName, columnName, definition) {
  if (await columnExists(connection, tableName, columnName)) {
    return;
  }

  await connection.execute(`ALTER TABLE \`${tableName}\` ADD COLUMN \`${columnName}\` ${definition}`);
  console.log(`[DB] Added missing column ${tableName}.${columnName}`);
}

/**
 * Repair additive schema drift from older installs.
 * Existing deployments commonly have the base tables but not the newer
 * report metadata columns; missing columns make the dashboard API return 500.
 */
export async function ensurePortalSchema() {
  const connection = await pool.getConnection();
  try {
    for (const [tableName, columns] of Object.entries(schemaRepairs)) {
      if (!(await tableExists(connection, tableName))) {
        console.warn(`[DB] Table ${tableName} is missing. Import database_schema.sql to create the base schema.`);
        continue;
      }

      for (const [columnName, definition] of columns) {
        try {
          await ensureColumn(connection, tableName, columnName, definition);
        } catch (error) {
          console.warn(`[DB] Could not add ${tableName}.${columnName}: ${error.message}`);
        }
      }
    }
  } finally {
    connection.release();
  }
}

/**
 * Get connection from pool
 * @returns {Promise<Connection>} Database connection
 */
export async function getConnection() {
  return await pool.getConnection();
}

/**
 * Execute query with parameters
 * @param {string} query - SQL query
 * @param {Array} params - Query parameters
 * @returns {Promise<Array>} Query results
 */
export async function query(sql, params = []) {
  const connection = await pool.getConnection();
  try {
    const [results] = await connection.execute(sql, params);
    return results;
  } finally {
    connection.release();
  }
}

export default pool;
