/**
 * Verification script for the active Node/Express portal structure.
 */

import { existsSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const baseDir = dirname(fileURLToPath(import.meta.url));

const sections = [
  {
    label: 'Node backend',
    paths: [
      'server.js',
      'package.json',
      'package-lock.json',
      'config/db.js',
      'api/auth.js',
      'api/save-test-report.js',
      'api/get-test-reports.js',
    ],
  },
  {
    label: 'Frontend',
    paths: [
      'frontend/index.html',
      'frontend/view-reports.html',
      'frontend/favicon.svg',
    ],
  },
  {
    label: 'Automation',
    paths: [
      'automation/form-functional-checks.mjs',
      'automation/form-functional-checks.config.json',
      'order_placement/run-order-flow.mjs',
      'order_placement/locators.json',
    ],
  },
  {
    label: 'Data and artifacts',
    paths: [
      'database_schema.sql',
      'reports',
      'uploads',
    ],
  },
  {
    label: 'Documentation and config',
    paths: [
      'ARCHITECTURE.md',
      '.env.example',
      '.gitignore',
      'Dockerfile',
    ],
  },
];

let missingCount = 0;

console.log('\nTesting Portal - Structure Verification\n');

for (const section of sections) {
  console.log(section.label);
  console.log('-'.repeat(section.label.length));

  for (const relativePath of section.paths) {
    const present = existsSync(join(baseDir, relativePath));
    if (!present) {
      missingCount += 1;
    }
    console.log(`${present ? '[ok]' : '[missing]'} ${relativePath}`);
  }

  console.log('');
}

if (missingCount > 0) {
  console.error(`Structure check failed: ${missingCount} required path(s) missing.`);
  process.exitCode = 1;
} else {
  console.log('Structure check passed.');
}
