#!/usr/bin/env node
//
// Real fresh install of the CURRENT WORKING TREE via the actual Simpl CLI
// (`simpl new` + `simpl add`, run through `npx @ijuantm/simpl`). For the chosen add-on set
// it runs: real scaffold -> real add-on merges -> `composer install` -> `composer test` ->
// (with `db`) `composer migrate:fresh` (+ `seed:fresh` when another add-on is present,
// against a db named simpl-test) -> `npm install` (postinstall sass/vite build). So the
// install is ready to browse.
//
// The zip is rebuilt from the working tree every run (uncommitted edits to tracked files
// included, via `git stash create` - new files must be `git add`ed first, since that command
// only snapshots tracked changes) and served to the CLI through SIMPL_LOCAL_RELEASES.
// Each zip is a `git archive` of its subtree, since the CLI extracts it as-is with no wrapping folder stripped.
// The CDN's versions.json is fetched once to resolve `latest`.
// A reachable MariaDB (root / no password) is optional; if none is found, the test:integration/migrate/seed steps are skipped.
// Docker is tried first: a throwaway MariaDB starts via scripts/compose.yaml and tears down on exit.
// If Docker isn't available, a local MySQL/MariaDB server on localhost:3306 is used instead (WAMP, XAMPP, MAMP, a native install, ... - whatever's already running).
// Override with SIMPL_TEST_DB=docker (never fall back to local) or SIMPL_TEST_DB=local (skip the Docker probe); default is 'auto'.
// SIMPL_TEST_DB_PORT (default 3307) is the host port used for the Docker fallback.
//
// The install lands in ~/Desktop/simpl-fresh-install-test/simpl-test/ (wiped each run, left
// after). It's always named "Simpl Test", scaffolded with --url=https://simpl.test/, and uses
// simpl-test as the db name.
// Browse it via `docker compose up -d --build` inside it (just needs the printed hosts file
// line). Docker's Apache always terminates TLS on :443 regardless of APP_URL, which is why the
// scaffold uses https. A httpd-vhosts.conf is also written for a local Apache setup (WAMP/XAMPP/...),
// but that serves plain HTTP on :80 - browsing that route means editing the installed .env's
// APP_URL back to http first.
// If mkcert is on PATH, a certificate covering simpl.test + localhost is generated and copied
// into docker/certs/, so the Docker route is trusted out of the box (run `mkcert -install` once
// yourself first - that step touches your OS/browser trust store, so it can't be done for you
// here). Skipped with a warning if mkcert isn't installed.
// Overrides: SIMPL_TEST_DEST, SIMPL_TEST_DOMAIN, SIMPL_TEST_DB, SIMPL_TEST_DB_PORT.
//
// Run with no arguments for the interactive add-on picker (default: everything selected).
// Flags:
//   --all  install with every add-on merged in - no menu
//
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {execFileSync, spawnSync} from 'node:child_process';

const SELF = fileURLToPath(import.meta.url);
const REPO = path.resolve(path.dirname(SELF), '..');
const CDN_VERSIONS = 'https://cdn.simpl.iwanvanderwal.nl/framework/versions.json';
const NAME = 'Simpl Test';
const DOMAIN = process.env.SIMPL_TEST_DOMAIN || 'simpl.test';
const SITE_URL = `https://${DOMAIN}/`;
const DEST = process.env.SIMPL_TEST_DEST || path.join(os.homedir(), 'Desktop', 'simpl-fresh-install-test');
const SIMPL_TEST_DB = process.env.SIMPL_TEST_DB || 'auto'; // 'auto' | 'local' | 'docker'
const SIMPL_TEST_DB_PORT = process.env.SIMPL_TEST_DB_PORT || '3307';
const DOCKER_COMPOSE = path.join(REPO, 'scripts', 'compose.yaml');
const LOCAL_DB = {host: 'localhost', user: 'root', pass: ''};
let DB = LOCAL_DB;
let CERT = null; // {crt, key} once generated

