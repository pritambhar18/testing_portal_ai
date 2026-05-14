const fs = require('fs');
const path = require('path');

// Directories to remove
const dirsToRemove = [
  path.join(__dirname, 'build'),
  path.join(__dirname, 'target')
];

function removeDirectory(dirPath) {
  if (fs.existsSync(dirPath)) {
    fs.rmSync(dirPath, { recursive: true, force: true });
    console.log(`✓ Removed: ${dirPath}`);
    return true;
  } else {
    console.log(`- Skip (not found): ${dirPath}`);
    return false;
  }
}

console.log('╔════════════════════════════════════════════════════╗');
console.log('║   Cleaning Up Unused Java Build Artifacts         ║');
console.log('╚════════════════════════════════════════════════════╝\n');

let removedCount = 0;
dirsToRemove.forEach(dir => {
  if (removeDirectory(dir)) {
    removedCount++;
  }
});

console.log(`\n✅ Cleanup Complete! Removed ${removedCount} directories.`);
console.log('All active code (Node.js/PHP) remains intact.\n');
