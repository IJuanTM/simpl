# Docker

The recommended way to run this project locally - a PHP + Apache container (and, once the `db` add-on is installed, MariaDB), with no WAMP/XAMPP/local PHP/MySQL/Composer required. WAMP/XAMPP/Apache remains available as a manual alternative, see the main [README](../README.md#step-4-set-up-your-localhost).

## Prerequisites

* [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or another Docker Compose v2-compatible engine)

## Usage

```bash
composer docker:up
```

(equivalent to `docker compose up -d --build`, see [Shorthand commands](#shorthand-commands))

The first run builds the image, runs `composer install` inside it, and starts the container(s). The site is available at `http://localhost/` and at whatever hostname `src/.env`'s `APP_URL` points to (see [Matching your real APP_URL](#matching-your-real-app_url) to make that resolve).

`src/` is bind-mounted, so PHP edits reflect immediately - rebuild only after changing `composer.json`, `composer.lock`, or the Dockerfile. After a `composer.json` change, run `composer docker:install`.

Migrations, seeding, and other Composer commands work the same as a manual setup, just inside the container:

```bash
composer docker:migrate
composer docker:seed:fresh
```

Sass/TypeScript (`npm run build` / `npm run dev`) still run on the host as usual - `npm run dev`'s browser-sync just proxies whatever URL the app is served at.

## Shorthand commands

Plain Composer scripts (same mechanism as `composer test`/`composer stan`), not a `docker` CLI wrapper - aliasing `docker` itself would shadow the real CLI.

| Command                          | Runs                                              |
|----------------------------------|---------------------------------------------------|
| `composer docker:up`             | `docker compose up -d --build`                    |
| `composer docker:down`           | `docker compose down`                             |
| `composer docker:sh`             | `docker compose exec app bash`                    |
| `composer docker:install`        | `docker compose run --rm app composer install`    |
| `composer docker:test`           | `docker compose exec app composer test`           |
| `composer docker:stan`           | `docker compose exec app composer stan`           |
| `composer docker:key:regenerate` | `docker compose exec app composer key:regenerate` |

Once the `db` add-on is installed:

| Command                            | Runs                                                |
|------------------------------------|-----------------------------------------------------|
| `composer docker:migrate`          | `docker compose exec app composer migrate`          |
| `composer docker:migrate:fresh`    | `docker compose exec app composer migrate:fresh`    |
| `composer docker:migrate:rollback` | `docker compose exec app composer migrate:rollback` |
| `composer docker:seed`             | `docker compose exec app composer seed`             |
| `composer docker:seed:fresh`       | `docker compose exec app composer seed:fresh`       |
| `composer docker:cron:test`        | `docker compose exec app composer cron:test`        |

`composer docker:up` always passes `--build` - a fast no-op once nothing's changed, but never a stale image.

## Configuration

Copy `docker/.env.example` to `.env` in the project root (separate from `src/.env`) to override any of these:

| Variable          | Default | Purpose                                                  |
|-------------------|---------|----------------------------------------------------------|
| `PHP_VERSION`     | `8.5`   | PHP version used to build the `app` image                |
| `APP_PORT`        | `80`    | Host port mapped to the container's Apache               |
| `MARIADB_VERSION` | `12.3`  | MariaDB image tag (only used once `db` is installed)     |
| `DB_PORT`         | `3307`  | Host port mapped to the container's MariaDB              |
| `DEV`             | `true`  | Overrides `src/.env`'s `DEV` flag as a container env var |

`DB_PORT` defaults away from a typical local MySQL/MariaDB install's `3306`. Only override `APP_PORT` if something else on your machine already owns port 80.

## Matching your real APP_URL

The container answers any hostname on port 80/`APP_PORT`, so once the host in `src/.env`'s `APP_URL` resolves to `127.0.0.1`, the site is reachable at that exact URL - not just `localhost`. Add a hosts file entry (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on macOS/Linux):

```
127.0.0.1  myproject.test
```

Replace `myproject.test` with your actual `APP_URL` host. If `APP_PORT` isn't `80`, browse to `http://myproject.test:<port>/` instead.

## This is a template, not a site

`APP_NAME`/`APP_URL`/`APP_HOST` are placeholders that only `simpl-installer` substitutes, at scaffold time - running Docker directly against this repo's own checkout leaves them literal, since there's no real site here yet. Use Docker the same way as `npm run dev`: on a real installed site (`npx @ijuantm/simpl-install`), not on this template repo itself.

## Why `.env` doesn't need editing

Compose sets `DB_SERVER=db` as a real container environment variable, which phpdotenv's `createImmutable()` never overwrites - so `src/.env`'s `DB_SERVER=localhost` default is left untouched. `DB_NAME`/`DB_USERNAME`/`DB_PASSWORD` come straight from the bind-mounted `src/.env`; `migrate:fresh` creates the database itself. If the app can't reach a database that's clearly up, check `docker compose exec app php -i | grep variables_order` includes `E` - without it, `docker/php/simpl.ini`'s `variables_order` never populates `$_ENV`, which `database.php` reads directly.
