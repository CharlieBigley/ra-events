#!/bin/bash

# Installation package builder for com_ra_events  
# Usage: ./build.sh [version]
# Example: ./build.sh 3.5.1

# Default version if not provided
VERSION="${1:-9.9.9}"

# Navigate to com_ra_events directory
cd "$(dirname "$0")/com_ra_events" || exit 1

echo "Building com_ra_events-${VERSION}.zip..."

# Step 1: Create zip with required folders and script
echo "  - Compressing files..."
zip -r "com_ra_events-${VERSION}.zip" \
  administrator \
  site \
  installer \
  modules \
  plugins \
  media \
  script.php \
  -x "*.DS_Store" \
  ".git/*" \
  "*.zip"

# Step 2: Add manifest from original location to root of zip
echo "  - Adding manifest to zip root..."
zip -j "com_ra_events-${VERSION}.zip" administrator/ra_events.xml

# Step 3: Verify zip was created
if [ -f "com_ra_events-${VERSION}.zip" ]; then
  echo "✓ Package created successfully: com_ra_events-${VERSION}.zip"
  echo "✓ Build complete"
else
  echo "✗ Error: Failed to create zip file"
  exit 1
fi
