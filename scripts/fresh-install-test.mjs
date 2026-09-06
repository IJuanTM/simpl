#!/usr/bin/env node
//
// Real fresh install of the CURRENT WORKING TREE via the actual installer ecosystem
// (`npx @ijuantm/simpl-install` + `npx @ijuantm/simpl-addon`). For the chosen add-on set
// it runs: real scaffold -> real add-on merges -> `composer install` -> `composer test` ->
// (with `db`) `composer migrate:fresh` (+ `seed:fresh` when another add-on is present,
// against a per-level db named <addons>-simpl) -> `npm install` (postinstall sass/vite
// build). So the install is ready to browse.
//
// Zips are rebuilt from the working tree every run (uncommitted edits included, via
// `git stash create`) and served to the installers through SIMPL_LOCAL_RELEASES. The CDN's
// versions.json is fetched once to resolve `latest`. MariaDB (WAMP, root / no password) is
// optional; if it's down the migrate/seed steps are skipped.
//
// Installs land in ~/Desktop/simpl-fresh-install-test/<level>/simpl-test/ (wiped each run,
// left after). Each is scaffolded with --url = <level>.simpl.test; a wildcard Apache vhost
// for those is written to <dest>/httpd-vhosts.conf. Overrides: SIMPL_TEST_DEST,
// SIMPL_TEST_DOMAIN.
//
// Run with no arguments for the interactive picker. Flags:
//   --all           run every cumulative level (core, core-db, core-db-auth, ...) - no menu
//   --only=<level>  run just that level - no menu
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
const DEST = process.env.SIMPL_TEST_DEST || path.join(os.homedir(), 'Desktop', 'simpl-fresh-install-test');
const DB = {host: 'localhost', user: 'root', pass: ''};

// Output helpers, matching the installer scripts' style.
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

let mode = null; // 'all' | { only: label } | 'pick'
for (const a of process.argv.slice(2)) {
  if (a === '--all') mode = 'all';
  else if (a.startsWith('--only=')) mode = {only: a.slice(7)};
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
  const out = [], seen = new Set();
  const visit = (n) => {
    if (!(n in deps) || seen.has(n)) return;
    seen.add(n);
    (deps[n] || []).forEach(visit);
    out.push(n);
  };
  (roots || Object.keys(deps)).forEach(visit);
  return out;
}

const DEPS = addonDeps();
const ADDONS = topo(DEPS);
if (!ADDONS.length) die(`no add-ons found under ${REPO}/add-ons`);

const levelLabel = (addons) => addons.length ? 'core-' + addons.join('-') : 'core';

const DB_UP = spawnSync('php', ['-r', 'try { new PDO("mysql:host=".getenv("H"), getenv("U"), getenv("P")); } catch (Throwable $e) { exit(1); }'],
  {env: {...process.env, H: DB.host, U: DB.user, P: DB.pass}}).status === 0;

