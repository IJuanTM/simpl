#!/usr/bin/env node
import {spawnSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {buildZips, C, DEV_DIR, die, divider, DOMAIN, error, heading, info, installBox, item, line, listZips, out, PAD, plural, RELEASES_DIR, REPO, row, SIMPL, SITE_URL, snapshotWorkingTree, stripAnsi, styled, success, task, TEST_NAME, TEST_PROJECT as PROJECT, titleBox, warn} from './shared.mjs';

const LOG = path.join(DEV_DIR, 'install.log');
const SIMPL_TEST_DB = process.env.SIMPL_TEST_DB || 'auto'; // 'auto' | 'local' | 'docker'
const LOCAL_DB = {host: 'localhost', user: 'root', pass: ''};

const help = () => {
  titleBox('Fresh install test');
  line();
  heading('Usage:');
  row('node scripts/fresh-install-test.mjs [options]');
  line();
  heading('Options:');
  row('--all', 'Install every add-on, without the picker');
  row('--release[=<version>]', 'Install a release.mjs --local release instead of the working tree (default: core/.simpl)');
  row('--help, -h', 'Show this help message');
  line();
  heading('Environment:');
  row('SIMPL_DEV_DIR', 'Folder the install lands in (default: ~/Desktop/simpl-dev)');
  row('SIMPL_TEST_DOMAIN', 'Domain to install at (default: simpl.test)');
  row('SIMPL_TEST_DB', 'auto, docker or local (default: auto)');
  line();
  heading('Note:');
  item('See scripts/README.md for how the database, browsing, hosts file and certificate work.');
  line();
};

let mode = process.stdin.isTTY ? 'pick' : 'all';
let release = null;
for (const arg of process.argv.slice(2)) {
  if (arg === '--all') mode = 'all';
  else if (arg === '--release' || arg.startsWith('--release=')) release = arg.split('=')[1] || true;
  else if (arg === '-h' || arg === '--help') {
    help();
    process.exit(0);
  } else die(`Unknown option: ${arg}`, 'Run with --help to see all available options.');
}

for (const bin of ['php', 'node', 'npm', 'npx', 'composer', 'git']) {
  if (spawnSync(`${bin} --version`, {shell: true, stdio: 'ignore'}).status !== 0) die(`Missing required tool: ${bin}`);
}

const readAddonDependencies = () => {
  const dir = path.join(REPO, 'add-ons');
  const names = fs.readdirSync(dir, {withFileTypes: true}).filter(entry => entry.isDirectory()).map(entry => entry.name);
  const dependencies = {};
  for (const name of names) {
    try {
      dependencies[name] = JSON.parse(fs.readFileSync(path.join(dir, name, 'addon.json'), 'utf8')).dependencies || [];
    } catch {
      dependencies[name] = [];
    }
  }
  return dependencies;
};

const topo = (dependencies, roots) => {
  const order = [], seen = new Set();
  const visit = (name) => {
    if (!(name in dependencies) || seen.has(name)) return;
    seen.add(name);
    (dependencies[name] || []).forEach(visit);
    order.push(name);
  };
  (roots || Object.keys(dependencies)).forEach(visit);
  return order;
};

const addonDependencies = readAddonDependencies();
const allAddons = topo(addonDependencies);
if (!allAddons.length) die(`No add-ons found under ${REPO}/add-ons`);

const pdoUp = (db) => spawnSync('php', ['-r', 'try { new PDO("mysql:host=".getenv("H"), getenv("U"), getenv("P")); } catch (Throwable $e) { exit(1); }'],
  {env: {...process.env, H: db.host, U: db.user, P: db.pass}}).status === 0;

const pick = async () => {
  const items = ['core', ...allAddons];
  const picked = new Set(items.map((_, i) => i)); // index 0 is core, always on
  const notes = ['', ...allAddons.map(addon => {
    const requires = topo(addonDependencies, [addon]).filter(dep => dep !== addon);
    return requires.length ? `${C.gray}(requires: ${requires.join(', ')})${C.reset}` : '';
  })];
  const withDependencies = () => new Set(
    topo(addonDependencies, [...picked].filter(i => i !== 0).map(i => items[i])).map(addon => 1 + allAddons.indexOf(addon)),
  );
  let cursor = 0, drawn = 0;
  const stdout = process.stdout;

  heading('Select what to install:');

  const draw = () => {
    if (drawn) stdout.write(`\x1b[${drawn}A`);
    const selected = withDependencies();
    items.forEach((name, i) => {
      const pointer = i === cursor ? `${C.cyan}❯${C.reset} ` : '  ';
      const checkbox =
        i === 0 ? `${C.gray}[x]${C.reset}` :
          picked.has(i) ? `${C.green}[x]${C.reset}` :
            selected.has(i) ? `${C.cyan}[x]${C.reset}` : '[ ]';
      stdout.write(`\r\x1b[K${PAD}${pointer}${checkbox} ${name}${notes[i] ? ' ' + notes[i] : ''}\n`);
    });
    stdout.write(`\r\x1b[K${PAD}${C.dim}↑/↓ move · space select · click · a all · n none · enter run · q cancel${C.reset}\n`);
    stdout.write(`\r\x1b[K${PAD}${C.green}[x]${C.dim} selected    ${C.reset}${C.cyan}[x]${C.reset}${C.dim}${C.cyan} required by a selection${C.reset}\n`);
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
    stdout.write('\x1b[?25h\x1b[?1000l\x1b[?1006l');
  };

  if (stdin.isTTY) {
    stdin.setRawMode(true);
    listTop = await new Promise(resolve => {
      const onResponse = (data) => {
        const match = /\x1b\[(\d+);\d+R/.exec(data.toString());
        if (match) {
          stdin.off('data', onResponse);
          resolve(+match[1]);
        }
      };
      stdin.on('data', onResponse);
      stdout.write('\x1b[6n');
      setTimeout(() => {
        stdin.off('data', onResponse);
        resolve(0);
      }, 300);
    });
    stdout.write('\x1b[?25l\x1b[?1000h\x1b[?1006h');
  }
  stdin.resume();
  stdin.setEncoding('utf8');
  draw();

  const chosen = await new Promise(resolve => {
    const finish = (value) => {
      stdin.off('data', onData);
      restore();
      process.off('exit', restore);
      resolve(value);
    };
    const onData = (data) => {
      const key = data.toString();
      if (key === '\x03' || key === 'q' || key === 'Q' || key === '\x1b') {
        restore();
        line();
        info('Cancelled');
        line();
        process.exit(key === '\x03' ? 130 : 0);
      } else if (key === '\x1b[A') cursor = Math.max(0, cursor - 1);
      else if (key === '\x1b[B') cursor = Math.min(items.length - 1, cursor + 1);
      else if (key === ' ' && cursor !== 0) picked.has(cursor) ? picked.delete(cursor) : picked.add(cursor);
      else if (key === 'a' || key === 'A') items.forEach((_, i) => picked.add(i));
      else if (key === 'n' || key === 'N') {
        picked.clear();
        picked.add(0);
      } else if (key === '\r' || key === '\n') return finish([...picked].filter(i => i !== 0).map(i => items[i]));
      else {
        const click = /\x1b\[<(\d+);(\d+);(\d+)M/.exec(key); // SGR mouse press
        if (click && +click[1] === 0) {
          const i = +click[3] - listTop;
          if (i >= 1 && i < items.length) {
            cursor = i;
            picked.has(i) ? picked.delete(i) : picked.add(i);
          }
        }
      }
      draw();
    };
    process.on('exit', restore);
    stdin.on('data', onData);
  });

  stdin.pause();
  return chosen;
};

// Compose names a stack after its folder, so a simpl-test stack still running from any folder holds the ports and gets `simpl composer` run inside it.
const stopRunningStack = () => {
  const name = process.env.COMPOSE_PROJECT_NAME || path.basename(PROJECT);
  const running = () => spawnSync('docker', ['compose', '-p', name, 'ps', '-q', '--status', 'running'], {encoding: 'utf8'}).stdout?.trim();
  if (!running()) return;
  task(`🛑 Stopping the running ${C.cyan}${name}${C.reset} Docker stack...`);
  line();
  spawnSync('docker', ['compose', '-p', name, 'down', '-v'], {stdio: 'ignore'});
  if (running()) die(`The ${name} Docker stack is still running and could not be stopped`, 'Stop it with simpl down in its project folder, then run this again.');
};

// The install's own Docker stack serves the database it keeps using afterwards, so it is preferred over a local server.
const findDatabase = () => {
  if (SIMPL_TEST_DB !== 'local' && spawnSync('docker', ['info'], {stdio: 'ignore'}).status === 0) return 'docker';
  return SIMPL_TEST_DB !== 'docker' && pdoUp(LOCAL_DB) ? 'local' : null;
};

const run = (command, cwd, log) => {
  const result = spawnSync(command, {cwd, shell: true, encoding: 'utf8', input: '', maxBuffer: 64 * 1024 * 1024});
  fs.appendFileSync(log, `\n$ ${command}\n${result.stdout || ''}${result.stderr || ''}`);
  return result.status === 0;
};

const runInstall = (addons) => {
  const dbName = addons.includes('db') ? 'simpl-test' : null;
  fs.writeFileSync(LOG, '');
  installBox(TEST_NAME, version);
  line();

  const step = (msg) => {
    task(msg);
    line();
  };
  const bail = () => {
    error(`Failed, see the log: ${LOG}`);
    stripAnsi(fs.readFileSync(LOG, 'utf8')).split('\n').slice(-18).forEach(logLine => out(PAD + C.dim + '| ' + logLine + C.reset));
    return false;
  };

  step(`🏗️ Creating the project ${C.dim}(simpl new)${C.reset}...`);
  if (!run(`${SIMPL} new --local --version=${version} --name="${TEST_NAME}" --url="${SITE_URL}"`, DEV_DIR, LOG)) return bail();

  if (cert) {
    const certDir = path.join(PROJECT, 'docker/certs');
    fs.mkdirSync(certDir, {recursive: true});
    fs.copyFileSync(cert.crt, path.join(certDir, 'simpl.crt'));
    fs.copyFileSync(cert.key, path.join(certDir, 'simpl.key'));
  }

  const hasSeedable = addons.some(addon => addon !== 'db');
  for (const addon of addons) {
    step(`🔀 Adding the ${C.cyan}${addon}${C.reset} add-on ${C.dim}(simpl add)${C.reset}...`);
    if (!run(`${SIMPL} add ${addon} --local`, PROJECT, LOG)) return bail();
  }

  step(`📦 Installing dependencies ${C.dim}(simpl composer install)${C.reset}...`);
  if (!run(`${SIMPL} composer install --no-interaction --no-progress`, PROJECT, LOG)) return bail();
  step(`🧪 Running the tests ${C.dim}(simpl test)${C.reset}...`);
  if (!run(`${SIMPL} test`, PROJECT, LOG)) return bail();

  if (dbName) {
    if (db) {
      fs.writeFileSync(path.join(PROJECT, 'src/.env'), fs.readFileSync(path.join(PROJECT, 'src/.env'), 'utf8').replace(/^DB_NAME=.*/m, `DB_NAME=${dbName}`));
      if (db === 'docker') {
        step(`🐳 Starting the Docker stack ${C.dim}(simpl up)${C.reset}...`);
        if (!run(`${SIMPL} up`, PROJECT, LOG)) return bail();
      }
      step(`🧪 Running the integration tests ${C.dim}(simpl test:integration)${C.reset}...`);
      if (!run(`${SIMPL} test:integration`, PROJECT, LOG)) return bail();
      step(`💾 Migrating ${C.cyan}${dbName}${C.reset} ${C.dim}(simpl migrate:fresh)${C.reset}...`);
      if (!run(`${SIMPL} migrate:fresh`, PROJECT, LOG)) return bail();
      if (hasSeedable) {
        step(`🌱 Seeding ${C.dim}(simpl seed:fresh)${C.reset}...`);
        if (!run(`${SIMPL} seed:fresh`, PROJECT, LOG)) return bail();
      }
    } else {
      warn('MariaDB not reachable, skipped test:integration/migrate/seed');
      line();
    }
  }

  step(`🎨 Running npm install ${C.dim}(sass + vite build)${C.reset}...`);
  if (!run('npm install --no-audit --no-fund', path.join(PROJECT, 'src'), LOG)) return bail();

  const tests = /OK \((\d+) tests, (\d+) assertions\)/.exec(fs.readFileSync(LOG, 'utf8'));
  success(tests ? `OK (${tests[1]} tests, ${tests[2]} assertions)` : 'Tests ran', true);
  if (dbName && db) item(`Database: ${C.cyan}${dbName}${C.reset} ${C.dim}(${db === 'docker' ? 'in the Docker stack' : 'on the local server'})${C.reset}`);
  item(`${C.cyan}${SITE_URL}${C.reset}  →  ${C.dim}${PROJECT}${C.reset}`);
  return true;
};

// Writing the hosts file needs admin rights, so a failed write falls back to printing the line for the user to add.
const ensureHosts = () => {
  const hostsFile = process.platform === 'win32'
    ? path.join(process.env.SystemRoot || 'C:\\Windows', 'System32', 'drivers', 'etc', 'hosts')
    : '/etc/hosts';
  let content = '';
  try {
    content = fs.readFileSync(hostsFile, 'utf8');
  } catch {
    return {hostsFile, written: false, failed: true};
  }
  const domain = DOMAIN.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  if (new RegExp(`^\\s*127\\.0\\.0\\.1\\s+${domain}\\s*$`, 'm').test(content)) return {hostsFile, written: false, failed: false};
  try {
    fs.appendFileSync(hostsFile, (content.endsWith('\n') ? '' : os.EOL) + `127.0.0.1  ${DOMAIN}` + os.EOL);
    return {hostsFile, written: true, failed: false};
  } catch {
    return {hostsFile, written: false, failed: true};
  }
};

const mkcertAvailable = () => spawnSync('mkcert', ['-version'], {shell: true, stdio: 'ignore'}).status === 0;

const writeMkcert = () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simpl-fit-cert-'));

  // Registered before mkcert runs, so a failed run or an early bail doesn't leak the folder.
  process.on('exit', () => {
    try {
      fs.rmSync(dir, {recursive: true, force: true});
    } catch {
    }
  });
  const crt = path.join(dir, 'simpl.crt');
  const key = path.join(dir, 'simpl.key');
  const result = spawnSync('mkcert', ['-cert-file', crt, '-key-file', key, 'localhost', '127.0.0.1', DOMAIN], {shell: true, encoding: 'utf8'});
  return result.status === 0 ? {crt, key} : null;
};

const writeVhostConf = () => {
  const apacheDest = DEV_DIR.replace(/\\/g, '/');
  const conf = path.join(DEV_DIR, 'httpd-vhosts.conf');
  fs.writeFileSync(conf,
    `# Generated by scripts/fresh-install-test.mjs
#
# Vhost: ${DOMAIN}  ->  ${apacheDest}/simpl-test/src/public
#
# One-time Apache setup (WAMP, XAMPP, MAMP, a native install, ...):
#   1. httpd.conf: add        IncludeOptional "${apacheDest}/httpd-vhosts.conf"
#   2. hosts file: add a "127.0.0.1 ${DOMAIN}" line (see script output)
#   3. restart Apache
#
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot "${apacheDest}/simpl-test/src/public"

    <Directory "${apacheDest}/simpl-test/src/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
`);
  return conf;
};

titleBox('Fresh install test');
line();

const addons = mode === 'all' ? allAddons : topo(addonDependencies, await pick());
if (mode === 'pick') divider();

// `simpl add` looks its zips up under the version in the project's .simpl, which comes straight from this core/.simpl.
const version = typeof release === 'string' ? release : JSON.parse(fs.readFileSync(path.join(REPO, 'core', '.simpl'), 'utf8')).version;

stopRunningStack();
try {
  fs.rmSync(PROJECT, {recursive: true, force: true});
  fs.rmSync(LOG, {force: true});
} catch (err) {
  die(`Could not delete the previous install (${err.code})`, `Something still has ${PROJECT} open: close any terminal, editor or file explorer window that is in it, then run this again.`);
}
fs.mkdirSync(DEV_DIR, {recursive: true});

if (release) {
  const missing = ['core', ...addons].filter(name => !fs.existsSync(path.join(RELEASES_DIR, version, name === 'core' ? 'core.zip' : `add-ons/${name}.zip`)));
  if (missing.length) die(`The local ${version} release has no ${missing.join(', ')} zip`, `Build it first: node scripts/release.mjs ${version} --local`);
  process.env.SIMPL_LOCAL_RELEASES = RELEASES_DIR;
  info(`Using the local ${version} release ${C.dim}(${path.join(RELEASES_DIR, version)})${C.reset}`);
  line();
} else {
  // Kept out of RELEASES_DIR, since the working tree shares its version number with the tagged local release there.
  const build = fs.mkdtempSync(path.join(os.tmpdir(), 'simpl-fit-'));
  process.on('exit', () => {
    try {
      fs.rmSync(build, {recursive: true, force: true});
    } catch {
    }
  });
  process.env.SIMPL_LOCAL_RELEASES = path.join(build, 'releases');
  const zips = path.join(build, 'releases', version);
  task(`🧰 Building ${version} zips from the working tree...`);
  try {
    buildZips(snapshotWorkingTree(build, addons), zips, addons);
  } catch (err) {
    die('Could not build the release zips', err.stderr?.trim() || err.message);
  }
  line();
  success(`Built ${plural(1 + addons.length, 'zip')}`);
  listZips(zips, addons);
  line();
}

task('💾 Looking for a database...');
const db = findDatabase();
line();
if (db === 'docker') info(`Docker is running, the install's own stack will serve the database`);
else if (db === 'local') info(`Using the local MariaDB/MySQL server ${C.dim}(Docker not running)${C.reset}`);
else warn('No database: Docker is not running and no local MariaDB/MySQL answers (test:integration/migrate/seed will be skipped)');

let cert = null;
if (mkcertAvailable()) {
  cert = writeMkcert();
  if (cert) info('mkcert certificate generated (covers simpl.test + localhost)');
  else warn('mkcert failed to generate a certificate, Docker HTTPS will use the untrusted self-signed one');
} else {
  warn('mkcert not found on PATH, Docker HTTPS will use the untrusted self-signed cert (see core/docker/README.md)');
}

const ok = runInstall(addons);

const conf = writeVhostConf();
divider();
heading('Summary:');
(ok ? success : error)(`${DOMAIN} ${C.dim}${SITE_URL}${C.reset}`);
line();
heading('Details:');
item(`Install: ${C.dim}${PROJECT}${C.reset} ${C.dim}(from ${release ? `the local ${version} release` : 'the working tree'})${C.reset}`);
item(`Log:     ${C.dim}${LOG}${C.reset}`);
item(`Docker:  ${C.dim}${db === 'docker' && addons.includes('db') ? 'running, stop it with `simpl down` inside the install' : 'run `simpl up` inside the install to browse it that way instead'}${C.reset}`);
item(`Vhost:   ${C.dim}${conf}${C.reset} ${C.dim}(local Apache setup only)${C.reset}`);
item(`Cert:    ${C.dim}${cert ? "docker/certs/ in the install (Docker route only, trusted if you've run `mkcert -install`)" : 'not generated, install mkcert to remove the self-signed warning on the Docker route'}${C.reset}`);
const {hostsFile, written: hostsWritten, failed: hostsFailed} = ensureHosts();
if (hostsWritten) {
  item(`Hosts:   ${C.dim}added 1 line to ${hostsFile}${C.reset}`);
  out(PAD + PAD + C.dim + `127.0.0.1  ${DOMAIN}` + C.reset);
} else if (hostsFailed) {
  warn(`Could not write ${hostsFile} (run this elevated, or add this line yourself):`);
  out(PAD + PAD + C.dim + `127.0.0.1  ${DOMAIN}` + C.reset);
} else {
  item(`Hosts:   ${C.dim}already present${C.reset}`);
}
line();
if (!ok) error(styled('Installation failed', C.bold, C.red), true);
else success(styled('Installation complete!', C.bold, C.green), true);
line();
process.exit(ok ? 0 : 1);
