# YEF CMS Change Log

## 0.1.0 under development

- Initial project skeleton: Yii3 application structure, architecture layers, quality tooling, CI.
- Hosting environment check (`php bin/yef yef:check`).
- Strict quality gates (Rector, Psalm level 1, PHP-CS-Fixer, Deptrac, PHPUnit) run by `composer qa` and split into separate CI jobs.
- Distribution rules: development-only files are excluded from archives via `export-ignore`.
- Pages are stored in files (one directory per page) with integrity checks; content types come from modules.
- Static HTML is built with Twig and published to the document root; `yef:apply` builds only the pages that changed and takes down removed ones.
- Modules are loaded at run time from `modules/`; they can be enabled and disabled (`yef:modules`, `yef:module:enable`, `yef:module:disable`), and a failing module is skipped instead of breaking the site.
- Admin: sign-in by login and password, first-run setup with a code read from the server's files, page management (list, create, edit, delete) with a form that every content type describes itself. Works with and without URL rewriting.
- Admin: the "Apply changes" screen lists what changed and applies it page by page with a progress bar, so a large site does not hit the execution time limit; the pages list and the dashboard remind about changes that wait.
- Admin: the "Modules" screen shows the installed modules with their titles, versions and states, and enables or disables them.
- Console commands `yef:user:create` and `yef:user:password`.
