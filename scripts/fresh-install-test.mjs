#!/usr/bin/env node
import {execFileSync, spawnSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CDN_VERSIONS = 'https://cdn.simpl.iwanvanderwal.nl/framework/versions.json';
const NAME = 'Simpl Test';
const DOMAIN = process.env.SIMPL_TEST_DOMAIN || 'simpl.test';
const SITE_URL = `https://${DOMAIN}/`;
const DEST = process.env.SIMPL_TEST_DEST || path.join(os.homedir(), 'Desktop', 'simpl-fresh-install-test');
const SIMPL_TEST_DB = process.env.SIMPL_TEST_DB || 'auto'; // 'auto' | 'local' | 'docker'
const SIMPL_TEST_DB_PORT = process.env.SIMPL_TEST_DB_PORT || '3307';
const DOCKER_COMPOSE = path.join(REPO, 'scripts', 'compose.yaml');
const LOCAL_DB = {host: 'localhost', user: 'root', pass: ''};

// Output helpers, copied from the Simpl CLI's lib/ui.js.
const CODES = {
  reset: '\x1b[0m', green: '\x1b[32m', yellow: '\x1b[33m', red: '\x1b[31m',
  cyan: '\x1b[36m', blue: '\x1b[34m', gray: '\x1b[90m', bold: '\x1b[1m', dim: '\x1b[2m',
};
const C = Object.fromEntries(Object.entries(CODES).map(([name, code]) => [name, process.stdout.hasColors?.() ? code : '']));
const BOX_WIDTH = 62;
const PAD = '  ';
const styled = (msg, ...styles) => styles.join('') + msg + C.reset;
const line = (msg = '') => console.log(msg);
const out = (msg, color = C.reset) => console.log(color + msg + C.reset);
const prefixed = (symbol, color, msg, bold = false, dim = false) => out(PAD + color + symbol + C.reset + ' ' + (bold ? styled(msg, C.bold) : dim ? styled(msg, C.dim) : msg));
const success = (msg, bold = false) => prefixed('✓', C.green, msg, bold);
const error = (msg, bold = false) => prefixed('✕', C.red, msg, bold);
const warn = (msg, bold = false) => prefixed('⚠', C.yellow, msg, bold);
const info = (msg) => prefixed('◌', C.cyan, msg, false, true);
const task = (msg) => out(PAD + msg);
const item = (msg, dim = false) => out(PAD + C.cyan + '•' + C.reset + ' ' + (dim ? styled(msg, C.dim) : msg));
const heading = (msg) => out(PAD + styled(msg, C.bold), C.blue);
const plural = (count, word) => `${styled(String(count), C.bold)} ${word}${count !== 1 ? 's' : ''}`;
const row = (left, right = '') => out(PAD + styled(right ? left.padEnd(30) : left, C.dim) + right);
const box = (title) => {
  const parts = title.split(/(\x1b\[[0-9;]*m)/);
  const length = parts.reduce((sum, part, i) => i % 2 ? sum : sum + part.length, 0);
  let displayTitle = title, remaining = BOX_WIDTH - 5;
  if (length > BOX_WIDTH - 2) displayTitle = parts.map((part, i) => {
    if (i % 2) return part;
    const kept = part.slice(0, remaining);
    remaining -= kept.length;
    return kept;
  }).join('') + '...';
  const spaces = ' '.repeat(Math.max(0, BOX_WIDTH - 2 - length));
  line();
  out(PAD + '╭' + '─'.repeat(BOX_WIDTH) + '╮');
  out(PAD + '│ ' + styled(displayTitle, C.bold) + spaces + ' │');
  out(PAD + '╰' + '─'.repeat(BOX_WIDTH) + '╯');
};
const installBox = (name, version) => box(`Installing: ${C.cyan}${name}${C.reset} ${C.dim}(v${version})${C.reset}`);
const titleBox = (title, detail) => box(`Simpl ${C.dim}-${C.reset} ${C.blue}${title}${C.reset}` + (detail ? ` ${C.dim}(${detail})${C.reset}` : ''));
const divider = () => {
  line();
  out(PAD + '─'.repeat(16), C.dim);
  line();
};
const die = (msg, ...hints) => {
  line();
  error(msg);
  hints.forEach(info);
  line();
  process.exit(1);
};
const stripAnsi = (text) => text.replace(/\x1b\[[0-9;]*m/g, '');

const help = () => {
  titleBox('Fresh install test');
  line();
  heading('Usage:');
  row('node scripts/fresh-install-test.mjs [options]');
  line();
  heading('Options:');
  row('--all', 'Install every add-on, without the picker');
  row('--help, -h', 'Show this help message');
  line();
  heading('Environment:');
  row('SIMPL_TEST_DEST', 'Install folder (default: ~/Desktop/simpl-fresh-install-test)');
  row('SIMPL_TEST_DOMAIN', 'Domain to install at (default: simpl.test)');
  row('SIMPL_TEST_DB', 'auto, docker or local (default: auto)');
  row('SIMPL_TEST_DB_PORT', 'Host port for the Docker database (default: 3307)');
  line();
  heading('Note:');
  item('See scripts/README.md for how the database, browsing, hosts file and certificate work.');
  line();
};

let mode = process.stdin.isTTY ? 'pick' : 'all';
for (const arg of process.argv.slice(2)) {
  if (arg === '--all') mode = 'all';
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

const resolveLatest = async () => {
  let versions;
  try {
    ({versions} = await (await fetch(CDN_VERSIONS, {signal: AbortSignal.timeout(10_000)})).json());
  } catch {
    die('The CDN server is currently unreachable', 'Please try again later.');
  }
  if (!versions || !Object.keys(versions).length) die('The CDN returned no versions');
  return Object.entries(versions).find(([, meta]) => meta['is-latest'] === true)?.[0] ?? Object.keys(versions)[0];
};

const buildZip = (subdir, outZip) => {
  fs.mkdirSync(path.dirname(outZip), {recursive: true});
  const tree = execFileSync('git', ['stash', 'create'], {cwd: REPO, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']}).trim() || 'HEAD';
  execFileSync('git', ['archive', '--format=zip', '-o', outZip, `${tree}:${subdir}`], {cwd: REPO, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']});
};

const connectDatabase = () => {
  if (SIMPL_TEST_DB !== 'local' && spawnSync('docker', ['compose', 'version'], {stdio: 'ignore'}).status === 0
    && spawnSync('docker', ['compose', '-f', DOCKER_COMPOSE, 'up', '-d', 'db', '--wait'], {stdio: 'ignore', env: {...process.env, SIMPL_TEST_DB_PORT}}).status === 0) {
    process.on('exit', () => {
      try {
        spawnSync('docker', ['compose', '-f', DOCKER_COMPOSE, 'down', '-v'], {stdio: 'ignore', env: {...process.env, SIMPL_TEST_DB_PORT}});
      } catch {
      }
    });
    const docker = {host: `127.0.0.1;port=${SIMPL_TEST_DB_PORT}`, user: 'root', pass: '', docker: true};
    if (pdoUp(docker)) return docker;
  }
  return SIMPL_TEST_DB !== 'docker' && pdoUp(LOCAL_DB) ? LOCAL_DB : null;
};

const run = (command, cwd, log) => {
  const result = spawnSync(command, {cwd, shell: true, encoding: 'utf8', input: '', maxBuffer: 64 * 1024 * 1024});
  fs.appendFileSync(log, `\n$ ${command}\n${result.stdout || ''}${result.stderr || ''}`);
  return result.status === 0;
};

const runInstall = (addons) => {
  const project = path.join(DEST, 'simpl-test');
  const log = path.join(DEST, 'install.log');
  const dbName = addons.includes('db') ? 'simpl-test' : null;
  fs.writeFileSync(log, '');
  installBox(NAME, version);
  line();

  const step = (msg) => {
    task(msg);
    line();
  };
  const bail = () => {
    error(`Failed, see the log: ${log}`);
    stripAnsi(fs.readFileSync(log, 'utf8')).split('\n').slice(-18).forEach(logLine => out(PAD + C.dim + '| ' + logLine + C.reset));
    return false;
  };

  step(`🏗️ Creating the project ${C.dim}(simpl new)${C.reset}...`);
  if (!run(`npx --yes @ijuantm/simpl new --local --version=latest --name="${NAME}" --url="${SITE_URL}"`, DEST, log)) return bail();

  if (cert) {
    const certDir = path.join(project, 'docker/certs');
    fs.mkdirSync(certDir, {recursive: true});
    fs.copyFileSync(cert.crt, path.join(certDir, 'simpl.crt'));
    fs.copyFileSync(cert.key, path.join(certDir, 'simpl.key'));
  }

  const hasSeedable = addons.some(addon => addon !== 'db');
  for (const addon of addons) {
    step(`🔀 Adding the ${C.cyan}${addon}${C.reset} add-on ${C.dim}(simpl add)${C.reset}...`);
    if (!run(`npx --yes @ijuantm/simpl add ${addon} --local`, project, log)) return bail();
  }

  step('📦 Running composer install...');
  if (!run('composer install --no-interaction --no-progress', project, log)) return bail();
  step('🧪 Running composer test...');
  if (!run('composer test', project, log)) return bail();

  if (dbName) {
    if (db) {
      const envPath = path.join(project, 'src/.env');
      let env = fs.readFileSync(envPath, 'utf8').replace(/^DB_NAME=.*/m, `DB_NAME=${dbName}`);

      // DB.php puts DB_SERVER straight after "host=" in the PDO DSN and there's no DB_PORT, so the Docker port rides along as a ";port=" clause.
      if (db.docker) env = env.replace(/^DB_SERVER=.*/m, `DB_SERVER=${db.host}`);
      fs.writeFileSync(envPath, env);
      step('🧪 Running composer test:integration...');
      if (!run('composer test:integration', project, log)) return bail();
      step(`💾 Running composer migrate:fresh ${C.dim}(db: ${dbName})${C.reset}...`);
      if (!run('composer migrate:fresh', project, log)) return bail();
      if (hasSeedable) {
        step('🌱 Running composer seed:fresh...');
        if (!run('composer seed:fresh', project, log)) return bail();
      }
    } else {
      warn('MariaDB not reachable, skipped test:integration/migrate/seed');
      line();
    }
  }

  step(`🎨 Running npm install ${C.dim}(sass + vite build)${C.reset}...`);
  if (!run('npm install --no-audit --no-fund', path.join(project, 'src'), log)) return bail();

  const tests = /OK \((\d+) tests, (\d+) assertions\)/.exec(fs.readFileSync(log, 'utf8'));
  success(tests ? `OK (${tests[1]} tests, ${tests[2]} assertions)` : 'Tests ran', true);
  if (dbName && db) item(`Database: ${C.cyan}${dbName}${C.reset}`);
  item(`${C.cyan}${SITE_URL}${C.reset}  →  ${C.dim}${project}${C.reset}`);
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
  const apacheDest = DEST.replace(/\\/g, '/');
  const conf = path.join(DEST, 'httpd-vhosts.conf');
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

task('📦 Fetching available versions...');
const version = await resolveLatest();
line();

const build = fs.mkdtempSync(path.join(os.tmpdir(), 'simpl-fit-'));
const releasesRoot = path.join(build, 'releases');
process.env.SIMPL_LOCAL_RELEASES = releasesRoot;
const releases = path.join(releasesRoot, version);
process.on('exit', () => {
  try {
    fs.rmSync(build, {recursive: true, force: true});
  } catch {
  }
});

fs.rmSync(DEST, {recursive: true, force: true});
fs.mkdirSync(DEST, {recursive: true});

task(`🧰 Building ${version} release zips...`);
try {
  buildZip('core', path.join(releases, 'core.zip'));
  for (const addon of addons) buildZip(`add-ons/${addon}`, path.join(releases, 'add-ons', `${addon}.zip`));
} catch (err) {
  die('Could not build the release zips', err.stderr?.trim() || err.message);
}
line();
success(`Built ${plural(1 + addons.length, 'zip')} ${C.dim}(${['core', ...addons].join(', ')})${C.reset}`);
line();

task('💾 Looking for a database...');
const db = connectDatabase();
line();
if (db) info(`MariaDB reachable${db.docker ? '' : ` ${C.dim}(via local fallback)${C.reset}`}`);
else warn('MariaDB not reachable (test:integration/migrate/seed will be skipped)');

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
item(`Install: ${C.dim}${path.join(DEST, 'simpl-test')}${C.reset}`);
item(`Docker:  ${C.dim}run \`docker compose up -d --build\` inside the install to browse it that way instead${C.reset}`);
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
