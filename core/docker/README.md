# Docker

The recommended way to run this project locally - a PHP + Apache container (and, once the `db` add-on is installed, MariaDB), with no WAMP/XAMPP/local PHP/MySQL/Composer required. WAMP/XAMPP/Apache remains available as a manual alternative, see [Step 4](https://github.com/IJuanTM/simpl#step-4-set-up-your-localhost) of the main README.

## Prerequisites

* [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or another Docker Compose v2-compatible engine)

## Usage

Bring the container (s) up with plain Docker Compose from the project root:

```bash
docker compose up -d --build
```

The first run builds the image, runs `composer install` inside it, and starts the container (s). The site is served over HTTPS - available at `https://localhost/` and at whatever hostname `src/.env`'s `APP_URL` points to (see [Matching your real APP_URL](#matching-your-real-app_url) to make that resolve, and [HTTPS](#https) for the certificate warning you'll see).

`src/` is bind-mounted, so PHP edits reflect immediately - rebuild only after changing `composer.json`, `composer.lock`, or the Dockerfile. After a `composer.json` change, run `npm run docker:composer -- install`.

Migrations, seeding, and other Composer commands work the same as a manual setup, just run through `docker compose exec` instead of `composer` directly - as the `www-data` user Apache runs as, so anything they write stays owned by you on the host (see [Linux file ownership](#linux-file-ownership)):

```bash
docker compose exec -u www-data app composer migrate
docker compose exec -u www-data app composer seed:fresh
```

Two shortcuts exist for the ones you'll type most - `npm run docker:sh` opens a shell in the container, and `npm run docker:composer -- <cmd>` runs any Composer command inside it (`npm run docker:composer -- migrate`, `npm run docker:composer -- test`, ...). The `--` tells npm everything after it is an argument to pass through, not an npm option. Both go through npm rather than Composer so the host needs no PHP or Composer, only the Node.js it already uses for the Sass/TypeScript build.

Sass/TypeScript (`npm run build` / `npm run dev`) still run on the host as usual - `npm run dev`'s browser-sync proxies whatever URL the app is served at, and matches its own port (`:3000` by default) to the same scheme, HTTPS or plain HTTP. See [HTTPS](#https) below for how it picks a certificate when that's HTTPS - which it is by default with Docker, but not with a plain HTTP WAMP/XAMPP setup.

## HTTPS

The container generates a self-signed certificate at build time, covering `localhost`, `127.0.0.1`, and any `*.test`/`*.local` hostname - port 80 (`APP_PORT`) just redirects to port 443 (`APP_SSL_PORT`), where the site is actually served. Browsers will show a "not secure"/untrusted-certificate warning since the certificate isn't signed by a real CA - this is expected for local development, click through it. A hostname outside `*.test`/`*.local` still works, just with an added hostname-mismatch warning on top - and even a `*.test` hostname mismatches once it has more than one label before `.test` (e.g. `myproject.test` matches, `myproject.simpl.test` doesn't - `*.` only ever covers one label). See below if you'd rather not see either warning.

### Removing the warning entirely with mkcert

[mkcert](https://github.com/FiloSottile/mkcert) makes a local certificate authority and installs it into your OS's and browsers' trust stores, then issues real certificates signed by it - the same mechanism a real CA uses, just local and only trusted by your own machine. This is a one-time, per-machine setup step, and it's optional - skip it if you're fine clicking through the warning, or if you're on a shared/managed machine where installing a root CA isn't appropriate.

1. Install mkcert: `choco install mkcert` or `scoop install mkcert` (Windows), `brew install mkcert` (macOS), or your distro's package / the [release binaries](https://github.com/FiloSottile/mkcert/releases) (Linux).
2. Run `mkcert -install` once. This installs mkcert's local CA into your system and browser trust stores - the step that actually removes the warning, and the reason this can't be automated from inside the container (it needs to modify your host machine, not the image).
3. Generate a certificate for your actual dev hostname (s), naming them exactly (no wildcard - see the mismatch note above) - from the project root:
   ```bash
   mkcert -cert-file docker/certs/simpl.crt -key-file docker/certs/simpl.key localhost 127.0.0.1 myproject.test
   ```
   Replace `myproject.test` with your real `APP_URL` host.
4. Restart the container (`docker compose restart app` is enough - no rebuild needed, `docker/certs/` is picked up from the bind mount at container start). Restart `npm run dev` too - browser-sync's `:3000` reuses the same cert automatically (see `bs-config.cjs` in the project root) whenever `APP_URL` is HTTPS, falling back to its own self-signed one if `docker/certs/` is empty.

`docker/certs/` is gitignored - the cert/key pair is local, private key material, and never shipped or committed.

Generating the mkcert certificate matters more than just removing a warning once you've visited the app's own HTTPS URL: it sends an HSTS header ([`core/src/public/.htaccess`](../src/public/.htaccess)), so once a browser has trusted that URL, it refuses to let you click through an untrusted-certificate warning on *any* port of that host - including browser-sync's `:3000` - until browser-sync also presents a trusted certificate. Run mkcert first, *then* start `npm run dev`, to avoid `:3000` getting HSTS-blocked with no click-through option.

## Email (Mailpit)

Once the `auth` add-on is installed, a [Mailpit](https://mailpit.axllent.org/) container catches every email the app sends in development (`DEV=true`) - verification links, password resets, 2FA codes - and shows them at `http://127.0.0.1:8025/` instead of delivering them anywhere. Compose points the app at it through `SMTP_DEV_HOST`/`SMTP_DEV_PORT`, so `src/.env` needs no edits. Production mail (`DEV=false`) still uses `src/.env`'s `SMTP_*` settings.

## Configuration

Copy `docker/.env.example` to `.env` in the project root (separate from `src/.env`) to override any of these:

| Variable          | Default | Purpose                                                  |
|-------------------|---------|----------------------------------------------------------|
| `PHP_VERSION`     | `8.5`   | PHP version used to build the `app` image                |
| `UID` / `GID`     | `1000`  | User/group ID Apache runs as (Linux hosts, see below)    |
| `APP_PORT`        | `80`    | Host port that redirects to `APP_SSL_PORT`               |
| `APP_SSL_PORT`    | `443`   | Host port mapped to the container's Apache (HTTPS)       |
| `MARIADB_VERSION` | `12.3`  | MariaDB image tag (only used once `db` is installed)     |
| `DB_PORT`         | `3307`  | Host port mapped to the container's MariaDB              |
| `MAILPIT_VERSION` | `v1.31` | Mailpit image tag (only used once `auth` is installed)   |
| `MAILPIT_PORT`    | `8025`  | Host port for Mailpit's web UI                           |
| `DEV`             | `true`  | Overrides `src/.env`'s `DEV` flag as a container env var |

`DB_PORT` defaults away from a typical local MySQL/MariaDB install's `3306`. Only override `APP_PORT`/`APP_SSL_PORT` if something else on your machine already owns port 80/443. Changing `MARIADB_VERSION` on an existing database is safe - the container upgrades its data files automatically on the next start.

Every port is published on `127.0.0.1` only, so nothing is reachable from other devices on your network (Docker's published ports bypass host firewalls such as ufw, and the dev database has no root password). To test from a phone or another machine, replace the app's ports in a `compose.override.yaml` next to `compose.yaml` (loaded automatically):

```yaml
services:
  app:
    ports: !override
      - "${APP_PORT:-80}:80"
      - "${APP_SSL_PORT:-443}:443"
```

### Linux file ownership

On Linux, the container's `www-data` user writes straight into your bind-mounted project (`src/logs/`, `src/cache/`, `composer.lock`, ...), so its user ID has to match yours. The image defaults to `1000`, the first regular user on most distros - if `id -u`/`id -g` print something else, set `UID`/`GID` in the project-root `.env` and run `docker compose up -d --build`. Docker Desktop (Windows/macOS) maps ownership for you, so there this setting doesn't matter.

### Windows performance

Docker Desktop reads bind-mounted files from the Windows filesystem through a slow file share, and file-change events don't reach the container. For noticeably faster page loads, keep the project inside your WSL distro's filesystem (e.g. `~/projects/myproject`, reachable from Windows as `\\wsl.localhost\<distro>\...`) and run `docker compose` from a WSL shell - see Docker's [WSL best practices](https://docs.docker.com/desktop/features/wsl/best-practices/).

## Matching your real APP_URL

The container answers any hostname on port 443/`APP_SSL_PORT`, so once the host in `src/.env`'s `APP_URL` resolves to `127.0.0.1`, the site is reachable at that exact URL - not just `localhost`. Add a hosts file entry (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on macOS/Linux):

```
127.0.0.1  myproject.test
```

Replace `myproject.test` with your actual `APP_URL` host, and make sure `APP_URL` itself uses `https://` - otherwise links generated from it won't match the scheme the container actually serves. If `APP_SSL_PORT` isn't `443`, browse to `https://myproject.test:<port>/` instead.

## This is a template, not a site

`APP_NAME`/`APP_URL` are placeholders that only `simpl-installer` substitutes, at scaffold time - running Docker directly against this repo's own checkout leaves them literal, since there's no real site here yet. Use Docker the same way as `npm run dev`: on a real installed site (`npx @ijuantm/simpl-install`), not on this template repo itself.

## Why `.env` doesn't need editing

Compose sets `DB_SERVER=db` as a real container environment variable, which phpdotenv's `createImmutable()` never overwrites - so `src/.env`'s `DB_SERVER=localhost` default is left untouched. `DB_NAME`/`DB_USERNAME`/`DB_PASSWORD` come straight from the bind-mounted `src/.env`; `migrate:fresh` creates the database itself. If the app can't reach a database that's clearly up, check `docker compose exec app php -i | grep variables_order` includes `E` - without it, `docker/php/simpl.ini`'s `variables_order` never populates `$_ENV`, which `database.php` reads directly.

## Why `docker:composer` instead of a fixed script per command

Which Composer commands exist depends on which add-ons are installed - `migrate`/`seed` only exist once `db` is merged in, for example - so a fixed list of shortcuts (one Composer script per command, or a Makefile target per command) would drift out of sync with whatever's actually installed. `npm run docker:composer -- <cmd>` stays generic and always matches whatever `composer.json` currently has, the same way calling `docker compose exec -u www-data app composer <cmd>` directly always does.
