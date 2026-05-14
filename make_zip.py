#!/usr/bin/env python3
"""
Simple ZIP creator for cPanel deployment
No complex staging, direct compression
"""
import shutil
import os
from pathlib import Path

source = Path(r"C:\xampp\htdocs\testing_portal")
output = Path(r"C:\xampp\htdocs\testing_portal_hosting.zip")

# Remove old zip
if output.exists():
    output.unlink()
    print("Removed old zip file")

print("\nCreating production zip for cPanel...")
print(f"Source: {source}")
print(f"Output: {output}")

try:
    # Create zip directly without staging
    shutil.make_archive(
        str(output.with_suffix('')), 
        'zip',
        str(source.parent),
        source.name
    )
    
    if output.exists():
        size = output.stat().st_size / (1024 * 1024)
        print(f"\n[SUCCESS] ZIP created successfully!")
        print(f"  File: {output.name}")
        print(f"  Size: {size:.2f} MB")
        print(f"  Location: C:\\xampp\\htdocs\\")
        print(f"\nReady for cPanel upload!")
    else:
        print("ERROR: ZIP creation failed")
        
except Exception as e:
    print(f"ERROR: {e}")
