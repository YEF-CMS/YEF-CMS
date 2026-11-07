# YEF CMS

**Yet another file CMS** — a PHP CMS without a database. Content lives in files; every time it is added or edited, the final static HTML is generated and served to visitors directly by the web server.

> Work in progress: early development, not usable yet.

## Highlights

- No database: data is stored in files behind repository interfaces, so the storage format can be replaced without touching the rest of the code.
- Visitors get static HTML; PHP is not involved in serving pages.
- Runs on ordinary shared hosting: PHP 8.4 only, `.htaccess` is optional, no `location` tuning, cron is optional.
- Drupal-like model: Twig layouts with regions, blocks placed into regions.
- Modules: extend content types, blocks, admin pages and more; installed from GitHub straight from the admin panel.
- Built on [Yii3](https://github.com/yiisoft) packages and PSR standards.

## Requirements

- PHP 8.4+ with `ctype`, `json`, `mbstring` (recommended: `fileinfo`, `intl`, `zip`, `gd`, `curl`).
- [Composer](https://getcomposer.org) 2 (a button in most hosting panels, or SSH).
- Check a hosting with `php bin/yef yef:check`.

## Installation

> Not usable yet: the package is not published and the installer is not implemented.

```bash
composer create-project yef-cms/yef-cms my-site --no-dev
```

No SSH? Upload the project files over FTP and run `composer install --no-dev` from your hosting panel.

## Local development

Docker is used **for local development only**; production does not need it.

```bash
docker compose up -d web                      # http://localhost:8080/admin/  (.htaccess enabled)
docker compose --profile nohtaccess up -d     # http://localhost:8081/admin/  (.htaccess disabled)
docker compose run --rm web composer install
docker compose run --rm web composer qa       # cs-fixer, psalm, deptrac, dependency analyser, phpunit
docker compose run --rm web php bin/yef yef:check
```

The first `composer install` can take several minutes on Windows because of the bind-mounted `vendor/`.
Run every PHP/Composer command inside the container (the host PHP is not used).

## Project layout

```
bin/yef            console entry point
config/            yiisoft/config groups (params, di, routes, ...)
public/            development document root (admin/ entry point; generated static files later)
src/               Yef\ namespace, one directory per architecture layer (see deptrac.yaml)
tests/             PHPUnit tests
var/               runtime data (caches, logs, locks), never served
docker/            local development environment
```

## License

[GPL-2.0-or-later](LICENSE). Provided "as is", without warranty of any kind.
