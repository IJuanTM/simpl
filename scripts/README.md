# Scripts

Maintainer tooling for this repo. Not shipped to installed projects.

Both scripts share one working folder, `SIMPL_DEV_DIR` (default `~/Desktop/simpl-dev`):

```
simpl-dev/
├── releases/<version>/   local releases from release.mjs --local
├── simpl-test/           the fresh install from fresh-install-test.mjs
├── install.log
└── httpd-vhosts.conf
```

## `release.mjs`

Publishes a tagged version to the CDN.

```bash
git tag v2.0.0
node scripts/release.mjs 2.0.0
node scripts/release.mjs          # pick the next patch, minor or major version from a list
node scripts/release.mjs 2.0.0 --local
```

Without a version it shows the next patch, minor and major version after the latest release on the CDN. Answer with a number or type a version.

Append `a` or `b` for an alpha or beta (e.g. `2.1.0a`, or `2a` in the list). A pre-release has to follow the latest stable release the same way, and an alpha can't come after a beta of the same version. Pre-releases are marked `is-pre-release` in `versions.json` and never become latest, so `simpl new` keeps using the latest stable release unless a pre-release is asked for by name. The tag, the version stamps and the changelog entry all use the full version, e.g. `v2.1.0a` and `#### Version 2.1.0a (<date>)`.

It builds `core.zip` and one zip per folder in `add-ons/` from the `v<version>` tag with `git archive`, so only committed files are released. It then adds the version to `versions.json` with the add-on list taken from `add-ons/`, and marks it as latest. The version must directly follow the latest one on the CDN (e.g. after 2.0.0: 2.0.1, 2.1.0 or 3.0.0), or be the latest one itself to replace it. It also stops if the version in `core/.simpl`, `core/composer.json`, `core/package.json` or `SIMPL_VERSION` in `core/src/.env` doesn't match, or if `SIMPL_LAST_UPDATE` in `core/src/.env` doesn't match the date of the version's entry in the root `README.md`. It warns if that date isn't today or the tag isn't pushed yet. It shows what it built and asks before uploading anything over SSH. Answer no to
keep the files locally for inspection.

The version folder is uploaded next to the live one and then swapped in, so releasing the same version again replaces it cleanly. `versions.json` is uploaded last, so it never lists a version whose zips aren't there yet.

