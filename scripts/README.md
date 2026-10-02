# Scripts

Maintainer tooling for this repo. Not shipped to installed projects.

The scripts share one working folder, `SIMPL_DEV_DIR` (default `~/Desktop/simpl-dev`):

```
simpl-dev/
├── simpl-test/           the fresh install from fresh-install-test.mjs (updated by update-test.mjs)
├── install.log
└── httpd-vhosts.conf
```

## `fresh-install-test.mjs`

Does a real fresh install of the **current working tree** through the Simpl CLI (`simpl new` + `simpl add`, run through `npx @ijuantm/simpl`). For the chosen add-ons it runs the scaffold, the add-on merges, `simpl composer install`, `simpl test`, and - when a database is reachable - `simpl test:integration`, `simpl migrate:fresh` / `seed:fresh`, then `npm install` (which builds Sass and Vite). The result is a browsable install.

By default, zips are rebuilt from the working tree every run (uncommitted edits to tracked files included; new files must be `git add`ed first) and served to the CLI locally through `SIMPL_LOCAL_RELEASES`, so nothing has to be committed or published first. Each zip is a `git archive` of its subtree, because the CLI extracts zips as-is without stripping a wrapping folder. The zips are put under the version in `core/.simpl`, since `simpl add` looks them up by the version in the project's `.simpl`, so no CDN access is needed. They're built in a temp folder that is removed afterwards.

### Running

```bash
node scripts/fresh-install-test.mjs            # interactive add-on picker, everything selected by default
node scripts/fresh-install-test.mjs --all      # every add-on, no menu
```

The install lands in `<SIMPL_DEV_DIR>/simpl-test/`, wiped (with `install.log`) at the start of each run and left in place afterwards so you can browse it. It's always named "Simpl Test" and scaffolded with `--url=https://<domain>/` (default domain `simpl.test`). When Docker is running and the `db` add-on is installed, the install's own Docker stack is left running, so it's browsable right away (just needs the hosts entry the script adds); stop it with `simpl down` inside the install. Otherwise run `simpl up` there, or use the generated Apache vhost, see [Apache route](#apache-route). A `simpl-test` stack still running from an earlier run is stopped (volumes included) before the install is wiped; if it can't be, the script quits and asks you to stop it.

### Environment overrides

| Variable            | Default               | Purpose                                          |
|---------------------|-----------------------|--------------------------------------------------|
| `SIMPL_DEV_DIR`     | `~/Desktop/simpl-dev` | working folder the install lands in              |
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
### Apache route

The script writes a vhost to `<SIMPL_DEV_DIR>/httpd-vhosts.conf` on every run, pointing `<domain>` at `<SIMPL_DEV_DIR>/simpl-test/src/public`. The vhost serves plain HTTP on port 80, while the install is scaffolded with an `https://` URL for the Docker route, so change `APP_URL` in the install's `src/.env` to `http://<domain>/` before browsing it this way.

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

## `update-test.mjs`

Updates the existing `<SIMPL_DEV_DIR>/simpl-test/` install with the working tree's styling, scripts and views, without reinstalling it. Nothing else is touched (no PHP classes, config, `.env`, database or Docker stack), so a running install keeps its data and serves the new files right away.

```bash
node scripts/update-test.mjs
```

It builds zips from the working tree (like `fresh-install-test.mjs`) and runs `simpl new` + `simpl add` for the install's add-ons (read from its `.simpl`) into a temp folder, so add-on patches to core files like `main.scss`, `main.ts` or `header.phtml` are merged exactly as in a real install. It then makes the install's `src/scss/`, `src/ts/` and `src/views/` match that result (adding, updating and removing files), lists what changed, and runs `npm run build` in the install.