// Core is always selected; a pick's dependencies fill in automatically.
async function pick() {
  const items = ['core', ...ADDONS];
  const on = new Set([0]); // the user's explicit picks; index 0 is core, always on
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
      const box =
        i === 0 ? `${C.gray}[x]${C.reset}` :
          on.has(i) ? `${C.green}[x]${C.reset}` :
            eff.has(i) ? `${C.cyan}[x]${C.reset}` : '[ ]';
      o.write(`\r\x1b[K${PAD}${ptr}${box} ${name}${notes[i] ? ' ' + notes[i] : ''}\n`);
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

function writeCoreEnv(proj, url) {
  fs.writeFileSync(path.join(proj, 'src/.env'), fs.readFileSync(path.join(REPO, 'core/src/.env'), 'utf8')
    .replaceAll('@app-name', NAME)
    .replaceAll('@app-url', url.replace(/\/+$/, '')));
}

function run(cmd, cwd, log) {
  const r = spawnSync(cmd, {cwd, shell: true, encoding: 'utf8', input: '', maxBuffer: 64 * 1024 * 1024});
  fs.appendFileSync(log, `\n$ ${cmd}\n${r.stdout || ''}${r.stderr || ''}`);
  return r.status === 0;
}

function runLevel(setAddons) {
  const label = levelLabel(setAddons);
  const ws = path.join(DEST, label);
  const proj = path.join(ws, 'simpl-test');
  const url = `http://${label}.${DOMAIN}/`;
  const log = path.join(DEST, label + '.log');
  const dbname = setAddons.includes('db') ? setAddons.join('-') + '-simpl' : null;
  fs.mkdirSync(ws, {recursive: true});
  fs.writeFileSync(log, '');
  box(`Installing: ${C.cyan}${label}${C.reset} ${C.dim}${url}${C.reset}`);
  line();

  const bail = () => {
    error(`failed - log: ${log}`);
    stripAnsi(fs.readFileSync(log, 'utf8')).split('\n').slice(-18).forEach((l) => out(PAD + C.dim + '| ' + l + C.reset));
    return false;
  };

  task('📦 scaffold via simpl-install');
  if (!run(`npx --yes @ijuantm/simpl-install --local --version=latest --name="${NAME}" --url="${url}"`, ws, log)) return bail();
  if (!fs.existsSync(path.join(proj, 'composer.json'))) {
    fs.appendFileSync(log, '\n>> installer did not scaffold a project\n');
    return bail();
  }
  writeCoreEnv(proj, url);

  const hasSeedable = setAddons.some((a) => a !== 'db');
  for (const a of setAddons) {
    task(`🔀 merge add-on: ${a}`);
    if (!run(`npx --yes @ijuantm/simpl-addon --local --addon=${a}`, proj, log)) return bail();
    if (!fs.readFileSync(path.join(proj, '.simpl'), 'utf8').includes(`"${a}"`)) {
      fs.appendFileSync(log, `\n>> '${a}' not recorded in .simpl\n`);
      return bail();
    }
  }

  task('📦 composer install');
  if (!run('composer install --no-interaction --no-progress', proj, log)) return bail();
  task('🧪 composer test');
  if (!run('composer test', proj, log)) return bail();

  if (dbname) {
    if (DB_UP) {
      const envPath = path.join(proj, 'src/.env');
      fs.writeFileSync(envPath, fs.readFileSync(envPath, 'utf8').replace(/^DB_NAME=.*/m, `DB_NAME=${dbname}`));
      task(`💾 composer migrate:fresh ${C.dim}(db: ${dbname})${C.reset}`);
      if (!run('composer migrate:fresh', proj, log)) return bail();
      if (hasSeedable) {
        task('🌱 composer seed:fresh');
        if (!run('composer seed:fresh', proj, log)) return bail();
      }
    } else {
      warn('MariaDB not reachable - skipped migrate/seed');
    }
  }

  task(`🎨 npm install ${C.dim}(sass + vite build)${C.reset}`);
  if (!run('npm install --no-audit --no-fund', path.join(proj, 'src'), log)) return bail();

  const m = /OK \((\d+) tests, (\d+) assertions\)/.exec(fs.readFileSync(log, 'utf8'));
  line();
  success(m ? `OK (${m[1]} tests, ${m[2]} assertions)` : 'tests ran', true);
  if (dbname && DB_UP) item(`database: ${C.cyan}${dbname}${C.reset}`);
  item(`${C.cyan}${url}${C.reset}  →  ${C.dim}${proj}${C.reset}`);
  return true;
}

function writeVhostConf() {
  const win = DEST.replace(/\\/g, '/');
  const conf = path.join(DEST, 'httpd-vhosts.conf');
  fs.writeFileSync(conf,
    `# Generated by scripts/fresh-install-test.mjs
#
# Wildcard vhost: <level>.${DOMAIN}  ->  ${win}/<level>/simpl-test/src/public
#
# One-time WAMP setup:
#   1. httpd.conf: uncomment  LoadModule vhost_alias_module modules/mod_vhost_alias.so
#   2. httpd.conf: add        Include "${win}/httpd-vhosts.conf"
#   3. hosts file: add a "127.0.0.1 <level>.${DOMAIN}" line per level (see script output)
#   4. restart Apache (WAMP tray)
#
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias *.${DOMAIN}
    UseCanonicalName Off
    VirtualDocumentRoot "${win}/%1/simpl-test/src/public"

    <Directory "${win}">
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

let sets; // one add-on array per level, each dep-ordered
if (mode === 'pick') {
  sets = [topo(DEPS, await pick())];
} else {
  sets = [[]];
  const acc = [];
  for (const a of ADDONS) {
    acc.push(a);
    sets.push([...acc]);
  }
  if (mode.only) {
    const want = sets.find((s) => levelLabel(s) === mode.only);
    if (!want) die(`--only=${mode.only} matched no level`);
    sets = [want];
  }
}

const VERSION = await resolveLatest();
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

const needZip = new Set(sets.flat());
divider();
heading(`Building ${VERSION} release zips`);
line();
task('🧰 core' + [...needZip].map((a) => ` + ${a}`).join(''));
buildZip('core', path.join(RELEASES, 'core.zip'));
for (const a of ADDONS) if (needZip.has(a)) buildZip(`add-ons/${a}`, path.join(RELEASES, 'add-ons', `${a}.zip`));
success(`Built ${1 + needZip.size} zip${needZip.size ? 's' : ''}`);
(DB_UP ? info : warn)(`MariaDB ${DB_UP ? 'reachable' : 'not reachable (migrate/seed will be skipped)'}`);

const results = {};
for (const s of sets) results[levelLabel(s)] = runLevel(s);
const failed = Object.values(results).includes(false);

const conf = writeVhostConf();
divider();
heading('Summary');
line();
for (const [label, ok] of Object.entries(results))
  (ok ? success : error)(`${label.padEnd(16)} ${C.dim}http://${label}.${DOMAIN}/${C.reset}`);
line();
heading('Details');
item(`installs: ${C.dim}${DEST}${C.reset}`);
item(`vhost:    ${C.dim}${conf}${C.reset}`);
item('hosts file lines:');
for (const label of Object.keys(results)) out(PAD + PAD + C.dim + `127.0.0.1  ${label}.${DOMAIN}` + C.reset);
line();
if (failed) error(styled('Some levels failed', C.bold, C.red), true);
else success(styled('Installation complete!', C.bold, C.green), true);
line();
process.exit(failed ? 1 : 0);
