#!/bin/bash

# Cleanup script for Java artifacts
echo "╔════════════════════════════════════════════════════╗"
echo "║   Cleaning Up Unused Java Build Artifacts         ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""

# Remove build directory
if [ -d "build" ]; then
  rm -rf build
  echo "✓ Removed: ./build"
else
  echo "- Skip (not found): ./build"
fi

# Remove target directory
if [ -d "target" ]; then
  rm -rf target
  echo "✓ Removed: ./target"
else
  echo "- Skip (not found): ./target"
fi

echo ""
echo "✅ Cleanup Complete!"
echo "All active code (Node.js/PHP) remains intact."
echo ""
