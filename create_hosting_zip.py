import os
import shutil
import sys
from pathlib import Path

source_dir = Path(r'C:\xampp\htdocs\testing_portal')
staging_dir = Path(r'C:\xampp\htdocs\testing_portal_for_hosting')
zip_output = Path(r'C:\xampp\htdocs\testing_portal_hosting.zip')

print("\n" + "="*50)
print("Creating cPanel Hosting Package")
print("="*50 + "\n")

# Clean up if exists
if staging_dir.exists():
    shutil.rmtree(staging_dir)
if zip_output.exists():
    zip_output.unlink()

# Create staging directory
staging_dir.mkdir(parents=True, exist_ok=True)
print("[OK] Created staging directory")

# Copy production folders
folders_to_copy = ['admin', 'api', 'actions', 'assets', 'config', 'helpers', 'reports', 'lib', 'security', 'frontend']

print("\nCopying production folders:")
for folder in folders_to_copy:
    src = source_dir / folder
    dst = staging_dir / folder
    if src.exists():
        shutil.copytree(src, dst, dirs_exist_ok=True)
        print(f"  [OK] Copied {folder}")

# Copy root files
print("\nCopying root files:")
root_files = ['index.php', 'database_schema.sql']
for file in root_files:
    src = source_dir / file
    dst = staging_dir / file
    if src.exists():
        shutil.copy2(src, dst)
        print(f"  [OK] Copied {file}")

# Create uploads directories
print("\nCreating uploads directories:")
uploads_dirs = ['uploads/reports', 'uploads/order_flow_reports', 'uploads/order_flow_stops']
for dir_path in uploads_dirs:
    full_path = staging_dir / dir_path
    full_path.mkdir(parents=True, exist_ok=True)
    print(f"  [OK] Created {dir_path}")

# Create .htaccess
print("\nCreating .htaccess...")
htaccess_content = """# Disable directory listing
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
</IfModule>
"""
with open(staging_dir / '.htaccess', 'w') as f:
    f.write(htaccess_content)
print("  [OK] Created .htaccess")

# Create README
print("Creating README_DEPLOYMENT.md...")
readme_content = """# Testing Portal - cPanel Deployment Guide

## Quick Setup:

### 1. Upload & Extract
- Extract this zip to your cPanel public_html directory

### 2. Database Setup
- Create database: testing_portal
- Import database_schema.sql via phpMyAdmin in cPanel

### 3. Configure Database Connection
- Edit: config/db.php
- Update variables with your cPanel credentials:
  - $db_host = your database host (usually localhost)
  - $db_user = your database username
  - $db_pass = your database password
  - $db_name = 'testing_portal'

### 4. Set Permissions
Run these commands via SSH/Terminal:
```
chmod 755 uploads/
chmod 755 uploads/reports/
chmod 755 uploads/order_flow_reports/
chmod 755 uploads/order_flow_stops/
```

### 5. Access the Application
- Navigate to: https://yourdomain.com/admin/login.php

## File Structure:
- admin/ - Admin dashboard and login pages
- api/ - REST API endpoints
- actions/ - Form action handlers
- config/ - Database configuration
- helpers/ - PHP utility classes
- assets/ - CSS, JavaScript, Images
- reports/ - Report generation utilities
- lib/ - Libraries (FPDF)
- uploads/ - Generated reports storage

## First Run Checklist:
- [ ] Database imported successfully
- [ ] config/db.php updated with correct credentials
- [ ] Permissions set on uploads/ directory (755)
- [ ] Can access admin/login.php
- [ ] Can view and submit forms

## Troubleshooting:
- **Database Connection Error**: Check config/db.php credentials and host
- **Permission Denied**: Run chmod 755 on uploads/ directories
- **Table Creation Failed**: Run database_schema.sql manually in phpMyAdmin
- **Cannot Access Pages**: Check .htaccess compatibility with your host
- **File Upload Failed**: Check uploads/ directory permissions

## Production Security:
- Change default admin credentials
- Use strong database passwords
- Enable SSL/HTTPS for your domain
- Keep PHP updated to latest version
- Regular database backups recommended
- Monitor error logs in cPanel regularly

## Support:
Refer to cPanel error logs for debugging information.
Database issues typically display error messages in the application.

---
Generated: $(date)
Version: 1.0
"""
with open(staging_dir / 'README_DEPLOYMENT.md', 'w') as f:
    f.write(readme_content)
print("  [OK] Created README_DEPLOYMENT.md")

# Create setup helper
print("Creating setup-helper.php...")
setup_content = """<?php
/**
 * cPanel Setup Helper - Verify Configuration
 * Delete this file after successful setup
 */

echo '<!DOCTYPE html>';
echo '<html><head><title>Setup Helper</title><style>';
echo 'body { font-family: Arial; margin: 20px; }';
echo '.success { color: green; } .error { color: red; }';
echo '</style></head><body>';
echo '<h1>Testing Portal - Setup Helper</h1>';

// Check database connection
require_once 'config/db.php';
if ($conn->connect_errno) {
    echo '<p class="error">[ERROR] Database Error: ' . $conn->connect_error . '</p>';
} else {
    echo '<p class="success">[OK] Database connected successfully</p>';
}

// Check directories
$dirs = ['uploads', 'uploads/reports', 'assets'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo '<p class="success">[OK] Directory exists: ' . $dir . '</p>';
    } else {
        echo '<p class="error">[ERROR] Missing directory: ' . $dir . '</p>';
    }
}

echo '<hr>';
echo '<p><a href="admin/login.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 4px;">Proceed to Login</a></p>';
echo '<p><small>Delete this file (setup-helper.php) after verification</small></p>';
echo '</body></html>';
?>
"""
with open(staging_dir / 'setup-helper.php', 'w', encoding='utf-8') as f:
    f.write(setup_content)
print("  [OK] Created setup-helper.php")

# Create ZIP
print("\nCreating ZIP archive...")
try:
    shutil.make_archive(
        str(zip_output.with_suffix('')),
        'zip',
        str(staging_dir.parent),
        staging_dir.name
    )
    
    if zip_output.exists():
        size_mb = zip_output.stat().st_size / (1024 * 1024)
        print(f"  [OK] ZIP created successfully")
        print(f"    Location: {zip_output}")
        print(f"    Size: {size_mb:.2f} MB")
        
        # Cleanup
        shutil.rmtree(staging_dir)
        print(f"  [OK] Staging directory cleaned up")
        
        print("\n" + "="*50)
        print("[SUCCESS] ZIP READY FOR cPANEL UPLOAD!")
        print("="*50)
        print(f"\nFile: testing_portal_hosting.zip")
        print(f"Location: C:\\xampp\\htdocs\\")
        print(f"\nNext Steps:")
        print("1. Download the zip file")
        print("2. Upload to cPanel File Manager")
        print("3. Extract in public_html")
        print("4. Follow README_DEPLOYMENT.md instructions")
        print("\n" + "="*50 + "\n")
    else:
        print("ERROR: ZIP file creation failed")
        sys.exit(1)
        
except Exception as e:
    print(f"ERROR: {e}")
    sys.exit(1)
