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

const fail = (msg) => {
  console.error(`  ✕ ${msg}`);
  process.exit(1);
};

const git = (...args) => execFileSync('git', ['-C', REPO, ...args], {encoding: 'utf8'}).trim();
const ssh = (command) => execFileSync('ssh', ['-o', 'LogLevel=ERROR', SERVER, command], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit']});
// Run from the build folder with relative paths, since scp reads a Windows drive letter like "C:" as a host name.
const scp = (cwd, source, dest) => execFileSync('scp', ['-q', '-r', '-o', 'LogLevel=ERROR', source, `${SERVER}:${dest}`], {cwd, stdio: 'inherit'});

const compareVersions = (a, b) => {
  const [x, y] = [a, b].map(v => v.split('.').map(Number));
  return x[0] - y[0] || x[1] - y[1] || x[2] - y[2];
};

const version = process.argv[2];
if (!/^\d+\.\d+\.\d+$/.test(version ?? '')) fail('Usage: node scripts/release.mjs <version>, e.g. node scripts/release.mjs 2.0.0');

let current;
try {
  current = ssh(`cat ${CDN_DIR}/versions.json`);
} catch {
  fail(`Could not read versions.json from the "${SERVER}" SSH host. If the host is unknown, add it to ~/.ssh/config, see scripts/README.md.`);
}
const {versions} = JSON.parse(current);
const latest = Object.keys(versions).sort(compareVersions).at(-1);
if (latest) {
  const [major, minor, patch] = latest.split('.').map(Number);
  const next = [`${major}.${minor}.${patch + 1}`, `${major}.${minor + 1}.0`, `${major + 1}.0.0`];
  if (version !== latest && !next.includes(version)) fail(`${version} does not follow ${latest}. Release ${next.join(', ')}, or ${latest} again to replace it.`);
}

const tag = `v${version}`;
try {
  git('rev-parse', '--verify', '--quiet', `${tag}^{commit}`);
} catch {
  fail(`Tag ${tag} not found. Create it first: git tag ${tag}`);
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
if (mismatched.length) fail(`${tag} has the wrong version in: ${mismatched.map(([file, v]) => `${file} (${v})`).join(', ')}`);

const releaseDate = env.match(/^SIMPL_LAST_UPDATE=(.*)$/m)?.[1].trim();
const changelogDate = show('README.md').match(new RegExp(`^#### Version ${version.replaceAll('.', '\\.')} \\((.+)\\)$`, 'm'))?.[1];
if (!changelogDate) fail(`${tag} has no "#### Version ${version} (<date>)" entry in README.md`);
if (releaseDate !== changelogDate) fail(`${tag} has a different release date in core/src/.env SIMPL_LAST_UPDATE (${releaseDate}) than in README.md (${changelogDate})`);

const addons = git('ls-tree', '-d', '--name-only', `${tag}:add-ons`).split('\n').filter(Boolean);
const out = fs.mkdtempSync(path.join(os.tmpdir(), `simpl-release-${version}-`));
fs.mkdirSync(path.join(out, version, 'add-ons'), {recursive: true});

// autocrlf is forced off so the zips hold LF files regardless of the local git config.
const archive = (tree, file) => git('-c', 'core.autocrlf=false', 'archive', '--format=zip', '-o', path.join(out, version, file), `${tag}:${tree}`);
archive('core', 'core.zip');
for (const name of addons) archive(`add-ons/${name}`, `add-ons/${name}.zip`);

const withoutFlags = ({'is-latest': _, 'script-compatible': __, ...meta} = {}) => meta;
const older = Object.entries(versions).filter(([v]) => v !== version).map(([v, meta]) => [v, withoutFlags(meta)]);
const updated = {versions: {[version]: {...withoutFlags(versions[version]), 'add-ons': addons, 'is-latest': true}, ...Object.fromEntries(older)}};
fs.writeFileSync(path.join(out, 'versions.json'), JSON.stringify(updated, null, 2) + '\n');

const kb = (file) => `${Math.ceil(fs.statSync(path.join(out, version, file)).size / 1024)} KB`;
console.log();
console.log(`  Release ${version} from ${tag} (${git('rev-parse', '--short', `${tag}^{commit}`)})`);
console.log(`  • core.zip (${kb('core.zip')})`);
for (const name of addons) console.log(`  • add-ons/${name}.zip (${kb(`add-ons/${name}.zip`)})`);
console.log('  • versions.json');
// sv-SE formats as YYYY-MM-DD in local time, unlike toISOString() which is UTC.
const today = new Date().toLocaleDateString('sv-SE');
if (releaseDate !== today) console.log(`\n  ⚠ The release date is ${releaseDate}, not today (${today})`);
try {
  if (!git('ls-remote', '--tags', 'origin', `refs/tags/${tag}`)) console.log(`\n  ⚠ ${tag} is not pushed to GitHub yet: git push origin ${tag}`);
} catch {
  console.log(`\n  ⚠ Could not check whether ${tag} is on GitHub (no origin remote or offline)`);
}
console.log();

const rl = createInterface({input: process.stdin, output: process.stdout});
const answer = await rl.question(`  ${version === latest ? `${version} is already on the CDN and will be replaced. ` : ''}Upload to ${SERVER}? (y/N) `);
rl.close();
if (!/^y(es)?$/i.test(answer.trim())) {
  console.log(`\n  Nothing uploaded. The files are in ${out}\n`);
  process.exit(0);
}

// The version folder is uploaded beside the live one and swapped in, so a re-release never serves half-uploaded or stale zips.
ssh(`rm -rf ${CDN_DIR}/${version}.new`);
scp(out, version, `${CDN_DIR}/${version}.new`);
ssh(`cd ${CDN_DIR} && rm -rf ${version}.old && { [ ! -e ${version} ] || mv ${version} ${version}.old; } && mv ${version}.new ${version} && rm -rf ${version}.old`);
// versions.json goes last, so the CDN never lists a version whose zips aren't there yet.
scp(out, 'versions.json', `${CDN_DIR}/versions.json.tmp`);
ssh(`mv ${CDN_DIR}/versions.json.tmp ${CDN_DIR}/versions.json`);
fs.rmSync(out, {recursive: true});

console.log(`\n  ✓ Released ${version}: https://cdn.simpl.iwanvanderwal.nl/framework/${version}/core.zip\n`);
