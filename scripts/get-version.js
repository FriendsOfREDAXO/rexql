#!/usr/bin/env node

/**
 * Extract version from package.yml and set as environment variable
 * This ensures the REDAXO package.yml is the single source of truth for version
 */

const fs = require('fs')
const yaml = require('js-yaml')
const path = require('path')

try {
  // Read package.yml
  const packageYmlPath = path.join(__dirname, 'package.yml')
  const packageYmlContent = fs.readFileSync(packageYmlPath, 'utf8')

  // Parse YAML
  const packageData = yaml.load(packageYmlContent)

  // Extract version
  const version = packageData.version

  if (!version) {
    console.error('Error: No version found in package.yml')
    process.exit(1)
  }

  // Set environment variable for Vite build
  process.env.ADDON_VERSION = version

  console.log(`✓ Extracted version ${version} from package.yml`)

  // Export for use in other scripts
  module.exports = { version }
} catch (error) {
  console.error('Error reading package.yml:', error.message)
  process.exit(1)
}
