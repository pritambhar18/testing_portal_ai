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
