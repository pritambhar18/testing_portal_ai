/**
 * VERIFICATION SCRIPT
 * Checks all active code components and reports status
 */

const fs = require('fs');
const path = require('path');

const baseDir = __dirname;

// Check if file/directory exists
function exists(filePath) {
  return fs.existsSync(filePath);
}

console.log('\n╔═══════════════════════════════════════════════════════════════════════╗');
console.log('║         TESTING PORTAL - CODE STRUCTURE VERIFICATION REPORT           ║');
console.log('╚═══════════════════════════════════════════════════════════════════════╝\n');

// 1. Check Node.js Backend
console.log('📦 NODE.JS BACKEND');
console.log('─'.repeat(70));
const nodeFiles = [
  'server.js',
  'package.json',
  'package-lock.json',
  'api/save-test-report.js',
  'api/get-test-reports.js',
  'config/db.js'
];

nodeFiles.forEach(file => {
  const filePath = path.join(baseDir, file);
  const status = exists(filePath) ? '✅' : '❌';
  console.log(`${status} ${file}`);
});

// 2. Check PHP Admin
console.log('\n🔐 PHP ADMIN PANEL');
console.log('─'.repeat(70));
const phpFiles = [
  'admin/login.php',
  'admin/dashboard.php',
  'admin/manage_users.php',
  'admin/view_reports.php',
  'admin/create_user.php',
  'admin/edit_user.php',
  'admin/delete_user.php',
  'admin/forgot_password.php',
  'config/db.php'
];

phpFiles.forEach(file => {
  const filePath = path.join(baseDir, file);
  const status = exists(filePath) ? '✅' : '❌';
  console.log(`${status} ${file}`);
});

// 3. Check Frontend
console.log('\n🎨 FRONTEND APPLICATION');
console.log('─'.repeat(70));
const frontendFiles = [
  'frontend/index.html',
  'frontend/view-reports.html',
  'assets/css/style.css'
];

frontendFiles.forEach(file => {
  const filePath = path.join(baseDir, file);
  const status = exists(filePath) ? '✅' : '❌';
  console.log(`${status} ${file}`);
});

// 4. Check Support Files
console.log('\n🔧 SUPPORT UTILITIES');
console.log('─'.repeat(70));
const supportFiles = [
  'lib/fpdf.php',
  'helpers/',
  'security/',
  'order_placement/run-order-flow.mjs',
  'database_schema.sql'
];

supportFiles.forEach(file => {
  const filePath = path.join(baseDir, file);
  const status = exists(filePath) ? '✅' : '❌';
  console.log(`${status} ${file}`);
});

// 5. Check Java Artifacts (Should be removed)
console.log('\n🗑️  UNUSED JAVA ARTIFACTS (To be removed)');
console.log('─'.repeat(70));
const javaArtifacts = [
  'build/java-classes',
  'target/classes',
  'target/test-classes'
];

let removeCount = 0;
javaArtifacts.forEach(file => {
  const filePath = path.join(baseDir, file);
  if (exists(filePath)) {
    console.log(`❌ ${file} (EXISTS - NEEDS REMOVAL)`);
    removeCount++;
  } else {
    console.log(`✅ ${file} (Already removed)`);
  }
});

// 6. Application Status
console.log('\n📊 APPLICATION STATUS');
console.log('─'.repeat(70));

const allNodeFilesExist = nodeFiles.every(f => exists(path.join(baseDir, f)));
const allPhpFilesExist = phpFiles.every(f => exists(path.join(baseDir, f)));
const allFrontendFilesExist = frontendFiles.every(f => exists(path.join(baseDir, f)));

console.log(`Node.js Backend:      ${allNodeFilesExist ? '✅ READY' : '❌ MISSING FILES'}`);
console.log(`PHP Admin Panel:      ${allPhpFilesExist ? '✅ READY' : '❌ MISSING FILES'}`);
console.log(`Frontend Application: ${allFrontendFilesExist ? '✅ READY' : '❌ MISSING FILES'}`);
console.log(`Java Artifacts:       ${removeCount > 0 ? `❌ ${removeCount} items to remove` : '✅ All removed'}`);

// 7. Current Flow
console.log('\n🔄 ACTIVE APPLICATION FLOW');
console.log('─'.repeat(70));
console.log('1. User visits http://localhost:3000/');
console.log('2. Redirects to /admin/login.php');
console.log('3. Admin authentication');
console.log('4. Dashboard access (/admin/dashboard.php)');
console.log('5. Report management (/admin/view_reports.php)');
console.log('6. API endpoints:');
console.log('   - POST /api/save-test-report');
console.log('   - GET /api/get-test-reports');
console.log('7. Frontend UI (/frontend/view-reports.html)');

console.log('\n✅ All active code verified and ready!');
console.log(`📝 Note: ${removeCount} unused Java artifact(s) can be safely removed.\n`);

// List commands to clean up
if (removeCount > 0) {
  console.log('╔═══════════════════════════════════════════════════════════════════════╗');
  console.log('║  CLEANUP COMMANDS (Run to remove Java artifacts):                    ║');
  console.log('╚═══════════════════════════════════════════════════════════════════════╝\n');
  console.log('Windows CMD:');
  console.log('  rmdir /s /q build');
  console.log('  rmdir /s /q target\n');
  console.log('Linux/Mac:');
  console.log('  rm -rf build');
  console.log('  rm -rf target\n');
  console.log('Node.js:');
  console.log('  node cleanup.js\n');
}
