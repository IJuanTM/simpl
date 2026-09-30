#!/usr/bin/env node
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {ask, box, buildZips, C, confirm, die, divider, error, git, heading, info, interactive, item, line, listZips, out, PAD, plural, printAnswer, RELEASES_DIR, row, styled, success, task, titleBox, warn} from './shared.mjs';

const SERVER = 'simpl-cdn';
const CDN_DIR = '/var/www/cdn.simpl.iwanvanderwal.nl/framework';
const CDN_URL = 'https://cdn.simpl.iwanvanderwal.nl/framework';
const VERSION = /^(\d+)\.(\d+)\.(\d+)([ab]?)$/;
const PRE_RELEASES = {a: 'alpha', b: 'beta'};
const RANK = {a: 0, b: 1, '': 2};

const help = () => {
  titleBox('Release');
  line();
  heading('Usage:');
  row('node scripts/release.mjs [version] [options]');
  line();
  heading('Options:');
  row('--local', 'Release to the local releases folder instead of the CDN');
  row('--help, -h', 'Show this help message');
  line();
  heading('Environment:');
  row('SIMPL_DEV_DIR', 'Local releases go in its releases/ (default: ~/Desktop/simpl-dev)');
  line();
  heading('Examples:');
  row('node scripts/release.mjs');
  row('node scripts/release.mjs 2.1.0');
  row('node scripts/release.mjs 2.1.0b');
  row('node scripts/release.mjs 2.1.0 --local');
  line();
  heading('Note:');
  item('The v<version> tag must exist first, see scripts/README.md for everything that is checked.');
  line();
};

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

const args = process.argv.slice(2);
if (args.includes('--help') || args.includes('-h')) {
  help();
  process.exit(0);
}
const local = args.includes('--local');
const unknownOption = args.find(arg => arg.startsWith('-') && arg !== '--local');
if (unknownOption) die(`Unknown option: ${unknownOption}`, 'Run with --help to see all available options.');
const target = local ? RELEASES_DIR : 'the CDN';

// The CLI's --local mode lists the version folders instead of reading a versions.json, so this does the same.
const readLocalVersions = () => fs.existsSync(RELEASES_DIR)
  ? Object.fromEntries(fs.readdirSync(RELEASES_DIR, {withFileTypes: true}).filter(e => e.isDirectory() && VERSION.test(e.name)).map(e => [e.name, {}]))
  : {};

const readCdnVersions = () => {
  let current;
  try {
    current = ssh(`cat ${CDN_DIR}/versions.json`);
  } catch {
    die(`Could not read versions.json from the "${SERVER}" SSH host`, 'If the host is unknown, add it to ~/.ssh/config, see scripts/README.md.');
  }
  try {
    return JSON.parse(current).versions;
  } catch (err) {
    die(`versions.json on ${SERVER} is not valid JSON`, err.message);
  }
};

titleBox('Release', local ? 'local' : undefined);
line();
task(local ? `💻 Reading local releases from ${RELEASES_DIR}...` : `📦 Fetching versions from ${SERVER}...`);
const versions = local ? readLocalVersions() : readCdnVersions();
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
  const [preset] = args.filter(arg => !arg.startsWith('-'));
  if (preset) {
    const problem = versionProblem(preset);
    if (problem) die(problem);
    line();
    printAnswer('Version', describe(preset));
    return preset;
  }
  if (!interactive) die('No version given', 'Usage: node scripts/release.mjs [version], e.g. node scripts/release.mjs 2.1.0');

  line();
  info(`Latest release in ${target}: ${latest ?? 'none'}`);
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
if (!changelogDate && !preRelease) die(`${tag} has no "#### Version ${version} (<date>)" entry in README.md`);
if (changelogDate && releaseDate !== changelogDate) die(`${tag} has a different release date in core/src/.env SIMPL_LAST_UPDATE (${releaseDate}) than in README.md (${changelogDate})`);

box(`Releasing: ${C.cyan}${version}${C.reset} ${C.dim}(${preRelease ? PRE_RELEASES[version.at(-1)] + ', ' : ''}${tag} ${git('rev-parse', '--short', `${tag}^{commit}`)})${C.reset}`);
line();
task('🧰 Building release zips...');

