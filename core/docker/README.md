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

`src/` is bind-mounted, so PHP edits reflect immediately - rebuild only after changing `composer.json`, `composer.lock`, or the Dockerfile. After a `composer.json` change, run `docker compose run --rm app composer install`.

Migrations, seeding, and other Composer commands work the same as a manual setup, just run through `docker compose exec` instead of `composer` directly:

```bash
docker compose exec app composer migrate
docker compose exec app composer seed:fresh
```

Two shortcuts exist for the ones you'll type most - `npm run docker:sh` opens a shell in the container, and `composer docker -- <cmd>` runs any Composer command inside it (`composer docker -- migrate`, `composer docker -- test`, ...). The `--` tells Composer everything after it is an argument to pass through, not a Composer option.

Sass/TypeScript (`npm run build` / `npm run dev`) still run on the host as usual - `npm run dev`'s browser-sync just proxies whatever URL the app is served at.

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
4. Restart the container (`docker compose restart app` is enough - no rebuild needed, `docker/certs/` is picked up from the bind mount at container start).

`docker/certs/` is gitignored - the cert/key pair is local, private key material, and never shipped or committed.

## Configuration

Copy `docker/.env.example` to `.env` in the project root (separate from `src/.env`) to override any of these:

| Variable          | Default | Purpose                                                  |
|-------------------|---------|----------------------------------------------------------|
| `PHP_VERSION`     | `8.5`   | PHP version used to build the `app` image                |
| `APP_PORT`        | `80`    | Host port that redirects to `APP_SSL_PORT`               |
| `APP_SSL_PORT`    | `443`   | Host port mapped to the container's Apache (HTTPS)       |
| `MARIADB_VERSION` | `12.3`  | MariaDB image tag (only used once `db` is installed)     |
| `DB_PORT`         | `3307`  | Host port mapped to the container's MariaDB              |
| `DEV`             | `true`  | Overrides `src/.env`'s `DEV` flag as a container env var |

`DB_PORT` defaults away from a typical local MySQL/MariaDB install's `3306`. Only override `APP_PORT`/`APP_SSL_PORT` if something else on your machine already owns port 80/443.

## Matching your real APP_URL

The container answers any hostname on port 443/`APP_SSL_PORT`, so once the host in `src/.env`'s `APP_URL` resolves to `127.0.0.1`, the site is reachable at that exact URL - not just `localhost`. Add a hosts file entry (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on macOS/Linux):

```
127.0.0.1  myproject.test
```

Replace `myproject.test` with your actual `APP_URL` host, and make sure `APP_URL` itself uses `https://` - otherwise links generated from it won't match the scheme the container actually serves. If `APP_SSL_PORT` isn't `443`, browse to `https://myproject.test:<port>/` instead.

## This is a template, not a site

`APP_NAME`/`APP_URL`/`APP_HOST` are placeholders that only `simpl-installer` substitutes, at scaffold time - running Docker directly against this repo's own checkout leaves them literal, since there's no real site here yet. Use Docker the same way as `npm run dev`: on a real installed site (`npx @ijuantm/simpl-install`), not on this template repo itself.

## Why `.env` doesn't need editing

Compose sets `DB_SERVER=db` as a real container environment variable, which phpdotenv's `createImmutable()` never overwrites - so `src/.env`'s `DB_SERVER=localhost` default is left untouched. `DB_NAME`/`DB_USERNAME`/`DB_PASSWORD` come straight from the bind-mounted `src/.env`; `migrate:fresh` creates the database itself. If the app can't reach a database that's clearly up, check `docker compose exec app php -i | grep variables_order` includes `E` - without it, `docker/php/simpl.ini`'s `variables_order` never populates `$_ENV`, which `database.php` reads directly.

## Why `docker` instead of a fixed script per command

Which Composer commands exist depends on which add-ons are installed - `migrate`/`seed` only exist once `db` is merged in, for example - so a fixed list of shortcuts (one Composer script per command, or a Makefile target per command) would drift out of sync with whatever's actually installed. `composer docker -- <cmd>` stays generic and always matches whatever `composer.json` currently has, the same way calling `docker compose exec app composer <cmd>` directly always does.
