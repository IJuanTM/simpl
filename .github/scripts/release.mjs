#!/usr/bin/env node
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {parseArgs} from 'node:util';

let args;
try {
  args = parseArgs({allowPositionals: true, options: {root: {type: 'string'}}});
} catch (err) {
  console.log(`::error::${err.message}`);
  process.exit(1);
}
const {values: options, positionals: [command, dirArg]} = args;

const REPO = options.root ? path.resolve(options.root) : path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const CDN_URL = 'https://cdn.simpl.iwanvanderwal.nl/framework';
const VERSION = /^(\d+)\.(\d+)\.(\d+)([ab]?)$/;
const RANK = {a: 0, b: 1, '': 2};

const usage = () => {
  console.log(`Usage:
  node .github/scripts/release.mjs check            Validate the version, and that every release date is today (UTC)
  node .github/scripts/release.mjs build <dir>      Build <dir>/<version>/core.zip and add-ons/<name>.zip from HEAD
  node .github/scripts/release.mjs versions <dir>   Write <dir>/versions.json with this version added to the CDN's list

  --root <dir>   Check or build the repository checkout in <dir> instead of the one this script is in`);
};

const read = (file) => fs.readFileSync(path.join(REPO, file), 'utf8');
const git = (...args) => execFileSync('git', ['-C', REPO, ...args], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']}).trim();
const fail = (problems) => {
  for (const problem of problems) console.log(`::error::${problem}`);
  process.exit(1);
};

const compareVersions = (a, b) => {
  const [x, y] = [a, b].map(v => VERSION.exec(v).slice(1));
  return x[0] - y[0] || x[1] - y[1] || x[2] - y[2] || RANK[x[3]] - RANK[y[3]];
};
const withoutSuffix = (v) => v.replace(/[ab]$/, '');
const isPreRelease = (v) => v !== withoutSuffix(v);

const version = JSON.parse(read('core/.simpl')).version;
const addons = () => fs.readdirSync(path.join(REPO, 'add-ons'), {withFileTypes: true}).filter(entry => entry.isDirectory()).map(entry => entry.name).sort();

// An empty file means a first release; invalid JSON stops the release, since the new versions.json is built from this one.
const fetchVersions = async () => {
  let res;
  try {
    res = await fetch(`${CDN_URL}/versions.json`, {signal: AbortSignal.timeout(15_000)});
  } catch (err) {
    fail([`Could not reach ${CDN_URL}/versions.json: ${err.cause?.code ?? err.message}`]);
  }
  if (res.status === 404) return {};
  if (!res.ok) fail([`Could not fetch ${CDN_URL}/versions.json: HTTP ${res.status}`]);
  const body = (await res.text()).trim();
  if (!body) return {};
  try {
    return JSON.parse(body).versions ?? {};
  } catch {
    fail([`${CDN_URL}/versions.json is not valid JSON, fix it on the server before releasing`]);
  }
};

const versionProblems = (versions) => {
  if (!VERSION.test(version)) return [`core/.simpl has version "${version}", use e.g. 2.1.0, 2.1.0a or 2.1.0b`];
  if (versions[version]) return [`${version} is already released, bump the version`];

  const latest = Object.keys(versions).filter(v => VERSION.test(v) && !isPreRelease(v)).sort(compareVersions).at(-1);
  if (!latest) return [];

  const [major, minor, patch] = latest.split('.').map(Number);
  const next = [`${major}.${minor}.${patch + 1}`, `${major}.${minor + 1}.0`, `${major + 1}.0.0`];
  if (!next.includes(withoutSuffix(version))) return [`${version} does not follow the latest release ${latest}, use ${next.join(', ')} (optionally with a or b appended)`];

  const later = Object.keys(versions).find(other => VERSION.test(other) && withoutSuffix(other) === withoutSuffix(version) && compareVersions(other, version) > 0);
  return later ? [`${later} is already released, so ${version} would go backwards`] : [];
};

const stampProblems = () => {
  const env = read('core/src/.env');
  const stamps = {
    'core/composer.json': JSON.parse(read('core/composer.json')).version,
    'core/package.json': JSON.parse(read('core/package.json')).version,
    'SIMPL_VERSION in core/src/.env': env.match(/^SIMPL_VERSION=(.*)$/m)?.[1].trim(),
  };
  return Object.entries(stamps).filter(([, v]) => v !== version).map(([where, v]) => `${where} has version ${v ?? '(missing)'}, but core/.simpl has ${version}`);
};

const dateProblems = () => {
  const releaseDate = read('core/src/.env').match(/^SIMPL_LAST_UPDATE=(.*)$/m)?.[1].trim();
  if (!releaseDate) return ['SIMPL_LAST_UPDATE is missing from core/src/.env'];

  const problems = [];
  const changelogDate = read('README.md').match(new RegExp(`^#### Version ${version.replaceAll('.', '\\.')} \\((.+)\\)$`, 'm'))?.[1];
  if (!changelogDate && !isPreRelease(version)) problems.push(`README.md has no "#### Version ${version} (<date>)" changelog entry`);
  if (changelogDate && changelogDate !== releaseDate) problems.push(`README.md dates ${version} ${changelogDate}, but SIMPL_LAST_UPDATE in core/src/.env is ${releaseDate}`);

  const sitemaps = ['core/src/public/sitemap.xml', ...addons().map(name => `add-ons/${name}/src/public/sitemap.xml`)].filter(file => fs.existsSync(path.join(REPO, file)));
  for (const file of sitemaps) {
    const stale = new Set([...read(file).matchAll(/<lastmod>(.*?)<\/lastmod>/g)].map(match => match[1]).filter(date => date !== releaseDate));
    if (stale.size) problems.push(`${file} has <lastmod> ${[...stale].join(', ')}, but SIMPL_LAST_UPDATE in core/src/.env is ${releaseDate}`);
  }

  const today = new Date().toISOString().slice(0, 10);
  if (releaseDate !== today) problems.push(`The release date is ${releaseDate}, not today (${today}), update SIMPL_LAST_UPDATE, the README.md changelog entry and the sitemap.xml files`);

  return problems;
};

const output = (name, value) => {
  if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `${name}=${value}\n`);
};

