# Scripts

Maintainer tooling for this repo. Not shipped to installed projects.

## `release.mjs`

Publishes a tagged version to the CDN.

```bash
git tag v2.0.0
node scripts/release.mjs 2.0.0
```

It builds `core.zip` and one zip per folder in `add-ons/` from the `v<version>` tag with `git archive`, so only committed files are released. It then adds the version to `versions.json` with the add-on list taken from `add-ons/`, and marks it as latest. The version must directly follow the latest one on the CDN (e.g. after 2.0.0: 2.0.1, 2.1.0 or 3.0.0), or be the latest one itself to replace it. It also stops if the version in `core/.simpl`, `core/composer.json`, `core/package.json` or `SIMPL_VERSION` in `core/src/.env` doesn't match, and warns if the tag isn't pushed yet. It shows what it built and asks before uploading anything over SSH. Answer no to keep the files locally for inspection.

The version folder is uploaded next to the live one and then swapped in, so releasing the same version again replaces it cleanly. `versions.json` is uploaded last, so it never lists a version whose zips aren't there yet.

The server login stays out of this public repo. The script connects to the SSH host `simpl-cdn`, which you define once in `~/.ssh/config`:

```
Host simpl-cdn
  HostName <server>
  User <user>
  IdentityFile ~/.ssh/<key>
```

## `fresh-install-test.mjs`

Does a real fresh install of the **current working tree** through the Simpl CLI (`simpl new` + `simpl add`, run through `npx @ijuantm/simpl`). For the chosen add-ons it runs the scaffold, the add-on merges, `composer install`, `composer test`, and - when a database is reachable - `composer test:integration`, `composer migrate:fresh` / `seed:fresh`, then `npm install` (which builds Sass and Vite). The result is a browsable install.

Zips are rebuilt from the working tree every run (uncommitted edits to tracked files included; new files must be `git add`ed first) and served to the CLI locally through `SIMPL_LOCAL_RELEASES`, so nothing has to be committed or published first. Each zip is a `git archive` of its subtree, because the CLI extracts zips as-is without stripping a wrapping folder. The CDN's `versions.json` is still fetched once to resolve `latest`.

### Running

```bash
node scripts/fresh-install-test.mjs            # interactive add-on picker, everything selected by default
node scripts/fresh-install-test.mjs --all      # every add-on, no menu
```

The install lands in `<DEST>/simpl-test/` (default `<DEST>` is `~/Desktop/simpl-fresh-install-test`), wiped at the start of each run and left in place afterwards so you can browse it. It's always named "Simpl Test" and scaffolded with `--url=https://<domain>/` (default domain `simpl.test`). To browse it, either run `docker compose up -d --build` inside the install (just needs the hosts entry the script adds), or use the generated Apache vhost, see [Apache route](#apache-route).

### Environment overrides

| Variable             | Default                              | Purpose                                          |
|----------------------|--------------------------------------|--------------------------------------------------|
| `SIMPL_TEST_DEST`    | `~/Desktop/simpl-fresh-install-test` | where the install is written                     |
| `SIMPL_TEST_DOMAIN`  | `simpl.test`                         | hostname the install is scaffolded and served at |
| `SIMPL_TEST_DB`      | `auto`                               | `auto` (Docker then local), `docker`, or `local` |
| `SIMPL_TEST_DB_PORT` | `3307`                               | host port for the Docker fallback MariaDB        |

### Database

A MariaDB/MySQL server (`root`, no password) is optional; if none is reachable, the test:integration/migrate/seed steps are skipped.

Docker is tried first: the script starts a throwaway MariaDB via `scripts/compose.yaml` (host port `3307` by default) and tears it down again on exit, so a full `--all` run works without a local MariaDB install. If Docker isn't available, it falls back to a local MySQL/MariaDB server on `localhost:3306` (WAMP, XAMPP, MAMP, a native install, ...).

This compose file is maintainer-only and only runs the `db` service. It's separate from the `compose.yaml`/`add-ons/db/compose.yaml` pair that ships to installed projects (see [`core/docker/README.md`](../core/docker/README.md)).

### Trusted HTTPS (Docker route)

If [mkcert](https://github.com/FiloSottile/mkcert) is on `PATH`, the script generates a certificate covering `<domain>` plus `localhost`/`127.0.0.1` and copies it into the install's `docker/certs/`, so `docker compose up -d --build` inside it serves HTTPS without a browser warning instead of the shipped self-signed cert (see [`core/docker/README.md`](../core/docker/README.md#https)). It's skipped with a warning if mkcert isn't installed.

This only removes the warning if you've run `mkcert -install` once. That installs mkcert's local CA into your OS/browser trust stores, which has to happen on your host and can't be scripted from here.

### Apache route

The script writes a vhost to `<DEST>/httpd-vhosts.conf` on every run, pointing `<domain>` at `<DEST>/simpl-test/src/public`. Wiring it into Apache is a one-time setup; redo it if an Apache update resets your config. The vhost serves plain HTTP on port 80, while the install is scaffolded with an `https://` URL for the Docker route, so change `APP_URL` in the install's `src/.env` to `http://<domain>/` before browsing it this way.

1. Enable the rewrite module in `httpd.conf`, needed for the `.htaccess` front-controller rules (`AllowOverride All` alone does nothing without it):
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
2. Include the generated vhost near the end of `httpd.conf`, using the real path to your `<DEST>` and forward slashes even on Windows:
   ```apache
   IncludeOptional "/absolute/path/to/<DEST>/httpd-vhosts.conf"
   ```
   Use `IncludeOptional`, not `Include`: the cleanup after a verification pass removes `<DEST>` entirely, and `Include` fails Apache's config check (so Apache won't start) when the file doesn't exist.
3. Add `127.0.0.1  <domain>` to your hosts file (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on macOS/Linux). The script adds it at the end of each run if it's missing, or prints the line if it can't write the file (no admin/root privileges).
4. Restart Apache (from the WAMP/XAMPP tray, or `httpd -k restart`). The install is then reachable at `http://<domain>/`.

For reference, the generated vhost looks like this:

```apache
<VirtualHost *:80>
    ServerName simpl.test
    DocumentRoot "/absolute/path/to/<DEST>/simpl-test/src/public"

    <Directory "/absolute/path/to/<DEST>/simpl-test/src/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```
