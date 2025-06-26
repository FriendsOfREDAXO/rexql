import { defineConfig } from 'vite'
import { resolve } from 'path'
import { copyFileSync, readFileSync } from 'fs'
import yaml from 'js-yaml'

// Read version from package.yml (single source of truth)
function getVersionFromPackageYml() {
  try {
    const packageYmlPath = resolve(__dirname, 'package.yml')
    const packageYmlContent = readFileSync(packageYmlPath, 'utf8')
    const packageData = yaml.load(packageYmlContent)
    return packageData.version || '1.0.0'
  } catch (error) {
    console.warn(
      'Warning: Could not read version from package.yml:',
      error.message
    )
    return '1.0.0'
  }
}

const ADDON_VERSION = getVersionFromPackageYml()
console.log(`🏷️  Building rexQL v${ADDON_VERSION}`)

export default defineConfig({
  // Root directory for source files
  root: './assets-src',

  // Base path for assets
  base: './',

  // Build configuration
  build: {
    // Output directory relative to project root
    outDir: '../assets',

    // Empty output directory before building
    emptyOutDir: true,

    // Minify output
    // minify: 'terser',

    // Generate source maps for development
    sourcemap: true,

    // Rollup options
    rollupOptions: {
      input: {
        // Main bundle (CSS + backend JS)
        rexql: resolve(__dirname, 'assets-src/main.js'),

        // Standalone client for frontend apps
        'rexql-client': resolve(__dirname, 'assets-src/rexql-client.js')
      },
      output: {
        // Naming pattern for assets
        assetFileNames: (assetInfo) => {
          const info = assetInfo.name.split('.')
          const ext = info[info.length - 1]
          if (ext === 'css') {
            return `[name].css`
          }
          return `[name].[ext]`
        },
        entryFileNames: '[name].js',
        chunkFileNames: '[name]-[hash].js'
      },
      // External dependencies (don't bundle these)
      external: (id) => {
        // Don't bundle jQuery and Bootstrap as they're provided by REDAXO backend
        return ['jquery', 'bootstrap'].some((dep) => id.includes(dep))
      }
    },

    // Target browsers (modern browsers for backend, wider support for client)
    target: ['es2017', 'chrome60', 'firefox60', 'safari11']
  },

  // CSS preprocessing
  css: {
    postcss: {
      plugins: [
        require('autoprefixer')({
          overrideBrowserslist: [
            '> 1%',
            'last 2 versions',
            'Firefox ESR',
            'not dead'
          ]
        })
      ]
    }
  },

  // Development server (if needed)
  server: {
    host: 'localhost',
    port: 3000,
    open: false
  },

  // Define global constants
  define: {
    __VERSION__: JSON.stringify(ADDON_VERSION)
  },

  // Plugins
  plugins: [
    // Custom plugin to copy static files
    {
      name: 'copy-static-files',
      writeBundle() {
        // Copy test-client.html to output directory
        try {
          copyFileSync(
            resolve(__dirname, 'assets-src/test-client.html'),
            resolve(__dirname, 'assets/test-client.html')
          )
          console.log('✓ Copied test-client.html')
        } catch (err) {
          console.warn('Warning: Could not copy test-client.html:', err.message)
        }
      }
    }
  ]
})
