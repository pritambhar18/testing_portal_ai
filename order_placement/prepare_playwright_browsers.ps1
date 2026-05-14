<#
Prepare Playwright browsers and create a ZIP of the repository for upload to cPanel.
Run this on your local dev machine where you can download large browser binaries.
Usage:
  PowerShell -ExecutionPolicy Bypass -File .\order_placement\prepare_playwright_browsers.ps1 -ZipName testing_portal_with_browsers.zip
#>
param(
  [string]$ZipName = 'testing_portal_with_browsers.zip'
)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$repoRoot = Resolve-Path (Join-Path $scriptDir '..')
$target = Join-Path $scriptDir 'playwright-browsers'

Write-Host "Repository root: $repoRoot"
Write-Host "Downloading Playwright browsers to: $target"

# Ensure folder exists
if (-Not (Test-Path $target)) { New-Item -ItemType Directory -Path $target -Force | Out-Null }

# Point Playwright to the embedded browsers path
$env:PLAYWRIGHT_BROWSERS_PATH = $target

# Install browsers (may take several minutes and hundreds of MB)
Write-Host "Running: npx playwright install chromium firefox webkit msedge chrome"
& npx playwright install chromium firefox webkit msedge chrome

if ($LASTEXITCODE -ne 0) {
  Write-Error "Playwright browser install failed (exit code $LASTEXITCODE). Check output above."
  exit $LASTEXITCODE
}

# Create ZIP of the repo including node_modules and the embedded browser files
$zipPath = Join-Path $repoRoot $ZipName
Write-Host "Creating ZIP: $zipPath (this may take several minutes)"

# Remove existing zip if present
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Compress-Archive -Path (Join-Path $repoRoot '*') -DestinationPath $zipPath -Force -CompressionLevel Optimal

if (Test-Path $zipPath) {
  Write-Host "ZIP created successfully: $zipPath"
  Write-Host "Upload this ZIP to cPanel and extract — ensure order_placement/playwright-browsers and node_modules are present on server."
} else {
  Write-Error "Failed to create ZIP archive."
  exit 1
}