if (command === 'check') {
  const problems = [...versionProblems(await fetchVersions()), ...stampProblems(), ...dateProblems()];
  if (problems.length) fail(problems);
  console.log(`${version} is ready to release${isPreRelease(version) ? ' as a pre-release' : ''}`);
  output('version', version);
} else if (command === 'build' && dirArg) {
  const dir = path.resolve(dirArg, version);
  fs.mkdirSync(path.join(dir, 'add-ons'), {recursive: true});
  // autocrlf is forced off so the zips hold LF files regardless of the runner's git config.
  const archive = (subtree, file) => git('-c', 'core.autocrlf=false', 'archive', '--format=zip', '-o', path.join(dir, file), `HEAD:${subtree}`);
  archive('core', 'core.zip');
  for (const name of addons()) archive(`add-ons/${name}`, `add-ons/${name}.zip`);
  console.log(`Built ${version}: core.zip, ${addons().map(name => `add-ons/${name}.zip`).join(', ')}`);
  output('version', version);
} else if (command === 'versions' && dirArg) {
  const versions = await fetchVersions();
  const problems = versionProblems(versions);
  if (problems.length) fail(problems);

  // is-latest stays on the newest stable release, the version `simpl new` defaults to.
  const withoutLatest = ({'is-latest': _, ...meta} = {}) => meta;
  const preRelease = isPreRelease(version);
  const entry = {'add-ons': addons(), 'is-pre-release': preRelease || undefined, 'is-latest': !preRelease || undefined};
  const others = Object.entries(versions).map(([v, meta]) => [v, preRelease ? meta : withoutLatest(meta)]);
  const updated = {versions: Object.fromEntries([[version, entry], ...others].sort(([a], [b]) => compareVersions(b, a)))};

  fs.mkdirSync(dirArg, {recursive: true});
  fs.writeFileSync(path.join(dirArg, 'versions.json'), JSON.stringify(updated, null, 2) + '\n');
  console.log(`Wrote versions.json with ${Object.keys(updated.versions).join(', ')}`);
} else {
  usage();
  process.exit(command ? 1 : 0);
}
