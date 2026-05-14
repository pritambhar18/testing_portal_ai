# PowerShell Script: Create cPanel Hosting Zip
# This script prepares the testing_portal for cPanel deployment

$stagingPath = 'C:\xampp\htdocs\testing_portal_for_hosting'
$zipPath = 'C:\xampp\htdocs\testing_portal_hosting.zip'
$sourcePath = 'C:\xampp\htdocs\testing_portal'

Write-Host "Creating cPanel deployment package..." -ForegroundColor Green

# Remove if exists
if (Test-Path $stagingPath) { 
    Remove-Item -Recurse -Force $stagingPath 
}
if (Test-Path $zipPath) { 
    Remove-Item -Force $zipPath 
}

# Create directory structure
New-Item -ItemType Directory -Path $stagingPath -Force | Out-Null

# Copy production folders
$foldersToInclude = 'admin', 'api', 'actions', 'assets', 'config', 'helpers', 'reports', 'lib', 'security', 'frontend'

foreach ($folder in $foldersToInclude) {
    $source = Join-Path $sourcePath $folder
    $dest = Join-Path $stagingPath $folder
    if (Test-Path $source) {
        Copy-Item -Path $source -Destination $dest -Recurse -Force
        Write-Host "  ✓ Copied $folder"
    }
}

# Copy root PHP files
$rootFiles = 'index.php'
foreach ($file in $rootFiles) {
    $source = Join-Path $sourcePath $file
    if (Test-Path $source) {
        Copy-Item -Path $source -Destination $stagingPath -Force
        Write-Host "  ✓ Copied $file"
    }
}

# Copy database schema
$schemaSource = Join-Path $sourcePath 'database_schema.sql'
if (Test-Path $schemaSource) {
    Copy-Item -Path $schemaSource -Destination $stagingPath -Force
    Write-Host '  ✓ Copied database_schema.sql'
}

# Create necessary empty directories
New-Item -ItemType Directory -Path (Join-Path $stagingPath 'uploads\reports') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $stagingPath 'uploads\order_flow_reports') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $stagingPath 'uploads\order_flow_stops') -Force | Out-Null
Write-Host '  ✓ Created uploads directories'

# Create .htaccess
$htaccessPath = Join-Path $stagingPath '.htaccess'
@"
# Disable directory listing
Options -Indexes

# Enable mod_rewrite
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
"@ | Set-Content -Path $htaccessPath -Force
Write-Host '  ✓ Created .htaccess'

# Create README
$readmePath = Join-Path $stagingPath 'README_DEPLOYMENT.md'
@"
# Testing Portal - cPanel Deployment Guide

## Quick Setup:

### 1. Upload & Extract
- Extract this zip to your cPanel public_html directory

### 2. Database Setup
- Create database: testing_portal
- Import database_schema.sql via phpMyAdmin in cPanel
- Note: Database credentials for cPanel setup

### 3. Configure Database Connection
- Edit: config/db.php
- Update these variables with your cPanel credentials:
  - ``\$db_host`` = your database host (ask your host if unsure, often 'localhost')
  - `\$db_user`` = your database username
  - `\$db_pass`` = your database password
  - `\$db_name`` = 'testing_portal'

### 4. Set Permissions
- Run in cPanel Terminal or SSH:
  ```
  chmod 755 uploads/
  chmod 755 uploads/reports/
  chmod 755 uploads/order_flow_reports/
  chmod 755 uploads/order_flow_stops/
  ```

### 5. Access the Application
- Navigate to: https://yourdomain.com/admin/login.php
- Tables should be created automatically on first access

## Included Files:
- admin/ - Admin dashboard and login
- api/ - API endpoints
- actions/ - Form action handlers
- config/ - Configuration files
- helpers/ - PHP utility classes
- assets/ - CSS, JavaScript, Images
- reports/ - Report generation
- lib/ - Libraries (FPDF)
- uploads/ - Generated reports storage

## First Run Checklist:
- [ ] Database imported successfully
- [ ] config/db.php updated with correct credentials
- [ ] Permissions set on uploads/ directory
- [ ] Can access admin/login.php
- [ ] Can submit a test form

## Troubleshooting:
- **Database Connection Error**: Check config/db.php credentials
- **Permission Denied**: Run chmod commands on uploads/ directories
- **Table Creation Failed**: Run database_schema.sql manually in phpMyAdmin
- **File Upload Failed**: Check uploads/ directory permissions
- **Cannot Access Pages**: Verify .htaccess compatibility with host

## Production Security:
- Change default admin credentials
- Use strong database passwords
- Enable SSL/HTTPS for your domain
- Keep PHP updated
- Regular database backups
- Monitor error logs

## Support:
Refer to error logs in cPanel for debugging.
Database issues typically show in the application UI.

---
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
"@ | Set-Content -Path $readmePath -Force
Write-Host '  ✓ Created README_DEPLOYMENT.md'

# Create setup helper PHP
$helperPath = Join-Path $stagingPath 'setup-helper.php'
@'
<?php
/**
 * cPanel Setup Helper - Verify Configuration
 * Delete this file after successful setup
 */

echo '<!DOCTYPE html>';
echo '<html><head><title>Setup Helper</title></head><body>';
echo '<h1>Testing Portal - Setup Helper</h1>';

// Check database connection
require_once 'config/db.php';
if ($conn->connect_errno) {
    echo '<p style="color:red;">Database Error: ' . $conn->connect_error . '</p>';
} else {
    echo '<p style="color:green;">✓ Database connected successfully</p>';
}

// Check key directories
$dirs = ['uploads', 'uploads/reports', 'assets'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo '<p style="color:green;">✓ Directory exists: ' . $dir . '</p>';
    } else {
        echo '<p style="color:orange;">⚠ Create directory: ' . $dir . '</p>';
    }
}

echo '<hr>';
echo '<p><a href="admin/login.php">Proceed to Login</a></p>';
echo '<p><small>Delete this file (setup-helper.php) after verification</small></p>';
echo '</body></html>';
?>
'@ | Set-Content -Path $helperPath -Force
Write-Host '  ✓ Created setup-helper.php'

# Create zip file
Write-Host "`nCreating zip archive..." -ForegroundColor Yellow
Compress-Archive -Path $stagingPath -DestinationPath $zipPath -Force

$fileSize = (Get-Item $zipPath).Length
$fileSizeMB = [math]::Round($fileSize / 1MB, 2)

Write-Host "  ✓ Zip created: $zipPath" -ForegroundColor Green
Write-Host "  Size: $fileSizeMB MB"

# Cleanup staging
Remove-Item -Recurse -Force $stagingPath
Write-Host "  ✓ Cleanup complete" -ForegroundColor Green

Write-Host "`n" + ("="*60) -ForegroundColor Green
Write-Host "✅ ZIP READY FOR cPANEL UPLOAD!" -ForegroundColor Green
Write-Host "="*60 -ForegroundColor Green
Write-Host "`nFile: testing_portal_hosting.zip"
Write-Host "Location: C:\xampp\htdocs\"
Write-Host "`nNext Steps:"
Write-Host "1. Download the zip file"
Write-Host "2. Upload to cPanel File Manager"
Write-Host "3. Extract in public_html"
Write-Host "4. Follow README_DEPLOYMENT.md instructions"
Write-Host "`n" + ("="*60) -ForegroundColor Green
