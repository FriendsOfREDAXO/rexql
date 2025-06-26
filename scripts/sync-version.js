#!/usr/bin/env node

/**
 * Sync version from package.yml to package.json
 * This keeps package.yml as the single source of truth
 */

const fs = require('fs')
const path = require('path')
const yaml = require('js-yaml')

function syncVersion() {
  try {
    // Read version from package.yml
    const packageYmlPath = path.join(__dirname, '..', 'package.yml')
    const packageYmlContent = fs.readFileSync(packageYmlPath, 'utf8')
    const packageData = yaml.load(packageYmlContent)
    const version = packageData.version

    if (!version) {
      throw new Error('No version found in package.yml')
    }

    // Read package.json
    const packageJsonPath = path.join(__dirname, '..', 'package.json')
    const packageJsonContent = fs.readFileSync(packageJsonPath, 'utf8')
    const packageJson = JSON.parse(packageJsonContent)

    // Update version if different
    if (packageJson.version !== version) {
      packageJson.version = version

      // Write back to package.json
      fs.writeFileSync(
        packageJsonPath,
        JSON.stringify(packageJson, null, 2) + '\n'
      )

      console.log(`✓ Synced version to ${version} in package.json`)
    } else {
      console.log(`✓ Version ${version} already synced`)
    }
  } catch (error) {
    console.error('Error syncing version:', error.message)
    process.exit(1)
  }
}

// Run if called directly
if (require.main === module) {
  syncVersion()
}

module.exports = { syncVersion }
