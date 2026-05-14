/**
 * config/db.js
 * MySQL Database Connection Pool Configuration
 * Using mysql2/promise for async/await support
 */

import mysql from 'mysql2/promise.js';

// Create connection pool
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'testing_portal',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelayMs: 0,
});

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
