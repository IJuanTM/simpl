#!/usr/bin/env node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {createInterface} from 'node:readline/promises';
import {fileURLToPath} from 'node:url';

const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SERVER = 'simpl-cdn';
const CDN_DIR = '/var/www/cdn.simpl.iwanvanderwal.nl/framework';
const CDN_URL = 'https://cdn.simpl.iwanvanderwal.nl/framework';
const VERSION = /^(\d+)\.(\d+)\.(\d+)([ab]?)$/;
const PRE_RELEASES = {a: 'alpha', b: 'beta'};
const RANK = {a: 0, b: 1, '': 2};

// Output helpers, matching the Simpl CLI's style.
const C = {
  reset: '\x1b[0m', green: '\x1b[32m', yellow: '\x1b[33m', red: '\x1b[31m',
  cyan: '\x1b[36m', blue: '\x1b[34m', bold: '\x1b[1m', dim: '\x1b[2m',
};
const PAD = '  ';
const BOX_W = 62;
const interactive = Boolean(process.stdin.isTTY);
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
const plural = (count, word) => `${styled(String(count), C.bold)} ${word}${count !== 1 ? 's' : ''}`;
const box = (title) => {
  const parts = title.split(/(\x1b\[[0-9;]*m)/);
  const length = parts.reduce((sum, part, i) => i % 2 ? sum : sum + part.length, 0);
  let shown = title, remaining = BOX_W - 5;
  if (length > BOX_W - 2) shown = parts.map((part, i) => {
    if (i % 2) return part;
    const kept = part.slice(0, remaining);
    remaining -= kept.length;
    return kept;
  }).join('') + '...';
  line();
  out(PAD + '╭' + '─'.repeat(BOX_W) + '╮');
  out(PAD + '│ ' + styled(shown, C.bold) + ' '.repeat(Math.max(0, BOX_W - 2 - length)) + ' │');
  out(PAD + '╰' + '─'.repeat(BOX_W) + '╯');
};
const titleBox = (title, detail) => box(`Simpl ${C.dim}-${C.reset} ${C.blue}${title}${C.reset}` + (detail ? ` ${C.dim}(${detail})${C.reset}` : ''));
const printAnswer = (question, value) => out(`${PAD}${question}: ${C.cyan}${value}${C.reset}`);
const die = (msg, ...hints) => {
  line();
  error(msg);
  hints.forEach(info);
  line();
  process.exit(1);
};

// Without a TTY there is nobody to answer, so the default is taken instead of waiting on stdin.
const ask = async (question, defaultValue = '', hint = defaultValue) => {
  if (!interactive) return defaultValue;
  const rl = createInterface({input: process.stdin, output: process.stdout});
  try {
    return (await rl.question(`${PAD}${question}${hint ? ` ${C.dim}(${hint})${C.reset}` : ''}: `)).trim() || defaultValue;
  } catch (err) {
    if (err.name !== 'AbortError') throw err;
    line();
    info('Cancelled');
    line();
    process.exit(130);
  } finally {
    rl.close();
  }
};
const confirm = async (question) => {
  line();
  while (true) {
    const answer = (await ask(question, 'no', 'y/N')).toLowerCase();
    if (['y', 'yes'].includes(answer)) return true;
    if (['n', 'no'].includes(answer)) return false;
    warn('Please answer [Y] Yes or [N] No');
    line();
  }
};

const git = (...args) => execFileSync('git', ['-C', REPO, ...args], {encoding: 'utf8'}).trim();
const ssh = (command) => execFileSync('ssh', ['-o', 'LogLevel=ERROR', SERVER, command], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit']});
// Run from the build folder with relative paths, since scp reads a Windows drive letter like "C:" as a host name.
const scp = (cwd, source, dest) => execFileSync('scp', ['-q', '-r', '-o', 'LogLevel=ERROR', source, `${SERVER}:${dest}`], {cwd, stdio: 'inherit'});

// A pre-release sorts before the release it leads up to: 2.1.0a < 2.1.0b < 2.1.0.
const compareVersions = (a, b) => {
  const [x, y] = [a, b].map(v => VERSION.exec(v).slice(1));
  return x[0] - y[0] || x[1] - y[1] || x[2] - y[2] || RANK[x[3]] - RANK[y[3]];
};
const withoutSuffix = (v) => v.replace(/[ab]$/, '');
const describe = (v) => PRE_RELEASES[v.at(-1)] ? `${v} ${C.dim}(${PRE_RELEASES[v.at(-1)]})${C.reset}` : v;

titleBox('Release');
line();
task(`📦 Fetching versions from ${SERVER}...`);

let current;
try {
  current = ssh(`cat ${CDN_DIR}/versions.json`);
} catch {
  die(`Could not read versions.json from the "${SERVER}" SSH host`, 'If the host is unknown, add it to ~/.ssh/config, see scripts/README.md.');
}
const {versions} = JSON.parse(current);
const latest = Object.keys(versions).filter(v => VERSION.test(v) && withoutSuffix(v) === v).sort(compareVersions).at(-1);
const next = latest ? (([major, minor, patch]) => [`${major}.${minor}.${patch + 1}`, `${major}.${minor + 1}.0`, `${major + 1}.0.0`])(latest.split('.').map(Number)) : [];

const versionProblem = (v) => {
  if (!VERSION.test(v)) return `${v} is not a valid version, use e.g. 2.1.0, 2.1.0a or 2.1.0b`;
  if (!latest || v === latest) return null;
  if (!next.includes(withoutSuffix(v))) return `${v} does not follow ${latest}. Release ${next.join(', ')} (optionally with a or b appended), or ${latest} again to replace it.`;
  const later = Object.keys(versions).find(other => withoutSuffix(other) === withoutSuffix(v) && compareVersions(other, v) > 0);
  return later ? `${later} is already released, so ${v} would go backwards` : null;
};

const resolveVersion = async () => {
  const preset = process.argv[2];
  if (preset) {
    const problem = versionProblem(preset);
    if (problem) die(problem);
    line();
    printAnswer('Version', describe(preset));
    return preset;
  }
  if (!interactive) die('No version given', 'Usage: node scripts/release.mjs [version], e.g. node scripts/release.mjs 2.1.0');

  line();
  info(`Latest release on the CDN: ${latest ?? 'none'}`);
  line();
  heading('Next version:');
  ['patch', 'minor', 'major'].forEach((kind, i) => next[i] && out(`${PAD}${C.cyan}${i + 1}.${C.reset} ${next[i]} ${C.dim}(${kind})${C.reset}`));
  info('Append a or b for an alpha or beta, e.g. 2.1.0b or 2b');
  line();
  while (true) {
    const input = await ask(`Version to release ${C.dim}(number or version)${C.reset}`);
    if (!input) {
      warn('Selection cannot be empty');
      line();
      continue;
    }
    const [, pick, suffix] = /^([1-3])([ab]?)$/.exec(input) ?? [];
    const v = pick && next[pick - 1] ? next[pick - 1] + suffix : input;
    const problem = versionProblem(v);
    if (!problem) {
      if (v !== input) printAnswer('Version', describe(v));
      return v;
    }
    error(problem);
    line();
  }
};

const version = await resolveVersion();
const preRelease = version !== withoutSuffix(version);
const tag = `v${version}`;
try {
  git('rev-parse', '--verify', '--quiet', `${tag}^{commit}`);
} catch {
  die(`Tag ${tag} not found`, `Create it first: git tag ${tag}`);
}

// Installed projects read their version from these files, so a forgotten bump would break `simpl add` for them.
const show = (file) => git('show', `${tag}:${file}`);
const env = show('core/src/.env');
const stamped = {
  'core/.simpl': JSON.parse(show('core/.simpl')).version,
  'core/composer.json': JSON.parse(show('core/composer.json')).version,
  'core/package.json': JSON.parse(show('core/package.json')).version,
  'core/src/.env': env.match(/^SIMPL_VERSION=(.*)$/m)?.[1].trim(),
};
const mismatched = Object.entries(stamped).filter(([, v]) => v !== version);
if (mismatched.length) die(`${tag} has the wrong version in: ${mismatched.map(([file, v]) => `${file} (${v})`).join(', ')}`);

const releaseDate = env.match(/^SIMPL_LAST_UPDATE=(.*)$/m)?.[1].trim();
const changelogDate = show('README.md').match(new RegExp(`^#### Version ${version.replaceAll('.', '\\.')} \\((.+)\\)$`, 'm'))?.[1];
if (!changelogDate) die(`${tag} has no "#### Version ${version} (<date>)" entry in README.md`);
if (releaseDate !== changelogDate) die(`${tag} has a different release date in core/src/.env SIMPL_LAST_UPDATE (${releaseDate}) than in README.md (${changelogDate})`);

box(`Releasing: ${C.cyan}${version}${C.reset} ${C.dim}(${preRelease ? PRE_RELEASES[version.at(-1)] + ', ' : ''}${tag} ${git('rev-parse', '--short', `${tag}^{commit}`)})${C.reset}`);
line();
task('🧰 Building release zips...');

const addons = git('ls-tree', '-d', '--name-only', `${tag}:add-ons`).split('\n').filter(Boolean);
const build = fs.mkdtempSync(path.join(os.tmpdir(), `simpl-release-${version}-`));
fs.mkdirSync(path.join(build, version, 'add-ons'), {recursive: true});

// autocrlf is forced off so the zips hold LF files regardless of the local git config.
const archive = (tree, file) => git('-c', 'core.autocrlf=false', 'archive', '--format=zip', '-o', path.join(build, version, file), `${tag}:${tree}`);
archive('core', 'core.zip');
for (const name of addons) archive(`add-ons/${name}`, `add-ons/${name}.zip`);

// A pre-release never becomes latest, so `simpl new` keeps defaulting to the newest stable release.
const withoutLatest = ({'is-latest': _, 'script-compatible': __, ...meta} = {}) => meta;
const entry = {...withoutLatest(versions[version]), 'add-ons': addons, 'is-pre-release': preRelease || undefined, 'is-latest': !preRelease || undefined};
const others = Object.entries(versions).filter(([v]) => v !== version).map(([v, meta]) => [v, preRelease ? meta : withoutLatest(meta)]);
const updated = {versions: Object.fromEntries([[version, entry], ...others].sort(([a], [b]) => compareVersions(b, a)))};
fs.writeFileSync(path.join(build, 'versions.json'), JSON.stringify(updated, null, 2) + '\n');

const kb = (file) => `${Math.ceil(fs.statSync(path.join(build, version, file)).size / 1024)} KB`;
line();
success(`Built ${plural(1 + addons.length, 'zip')}`);
item(`core.zip ${C.dim}(${kb('core.zip')})${C.reset}`);
for (const name of addons) item(`add-ons/${name}.zip ${C.dim}(${kb(`add-ons/${name}.zip`)})${C.reset}`);
item('versions.json');

// sv-SE formats as YYYY-MM-DD in local time, unlike toISOString() which is UTC.
const today = new Date().toLocaleDateString('sv-SE');
const warnings = [];
if (releaseDate !== today) warnings.push(`The release date is ${releaseDate}, not today (${today})`);
try {
  if (!git('ls-remote', '--tags', 'origin', `refs/tags/${tag}`)) warnings.push(`${tag} is not pushed to GitHub yet: git push origin ${tag}`);
} catch {
  warnings.push(`Could not check whether ${tag} is on GitHub (no origin remote or offline)`);
}
if (versions[version]) warnings.push(`${version} is already on the CDN and will be replaced`);
if (warnings.length) line();
warnings.forEach(warn);

if (!await confirm(`Upload to ${SERVER}?`)) {
  line();
  info(`Nothing uploaded. The files are in ${build}`);
  line();
  process.exit(0);
}

divider();
task(`🚀 Uploading to ${SERVER}...`);
// The version folder is uploaded beside the live one and swapped in, so a re-release never serves half-uploaded or stale zips.
ssh(`rm -rf ${CDN_DIR}/${version}.new`);
scp(build, version, `${CDN_DIR}/${version}.new`);
ssh(`cd ${CDN_DIR} && rm -rf ${version}.old && { [ ! -e ${version} ] || mv ${version} ${version}.old; } && mv ${version}.new ${version} && rm -rf ${version}.old`);
// versions.json goes last, so the CDN never lists a version whose zips aren't there yet.
scp(build, 'versions.json', `${CDN_DIR}/versions.json.tmp`);
ssh(`mv ${CDN_DIR}/versions.json.tmp ${CDN_DIR}/versions.json`);
fs.rmSync(build, {recursive: true});

line();
success(`Uploaded to ${C.cyan}${CDN_URL}/${version}/${C.reset}`);
line();
success(styled(`Released ${version}!`, C.bold, C.green), true);
line();
