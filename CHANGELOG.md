## 🚀 Changelog

### [1.0.7] - 2025-08-06

#### Added

- Added new fields `parent: article`, `urlProfile: null|string` and `language: language` to type `route` resolver
- Added `urlProfile: null|string` to yform table resolver

#### Fixed

- Reset query and clauses in `ResolverBase` to ensure clean state for each query

### [1.0.6] - 2025-08-05

#### Changed

- Update `route` Schema to use `Int` instead of `ID` to get an integer value
- Cleanup of `route` resolver

#### Fixed

- Call `init` method in `Webhook` class to ensure proper initialization
- Fixed issue with `route` resolver not returning base path for `url` slugs

### [1.0.5] - 2025-08-05

#### Changed

- Verbose logging for permission checks in Context.php

#### Fixed

- Changed dependency version in composer.json
- Fixed issue with undefined `rex_article_clang_id` in ArticleResolver
- Fixed issue with incorrect resolver name `system`

### [1.0.4] - 2025-08-05

#### Changed

- Consistent form HTML structure and CSS

#### Fixed

- Fixed missing dependency in composer.json

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
