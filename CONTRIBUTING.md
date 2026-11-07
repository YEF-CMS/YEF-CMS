# Contributing

1. Develop in Docker (see README). The target PHP version is 8.4.
2. Follow the Yii3 conventions: `declare(strict_types=1)`, PER Coding Style, constructor injection, PSR interfaces.
3. Everything must work without the console: logic lives in `Application` use cases; console commands and admin pages are thin adapters, and every feature needs a web path (shared hosting often has no CLI).
4. Keep the layer boundaries (`deptrac.yaml`): `Domain` knows nothing about files, Twig or Yii.
5. Before sending a pull request run `docker compose run --rm web composer qa` and add an entry to `CHANGELOG.md`.

By contributing you agree that your contribution is licensed under GPL-2.0-or-later.
