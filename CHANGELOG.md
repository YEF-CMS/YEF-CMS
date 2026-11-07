# YEF CMS Change Log

## 0.1.0 under development

- Initial project skeleton: Yii3 application structure, architecture layers, quality tooling, CI.
- Hosting environment check (`php bin/yef yef:check`).
- Strict quality gates (Rector, Psalm level 1, PHP-CS-Fixer, Deptrac, PHPUnit) run by `composer qa` and split into separate CI jobs.
- Distribution rules: development-only files are excluded from archives via `export-ignore`.