With `--local`, the same zips go to `<SIMPL_DEV_DIR>/releases/<version>/` instead, with the same version checks run against the versions already in that folder. Nothing is uploaded, no `versions.json` is written (the CLI's `--local` mode lists the version folders instead), and the tag doesn't have to be pushed. Install it with `node scripts/fresh-install-test.mjs --release=<version>`, or by hand with `SIMPL_LOCAL_RELEASES=<SIMPL_DEV_DIR>/releases` and `simpl new --local` / `simpl add --local`.

The server login stays out of this public repo. The script connects to the SSH host `simpl-cdn`, which you define once in `~/.ssh/config`:

```
Host simpl-cdn
  HostName <server>
  User <user>
  IdentityFile ~/.ssh/<key>
```

## `fresh-install-test.mjs`

Does a real fresh install of the **current working tree** (or, with `--release`, a local release) through the Simpl CLI (`simpl new` + `simpl add`, run through `npx @ijuantm/simpl`). For the chosen add-ons it runs the scaffold, the add-on merges, `simpl composer install`, `simpl test`, and - when a database is reachable - `simpl test:integration`, `simpl migrate:fresh` / `seed:fresh`, then `npm install` (which builds Sass and Vite). The result is a browsable install.

By default, zips are rebuilt from the working tree every run (uncommitted edits to tracked files included; new files must be `git add`ed first) and served to the CLI locally through `SIMPL_LOCAL_RELEASES`, so nothing has to be committed or published first. Each zip is a `git archive` of its subtree, because the CLI extracts zips as-is without stripping a wrapping folder. The zips are put under the version in `core/.simpl`, since `simpl add` looks them up by the version in the project's `.simpl`, so no CDN access is needed. They're built in a temp folder rather than `releases/`, since they share their version number with the tagged local release there.

With `--release`, it skips the build and installs `<SIMPL_DEV_DIR>/releases/<version>/` as built by `release.mjs --local`, so a tagged release can be tested before it's uploaded. The version defaults to the one in `core/.simpl`; pick another with `--release=<version>`.

### Running

```bash
node scripts/fresh-install-test.mjs            # interactive add-on picker, everything selected by default
node scripts/fresh-install-test.mjs --all      # every add-on, no menu
node scripts/fresh-install-test.mjs --release  # install the local release of core/.simpl's version instead
```

The install lands in `<SIMPL_DEV_DIR>/simpl-test/`, wiped (with `install.log`) at the start of each run and left in place afterwards so you can browse it. It's always named "Simpl Test" and scaffolded with `--url=https://<domain>/` (default domain `simpl.test`). When Docker is running and the `db` add-on is installed, the install's own Docker stack is left running, so it's browsable right away (just needs the hosts entry the script adds); stop it with `simpl down` inside the install. Otherwise run `simpl up` there, or use the generated Apache vhost, see [Apache route](#apache-route). A `simpl-test` stack still running from an earlier run is stopped (volumes included) before the install is wiped; if it can't be, the script quits and asks you to stop it.

### Environment overrides

| Variable            | Default               | Purpose                                          |
|---------------------|-----------------------|--------------------------------------------------|
| `SIMPL_DEV_DIR`     | `~/Desktop/simpl-dev` | working folder shared with `release.mjs --local` |
| `SIMPL_TEST_DOMAIN` | `simpl.test`          | hostname the install is scaffolded and served at |
| `SIMPL_TEST_DB`     | `auto`                | `auto` (Docker then local), `docker`, or `local` |

### Database

The `simpl-test` database is created, migrated and seeded where the install keeps using it:

1. If Docker is running, the script starts the install's own stack with `simpl up` and runs `simpl test:integration`, `migrate:fresh` and `seed:fresh` inside it, so the database lives in that stack's MariaDB.
2. Otherwise it uses a local MySQL/MariaDB server on `localhost:3306` (`root`, no password; WAMP, XAMPP, MAMP, a native install, ...), which the Apache route then uses too.
3. If neither is available, those steps are skipped.

`SIMPL_TEST_DB=local` skips Docker (for the Apache route when Docker is also running); `SIMPL_TEST_DB=docker` never uses the local server.

### Trusted HTTPS (Docker route)

If [mkcert](https://github.com/FiloSottile/mkcert) is on `PATH`, the script generates a certificate covering `<domain>` plus `localhost`/`127.0.0.1` and copies it into the install's `docker/certs/`, so `simpl up` inside it serves HTTPS without a browser warning instead of the shipped self-signed cert (see [`core/docker/README.md`](../core/docker/README.md#https)). It's skipped with a warning if mkcert isn't installed.

This only removes the warning if you've run `mkcert -install` once. That installs mkcert's local CA into your OS/browser trust stores, which has to happen on your host and can't be scripted from here.

### Apache route

The script writes a vhost to `<SIMPL_DEV_DIR>/httpd-vhosts.conf` on every run, pointing `<domain>` at `<SIMPL_DEV_DIR>/simpl-test/src/public`. Wiring it into Apache is a one-time setup; redo it if an Apache update resets your config. The vhost serves plain HTTP on port 80, while the install is scaffolded with an `https://` URL for the Docker route, so change `APP_URL` in the install's `src/.env` to `http://<domain>/` before browsing it this way.

1. Enable the rewrite module in `httpd.conf`, needed for the `.htaccess` front-controller rules (`AllowOverride All` alone does nothing without it):
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
2. Include the generated vhost near the end of `httpd.conf`, using the real path to your `<SIMPL_DEV_DIR>` and forward slashes even on Windows:
   ```apache
   IncludeOptional "/absolute/path/to/<SIMPL_DEV_DIR>/httpd-vhosts.conf"
   ```
   Use `IncludeOptional`, not `Include`: the cleanup after a verification pass removes `<SIMPL_DEV_DIR>` entirely, and `Include` fails Apache's config check (so Apache won't start) when the file doesn't exist.
3. Add `127.0.0.1  <domain>` to your hosts file (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on macOS/Linux). The script adds it at the end of each run if it's missing, or prints the line if it can't write the file (no admin/root privileges).
4. Restart Apache (from the WAMP/XAMPP tray, or `httpd -k restart`). The install is then reachable at `http://<domain>/`.

For reference, the generated vhost looks like this:

```apache
<VirtualHost *:80>
    ServerName simpl.test
    DocumentRoot "/absolute/path/to/<SIMPL_DEV_DIR>/simpl-test/src/public"

    <Directory "/absolute/path/to/<SIMPL_DEV_DIR>/simpl-test/src/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```
