# Scripts

Maintainer tooling for this repo. Not shipped to installed projects.

## `fresh-install-test.mjs`

Does a real fresh install of the **current working tree** through the actual
installer ecosystem (`npx @ijuantm/simpl-install` + `npx @ijuantm/simpl-addon`).
For each chosen add-on set it runs the real scaffold, the real add-on merges,
`composer install`, `composer test`, and - when a database is reachable -
`composer migrate:fresh` / `seed:fresh`, then `npm install` (which builds Sass and
Vite). The result is a browsable install.

Zips are rebuilt from the working tree every run (uncommitted edits included) and
served to the installers locally, so nothing has to be committed or published
first. A MariaDB/MySQL server (`root`, no password) is optional; if it is down the
migrate/seed steps are skipped.

### Running

```bash
node scripts/fresh-install-test.mjs            # interactive picker
node scripts/fresh-install-test.mjs --all      # single site with every add-on, no menu
node scripts/fresh-install-test.mjs --only=core-db-auth   # just that cumulative level
```

Installs land in `<DEST>/<level>/simpl-test/` (default `<DEST>` is
`~/Desktop/simpl-fresh-install-test`), wiped at the start of each run and left in
place afterwards so you can browse them. Each level is scaffolded with
`--url=<level>.<domain>` (default domain `simpl.test`), e.g. `core-db-auth`
installs to `core-db-auth.simpl.test`.

### Environment overrides

| Variable            | Default                              | Purpose                         |
|---------------------|--------------------------------------|---------------------------------|
| `SIMPL_TEST_DEST`   | `~/Desktop/simpl-fresh-install-test` | where installs are written      |
| `SIMPL_TEST_DOMAIN` | `simpl.test`                         | base domain for the level hosts |

## Apache / WAMP setup for the test installs

The script writes a wildcard vhost to `<DEST>/httpd-vhosts.conf` on every run. It
maps `*.<domain>` to `<DEST>/%1/simpl-test/src/public`, where `%1` is the first
label of the requested host - so `core-db-auth.simpl.test` resolves to the
`core-db-auth` install's public folder. This is a one-time wiring; redo it if an
Apache update resets your config.

### 1. Enable the required modules in `httpd.conf`

```apache
LoadModule vhost_alias_module modules/mod_vhost_alias.so
LoadModule rewrite_module modules/mod_rewrite.so
```

`vhost_alias` provides `VirtualDocumentRoot`; `rewrite` is needed for the
`.htaccess` front-controller rules (`AllowOverride All` alone does nothing without
it).

### 2. Include the generated vhost from `httpd.conf`

Add near the end of `httpd.conf`, using the real path to your `<DEST>` and forward
slashes even on Windows. Use `IncludeOptional`, not `Include` - the cleanup step
after a verification pass removes `<DEST>` entirely, and `Include` fails Apache's
config check (and refuses to (re)start) when the file doesn't exist, whereas
`IncludeOptional` silently skips a missing file:

```apache
IncludeOptional "/absolute/path/to/<DEST>/httpd-vhosts.conf"
```

For reference, the file it generates looks like this:

```apache
<VirtualHost *:80>
    ServerName simpl.test
    ServerAlias *.simpl.test
    UseCanonicalName Off
    VirtualDocumentRoot "/absolute/path/to/<DEST>/%1/simpl-test/src/public"

    <Directory "/absolute/path/to/<DEST>">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

### 3. Add a hosts entry per level

Wildcard DNS does not apply to the hosts file, so each level needs its own line in
`/etc/hosts` (`C:\Windows\System32\drivers\etc\hosts` on Windows):

```
127.0.0.1  core.simpl.test
127.0.0.1  core-db.simpl.test
127.0.0.1  core-db-auth.simpl.test
```

The script prints the exact lines it needs at the end of each run.

### 4. Restart Apache

Restart from the WAMP/XAMPP tray or `httpd -k restart`. Each installed level is
then reachable at `http://<level>.<domain>/`.