// Output helpers, matching the Simpl CLI's style.
const C = {
  reset: '\x1b[0m', green: '\x1b[32m', yellow: '\x1b[33m', red: '\x1b[31m',
  cyan: '\x1b[36m', blue: '\x1b[34m', gray: '\x1b[90m', bold: '\x1b[1m', dim: '\x1b[2m',
};
const PAD = '  ';
const BOX_W = 62;
const stripAnsi = (s) => s.replace(/\x1b\[[0-9;]*m/g, '');
const styled = (m, ...s) => s.join('') + m + C.reset;
const line = (m = '') => console.log(m);
const out = (m, color = C.reset) => console.log(color + m + C.reset);
const prefixed = (sym, color, m, bold = false, dim = false) =>
  out(PAD + color + sym + C.reset + ' ' + (bold ? styled(m, C.bold) : dim ? styled(m, C.dim) : m));
const success = (m, bold = false) => prefixed('✓', C.green, m, bold);
const error = (m, bold = false) => prefixed('✕', C.red, m, bold);
const warn = (m) => prefixed('⚠', C.yellow, m);
const info = (m) => prefixed('◌', C.cyan, m, false, true);
const task = (m) => out(PAD + m);
const item = (m, dim = false) => out(PAD + C.cyan + '•' + C.reset + ' ' + (dim ? styled(m, C.dim) : m));
const divider = () => {
  line();
  out(PAD + '─'.repeat(16), C.dim);
  line();
};
const heading = (m) => out(PAD + styled(m, C.bold), C.blue);
const box = (title) => {
  const plain = stripAnsi(title);
  const shown = plain.length > BOX_W - 2 ? plain.slice(0, BOX_W - 5) + '...' : plain;
  line();
  out(PAD + '╭' + '─'.repeat(BOX_W) + '╮');
  out(PAD + '│ ' + styled(title.replace(plain, shown), C.bold) + ' '.repeat(BOX_W - 2 - shown.length) + ' │');
  out(PAD + '╰' + '─'.repeat(BOX_W) + '╯');
};
const die = (msg) => {
  line();
  error(msg);
  line();
  process.exit(1);
};

let mode = null; // 'all' | 'pick'
for (const a of process.argv.slice(2)) {
  if (a === '--all') mode = 'all';
  else if (a === '-h' || a === '--help') {
    const body = fs.readFileSync(SELF, 'utf8').split('\n').slice(1);
    console.log(body.slice(0, body.findIndex((l) => !l.startsWith('//'))).join('\n'));
    process.exit(0);
  } else die(`unknown option: ${a}`);
}

for (const bin of ['php', 'node', 'npm', 'npx', 'composer', 'git']) {
  if (spawnSync(`${bin} --version`, {shell: true, stdio: 'ignore'}).status !== 0) die(`missing required tool: ${bin}`);
}

function addonDeps() {
  const dir = path.join(REPO, 'add-ons');
  const names = fs.readdirSync(dir, {withFileTypes: true}).filter((e) => e.isDirectory()).map((e) => e.name);
  const deps = {};
  for (const n of names) {
    try {
      deps[n] = JSON.parse(fs.readFileSync(path.join(dir, n, 'addon.json'), 'utf8')).dependencies || [];
    } catch {
      deps[n] = [];
    }
  }
  return deps;
}

function topo(deps, roots) {
  const order = [], seen = new Set();
  const visit = (n) => {
    if (!(n in deps) || seen.has(n)) return;
    seen.add(n);
    (deps[n] || []).forEach(visit);
    order.push(n);
  };
  (roots || Object.keys(deps)).forEach(visit);
  return order;
}

const DEPS = addonDeps();
const ADDONS = topo(DEPS);
if (!ADDONS.length) die(`no add-ons found under ${REPO}/add-ons`);

function pdoUp(db) {
  return spawnSync('php', ['-r', 'try { new PDO("mysql:host=".getenv("H"), getenv("U"), getenv("P")); } catch (Throwable $e) { exit(1); }'],
    {env: {...process.env, H: db.host, U: db.user, P: db.pass}}).status === 0;
}

let DB_UP = false, dockerDbStarted = false;
if (SIMPL_TEST_DB !== 'local' && spawnSync('docker', ['compose', 'version'], {stdio: 'ignore'}).status === 0) {
  if (spawnSync('docker', ['compose', '-f', DOCKER_COMPOSE, 'up', '-d', 'db', '--wait'],
    {stdio: 'ignore', env: {...process.env, SIMPL_TEST_DB_PORT}}).status === 0) {
    dockerDbStarted = true;
    // host carries a trailing ";port=..." DSN clause - see runInstall()'s DB_SERVER write for why.
    DB = {host: `127.0.0.1;port=${SIMPL_TEST_DB_PORT}`, user: 'root', pass: ''};
    DB_UP = pdoUp(DB);
  }
}
if (!DB_UP && SIMPL_TEST_DB !== 'docker' && pdoUp(LOCAL_DB)) {
  DB = LOCAL_DB;
  DB_UP = true;
}
if (dockerDbStarted) process.on('exit', () => {
  try {
    spawnSync('docker', ['compose', '-f', DOCKER_COMPOSE, 'down', '-v'], {stdio: 'ignore', env: {...process.env, SIMPL_TEST_DB_PORT}});
  } catch {
  }
});

// Core is always selected; everything else defaults to selected too, and a pick's
// dependencies fill in automatically as items are deselected.
async function pick() {
  const items = ['core', ...ADDONS];
  const on = new Set(items.map((_, i) => i)); // the user's explicit picks; index 0 is core, always on
  const notes = ['', ...ADDONS.map((a) => {
    const d = topo(DEPS, [a]).filter((x) => x !== a);
    return d.length ? `${C.gray}(requires: ${d.join(', ')})${C.reset}` : '';
  })];
  // indices that end up selected once each pick's dependencies are pulled in
  const effective = () => new Set(
    topo(DEPS, [...on].filter((i) => i !== 0).map((i) => items[i])).map((a) => 1 + ADDONS.indexOf(a)),
  );
  let cur = 0, drawn = 0;
  const o = process.stdout;

  line();
  out(PAD + styled('Select what to install', C.bold), C.blue);
  line();

  const draw = () => {
    if (drawn) o.write(`\x1b[${drawn}A`);
    const eff = effective();
    items.forEach((name, i) => {
      const ptr = i === cur ? `${C.cyan}❯${C.reset} ` : '  ';
      const checkbox =
        i === 0 ? `${C.gray}[x]${C.reset}` :
          on.has(i) ? `${C.green}[x]${C.reset}` :
            eff.has(i) ? `${C.cyan}[x]${C.reset}` : '[ ]';
      o.write(`\r\x1b[K${PAD}${ptr}${checkbox} ${name}${notes[i] ? ' ' + notes[i] : ''}\n`);
    });
    o.write(`\r\x1b[K${PAD}${C.dim}↑/↓ move · space select · click · a all · n none · enter run · q cancel${C.reset}\n`);
    o.write(`\r\x1b[K${PAD}${C.green}[x]${C.dim} selected    ${C.reset}${C.cyan}[x]${C.reset}${C.dim}${C.cyan} required by a selection${C.reset}\n`);
    drawn = items.length + 2;
  };

  const stdin = process.stdin;
  const wasRaw = stdin.isRaw;
  let listTop = 0;
  const restore = () => {
    try {
      stdin.setRawMode(wasRaw);
    } catch {
    }
    o.write('\x1b[?25h\x1b[?1000l\x1b[?1006l');
  };

  if (stdin.isTTY) {
    stdin.setRawMode(true);
    listTop = await new Promise((res) => {
      const onResp = (d) => {
        const m = /\x1b\[(\d+);\d+R/.exec(d.toString());
        if (m) {
          stdin.off('data', onResp);
          res(+m[1]);
        }
      };
      stdin.on('data', onResp);
      o.write('\x1b[6n');
      setTimeout(() => {
        stdin.off('data', onResp);
        res(0);
      }, 300);
    });
    o.write('\x1b[?25l\x1b[?1000h\x1b[?1006h');
  }
  stdin.resume();
  stdin.setEncoding('utf8');
  draw();

  const selected = await new Promise((resolve) => {
    const finish = (val) => {
      stdin.off('data', onData);
      restore();
      process.off('exit', restore);
      resolve(val);
    };
    const onData = (d) => {
      const k = d.toString();
      if (k === '\x03' || k === 'q' || k === 'Q' || k === '\x1b') {
        restore();
        line();
        info('cancelled');
        line();
        process.exit(0);
      } else if (k === '\x1b[A') cur = Math.max(0, cur - 1);
      else if (k === '\x1b[B') cur = Math.min(items.length - 1, cur + 1);
      else if (k === ' ' && cur !== 0) on.has(cur) ? on.delete(cur) : on.add(cur);
      else if (k === 'a' || k === 'A') items.forEach((_, i) => on.add(i));
      else if (k === 'n' || k === 'N') {
        on.clear();
        on.add(0);
      } else if (k === '\r' || k === '\n') return finish([...on].filter((i) => i !== 0).map((i) => items[i]));
      else {
        const m = /\x1b\[<(\d+);(\d+);(\d+)M/.exec(k); // SGR mouse press
        if (m && +m[1] === 0) {
          const i = +m[3] - listTop;
          if (i >= 1 && i < items.length) {
            cur = i;
            on.has(i) ? on.delete(i) : on.add(i);
          }
        }
      }
      draw();
    };
    process.on('exit', restore);
    stdin.on('data', onData);
  });

  stdin.pause();
  return selected;
}

async function resolveLatest() {
  const {versions = {}} = await (await fetch(CDN_VERSIONS, {signal: AbortSignal.timeout(15000)})).json();
  return Object.keys(versions).find((k) => versions[k]['is-latest']) || null;
}

function buildZip(subdir, outZip) {
  fs.mkdirSync(path.dirname(outZip), {recursive: true});
  const tree = execFileSync('git', ['stash', 'create'], {cwd: REPO, encoding: 'utf8'}).trim() || 'HEAD';
  execFileSync('git', ['archive', '--format=zip', '-o', outZip, `${tree}:${subdir}`], {cwd: REPO});
}

function run(cmd, cwd, log) {
  const r = spawnSync(cmd, {cwd, shell: true, encoding: 'utf8', input: '', maxBuffer: 64 * 1024 * 1024});
  fs.appendFileSync(log, `\n$ ${cmd}\n${r.stdout || ''}${r.stderr || ''}`);
  return r.status === 0;
}

function runInstall(addons) {
  const proj = path.join(DEST, 'simpl-test');
  const log = path.join(DEST, 'install.log');
  const dbname = addons.includes('db') ? 'simpl-test' : null;
  fs.writeFileSync(log, '');
  box(`Installing: ${C.cyan}${NAME}${C.reset} ${C.dim}${SITE_URL}${C.reset}`);
  line();

  const bail = () => {
    error(`failed - log: ${log}`);
    stripAnsi(fs.readFileSync(log, 'utf8')).split('\n').slice(-18).forEach((l) => out(PAD + C.dim + '| ' + l + C.reset));
    return false;
  };

  task('📦 scaffold via simpl new');
  if (!run(`npx --yes @ijuantm/simpl new --local --version=latest --name="${NAME}" --url="${SITE_URL}"`, DEST, log)) return bail();

  if (CERT) {
    const certDir = path.join(proj, 'docker/certs');
    fs.mkdirSync(certDir, {recursive: true});
    fs.copyFileSync(CERT.crt, path.join(certDir, 'simpl.crt'));
    fs.copyFileSync(CERT.key, path.join(certDir, 'simpl.key'));
  }

  const hasSeedable = addons.some((a) => a !== 'db');
  for (const a of addons) {
    task(`🔀 merge add-on: ${a}`);
    if (!run(`npx --yes @ijuantm/simpl add ${a} --local`, proj, log)) return bail();
  }

  task('📦 composer install');
  if (!run('composer install --no-interaction --no-progress', proj, log)) return bail();
  task('🧪 composer test');
  if (!run('composer test', proj, log)) return bail();

  if (dbname) {
    if (DB_UP) {
      const envPath = path.join(proj, 'src/.env');
      let env = fs.readFileSync(envPath, 'utf8').replace(/^DB_NAME=.*/m, `DB_NAME=${dbname}`);
      // The db add-on's .env only has DB_SERVER, no DB_PORT, and DB.php interpolates DB_SERVER
      // straight into the PDO DSN after "host=" - so the Docker fallback's port is tacked onto
      // DB.host as a trailing ";port=..." DSN clause instead (see the Docker block above).
      if (DB.host !== 'localhost') env = env.replace(/^DB_SERVER=.*/m, `DB_SERVER=${DB.host}`);
      fs.writeFileSync(envPath, env);
      task('🧪 composer test:integration');
      if (!run('composer test:integration', proj, log)) return bail();
      task(`💾 composer migrate:fresh ${C.dim}(db: ${dbname})${C.reset}`);
      if (!run('composer migrate:fresh', proj, log)) return bail();
      if (hasSeedable) {
        task('🌱 composer seed:fresh');
        if (!run('composer seed:fresh', proj, log)) return bail();
      }
    } else {
      warn('MariaDB not reachable - skipped test:integration/migrate/seed');
    }
  }

  task(`🎨 npm install ${C.dim}(sass + vite build)${C.reset}`);
  if (!run('npm install --no-audit --no-fund', path.join(proj, 'src'), log)) return bail();

  const m = /OK \((\d+) tests, (\d+) assertions\)/.exec(fs.readFileSync(log, 'utf8'));
  line();
  success(m ? `OK (${m[1]} tests, ${m[2]} assertions)` : 'tests ran', true);
  if (dbname && DB_UP) item(`database: ${C.cyan}${dbname}${C.reset}`);
  item(`${C.cyan}${SITE_URL}${C.reset}  →  ${C.dim}${proj}${C.reset}`);
  return true;
}

// Appends a missing "127.0.0.1  simpl.test" line straight to the hosts file. Writing it needs
// elevated/admin privileges (this script isn't run elevated by default), so a failed write falls
// back to printing the line for the user to add by hand instead of silently doing nothing.
function ensureHosts() {
  const hostsFile = process.platform === 'win32'
    ? path.join(process.env.SystemRoot || 'C:\\Windows', 'System32', 'drivers', 'etc', 'hosts')
    : '/etc/hosts';
  let content = '';
  try {
    content = fs.readFileSync(hostsFile, 'utf8');
  } catch {
    return {hostsFile, written: false, failed: true}; // can't even read it - assume it's missing
  }
  const domain = DOMAIN.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  if (new RegExp(`^\\s*127\\.0\\.0\\.1\\s+${domain}\\s*$`, 'm').test(content)) return {hostsFile, written: false, failed: false};
  try {
    fs.appendFileSync(hostsFile, (content.endsWith('\n') ? '' : os.EOL) + `127.0.0.1  ${DOMAIN}` + os.EOL);
    return {hostsFile, written: true, failed: false};
  } catch {
    return {hostsFile, written: false, failed: true};
  }
}

function mkcertAvailable() {
  return spawnSync('mkcert', ['-version'], {shell: true, stdio: 'ignore'}).status === 0;
}

// One certificate covering simpl.test + localhost, generated once and copied into the install.
function writeMkcert() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simpl-fit-cert-'));
  // Registered here, not just after a successful copy in runInstall(), so a failed mkcert
  // run or an early bail doesn't leak this dir.
  process.on('exit', () => {
    try {
      fs.rmSync(dir, {recursive: true, force: true});
    } catch {
    }
  });
  const crt = path.join(dir, 'simpl.crt');
  const key = path.join(dir, 'simpl.key');
  const r = spawnSync('mkcert', ['-cert-file', crt, '-key-file', key, 'localhost', '127.0.0.1', DOMAIN], {shell: true, encoding: 'utf8'});
  return r.status === 0 ? {crt, key} : null;
}

function writeVhostConf() {
  const win = DEST.replace(/\\/g, '/');
  const conf = path.join(DEST, 'httpd-vhosts.conf');
  fs.writeFileSync(conf,
    `# Generated by scripts/fresh-install-test.mjs
#
# Vhost: ${DOMAIN}  ->  ${win}/simpl-test/src/public
#
# One-time Apache setup (WAMP, XAMPP, MAMP, a native install, ...):
#   1. httpd.conf: add        IncludeOptional "${win}/httpd-vhosts.conf"
#   2. hosts file: add a "127.0.0.1 ${DOMAIN}" line (see script output)
#   3. restart Apache
#
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot "${win}/simpl-test/src/public"

    <Directory "${win}/simpl-test/src/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
`);
  return conf;
}

box('Simpl Fresh Install Test');
if (!mode) mode = process.stdin.isTTY ? 'pick' : 'all';

const addons = mode === 'all' ? ADDONS : topo(DEPS, await pick());

let VERSION;
try {
  VERSION = await resolveLatest();
} catch (e) {
  die(`could not reach ${CDN_VERSIONS}: ${e.message}`);
}
if (!VERSION) die(`could not resolve 'latest' from ${CDN_VERSIONS}`);

const BUILD = fs.mkdtempSync(path.join(os.tmpdir(), 'simpl-fit-'));
const RELEASES_ROOT = path.join(BUILD, 'releases');
process.env.SIMPL_LOCAL_RELEASES = RELEASES_ROOT;
const RELEASES = path.join(RELEASES_ROOT, VERSION);
process.on('exit', () => {
  try {
    fs.rmSync(BUILD, {recursive: true, force: true});
  } catch {
  }
});

fs.rmSync(DEST, {recursive: true, force: true});
fs.mkdirSync(DEST, {recursive: true});

const needZip = new Set(addons);
divider();
heading(`Building ${VERSION} release zips`);
line();
task('🧰 core' + [...needZip].map((a) => ` + ${a}`).join(''));
try {
  buildZip('core', path.join(RELEASES, 'core.zip'));
  for (const a of ADDONS) if (needZip.has(a)) buildZip(`add-ons/${a}`, path.join(RELEASES, 'add-ons', `${a}.zip`));
} catch (e) {
  die(`failed to build release zip: ${e.message}`);
}
success(`Built ${1 + needZip.size} zip${needZip.size ? 's' : ''}`);
(DB_UP ? info : warn)(DB_UP
  ? `MariaDB reachable${dockerDbStarted ? '' : ` ${C.dim}(via local fallback)${C.reset}`}`
  : 'MariaDB not reachable (test:integration/migrate/seed will be skipped)');

if (mkcertAvailable()) {
  CERT = writeMkcert();
  (CERT ? info : warn)(CERT
    ? 'mkcert certificate generated (covers simpl.test + localhost)'
    : 'mkcert failed to generate a certificate - Docker HTTPS will use the untrusted self-signed one');
} else {
  warn('mkcert not found on PATH - Docker HTTPS will use the untrusted self-signed cert (see core/docker/README.md)');
}

const ok = runInstall(addons);

const conf = writeVhostConf();
divider();
heading('Summary');
line();
(ok ? success : error)(`${DOMAIN.padEnd(16)} ${C.dim}${SITE_URL}${C.reset}`);
line();
heading('Details');
item(`install: ${C.dim}${path.join(DEST, 'simpl-test')}${C.reset}`);
item(`docker:  ${C.dim}run \`docker compose up -d --build\` inside the install to browse it that way instead${C.reset}`);
item(`vhost:   ${C.dim}${conf}${C.reset} ${C.dim}(local Apache setup only)${C.reset}`);
item(`cert:    ${C.dim}${CERT ? "docker/certs/ in the install (Docker route only, trusted if you've run `mkcert -install`)" : 'not generated - install mkcert to remove the self-signed warning on the Docker route'}${C.reset}`);
const {hostsFile, written: hostsWritten, failed: hostsFailed} = ensureHosts();
if (hostsWritten) {
  item(`hosts file: added 1 line to ${hostsFile}`);
  out(PAD + PAD + C.dim + `127.0.0.1  ${DOMAIN}` + C.reset);
} else if (hostsFailed) {
  warn(`could not write ${hostsFile} (run this elevated, or add this line yourself):`);
  out(PAD + PAD + C.dim + `127.0.0.1  ${DOMAIN}` + C.reset);
} else {
  item('hosts file: already present');
}
line();
if (!ok) error(styled('Installation failed', C.bold, C.red), true);
else success(styled('Installation complete!', C.bold, C.green), true);
line();
process.exit(ok ? 0 : 1);