const build = fs.mkdtempSync(path.join(os.tmpdir(), `simpl-release-${version}-`));
let addons;
try {
  addons = git('ls-tree', '-d', '--name-only', `${tag}:add-ons`).split('\n').filter(Boolean);
  buildZips(tag, path.join(build, version), addons);
} catch (err) {
  die('Could not build the release zips', err.stderr?.trim() || err.message);
}

if (!local) {
  // A pre-release never becomes latest, so `simpl new` keeps defaulting to the newest stable release.
  const withoutLatest = ({'is-latest': _, 'script-compatible': __, ...meta} = {}) => meta;
  const entry = {...withoutLatest(versions[version]), 'add-ons': addons, 'is-pre-release': preRelease || undefined, 'is-latest': !preRelease || undefined};
  const others = Object.entries(versions).filter(([v]) => v !== version).map(([v, meta]) => [v, preRelease ? meta : withoutLatest(meta)]);
  const updated = {versions: Object.fromEntries([[version, entry], ...others].sort(([a], [b]) => compareVersions(b, a)))};
  fs.writeFileSync(path.join(build, 'versions.json'), JSON.stringify(updated, null, 2) + '\n');
}

line();
success(`Built ${plural(1 + addons.length, 'zip')}`);
listZips(path.join(build, version), addons);
if (!local) item('versions.json');

// sv-SE formats as YYYY-MM-DD in local time, unlike toISOString() which is UTC.
const today = new Date().toLocaleDateString('sv-SE');
const warnings = [];
if (!changelogDate) warnings.push(`${tag} has no "#### Version ${version} (<date>)" entry in README.md`);
if (releaseDate !== today) warnings.push(`The release date is ${releaseDate}, not today (${today})`);
try {
  if (!local && !git('ls-remote', '--tags', 'origin', `refs/tags/${tag}`)) warnings.push(`${tag} is not pushed to GitHub yet: git push origin ${tag}`);
} catch {
  warnings.push(`Could not check whether ${tag} is on GitHub (no origin remote or offline)`);
}
if (versions[version]) warnings.push(`${version} is already in ${target} and will be replaced`);
if (warnings.length) line();
warnings.forEach(warn);

if (local) {
  divider();
  task(`💻 Copying to ${RELEASES_DIR}...`);
  const dest = path.join(RELEASES_DIR, version);
  try {
    fs.rmSync(dest, {recursive: true, force: true});
    fs.cpSync(path.join(build, version), dest, {recursive: true});
  } catch (err) {
    die(`Copy to ${dest} failed`, err.message, `The files are still in ${build}.`);
  }
  fs.rmSync(build, {recursive: true});

  line();
  success(`Copied to ${C.cyan}${dest}${C.reset}`);
  info(`Test it with: node scripts/fresh-install-test.mjs --release=${version}`);
  line();
  success(styled(`Released ${version} locally!`, C.bold, C.green), true);
  line();
  process.exit(0);
}

if (!await confirm(`Upload to ${SERVER}?`)) {
  line();
  info(`Nothing uploaded. The files are in ${build}`);
  line();
  process.exit(0);
}

divider();
task(`🚀 Uploading to ${SERVER}...`);
try {
  // The version folder is uploaded beside the live one and swapped in, so a re-release never serves half-uploaded or stale zips.
  ssh(`rm -rf ${CDN_DIR}/${version}.new`);
  scp(build, version, `${CDN_DIR}/${version}.new`);
  ssh(`cd ${CDN_DIR} && rm -rf ${version}.old && { [ ! -e ${version} ] || mv ${version} ${version}.old; } && mv ${version}.new ${version} && rm -rf ${version}.old`);

  // versions.json goes last, so the CDN never lists a version whose zips aren't there yet.
  scp(build, 'versions.json', `${CDN_DIR}/versions.json.tmp`);
  ssh(`mv ${CDN_DIR}/versions.json.tmp ${CDN_DIR}/versions.json`);
} catch {
  die(`Upload to ${SERVER} failed`, `The files are still in ${build}.`, 'Run the release again to retry.');
}
fs.rmSync(build, {recursive: true});

line();
success(`Uploaded to ${C.cyan}${CDN_URL}/${version}/${C.reset}`);
line();
success(styled(`Released ${version}!`, C.bold, C.green), true);
line();
