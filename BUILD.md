# rexQL Assets Build

This directory contains the Vite.js build configuration for rexQL addon assets.

## Structure

```
assets-src/          # Source files
├── main.js          # Main entry point (imports CSS and backend JS)
├── rexql.js         # Backend JavaScript functionality
├── rexql-client.js  # Standalone client for frontend apps
├── rexql.css        # Styles for backend UI
└── test-client.html # Test client HTML file

assets/              # Built files (generated)
├── rexql.js         # Built backend bundle
├── rexql.css        # Processed CSS
├── rexql-client.js  # Built standalone client
└── test-client.html # Copied HTML file

scripts/             # Build scripts
├── sync-version.js  # Syncs version from package.yml to package.json
└── get-version.js   # Extracts version from package.yml
```

## Development

### Install dependencies

```bash
npm install
```

### Build assets

```bash
npm run build
```

### Watch for changes (development)

```bash
npm run dev
# or
npm run watch
```

## Build Process

The build process uses Vite.js to:

1. **Bundle JavaScript**: Combines and minifies JS files
2. **Process CSS**: Applies PostCSS with Autoprefixer
3. **Copy static files**: Copies HTML and other static assets
4. **External dependencies**: jQuery and Bootstrap are marked as external (provided by REDAXO)

## Output

The build generates optimized assets in the `assets/` directory:

- **rexql.js**: Main backend functionality (includes CSS imports)
- **rexql.css**: Processed and prefixed CSS
- **rexql-client.js**: Standalone client for frontend applications
- **test-client.html**: Test client for API testing

## Configuration

- **vite.config.js**: Main Vite configuration
- **postcss.config.js**: PostCSS configuration for CSS processing
- **package.json**: npm scripts and dependencies

## Notes

- The build targets modern browsers (ES2017+)
- CSS is processed with Autoprefixer for browser compatibility
- External dependencies (jQuery, Bootstrap) are not bundled
- Source maps are disabled for production builds
- Files are minified using Terser

## Version Management

**Single Source of Truth**: The version is maintained in `package.yml` (REDAXO standard) and automatically synced to `package.json` and injected into the build.

- ✅ **Edit version in**: `package.yml` only
- ✅ **Auto-synced to**: `package.json`, build artifacts
- ✅ **Available in JS as**: `__VERSION__` constant

### Workflow

1. Update version in `package.yml`
2. Run `npm run build` (automatically syncs version)
3. Version is injected into built assets

### Scripts

```bash
npm run version:sync       # Just sync version without building
```

## Development
