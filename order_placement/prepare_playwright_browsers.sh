#!/usr/bin/env bash
# Prepare Playwright browsers into ./playwright-browsers for packaging
set -euo pipefail
cd "$(dirname "$0")"
TARGET_DIR="$(pwd)/playwright-browsers"
mkdir -p "$TARGET_DIR"
export PLAYWRIGHT_BROWSERS_PATH="$TARGET_DIR"
# Install all browsers (chromium, firefox, webkit) and channels (chrome, msedge)
# Note: This downloads hundreds of MBs. Run on a development machine with good bandwidth.
npx playwright install chromium firefox webkit msedge chrome

echo "Browsers downloaded to: $TARGET_DIR"