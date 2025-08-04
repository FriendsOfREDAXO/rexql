## 🚀 Changelog

### [1.0.3] - 2025-08-04

#### Added

- Configurable Cache TTL in configuration
- Extended relations for `articles` and `slices` (`language`)
- CHANGELOG.md for better tracking of changes

#### Changed

- Examples in playground.php to include fields (`name`, `slug`)

#### Fixed

- Fixed overwriting of SDL definitions in schema.graphql
- Fixed issue with multiple instances of the same joins in ResolverBase

### [1.0.2] - 2025-08-03

#### Fixed

- Checked code with PHPStan and fixed issues

### [1.0.1] - 2025-08-02

#### Changed

- Updated tooling deps in package.json

#### Fixed

- Cast server variables using rex_request::server() to string

### [1.0.0] - 2025-08-01

#### Added

- First stable release
- Support for all core and yform tables
- Basic schema with SDL definition

#### Changed

- Complete refactor
- Improved performance through optimized database queries
- Expanded documentation

#### Fixed

- Bug fixes and stability improvements
- Resolved issues with relation handling
