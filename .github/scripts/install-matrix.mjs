#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
// GitHub refuses a matrix with more jobs than this.
const MAX_JOBS = 256;

const dir = path.join(REPO, 'add-ons');
const addons = fs.readdirSync(dir, {withFileTypes: true}).filter(entry => entry.isDirectory()).map(entry => entry.name).sort();
const dependencies = Object.fromEntries(addons.map(name => {
  const file = path.join(dir, name, 'addon.json');
  return [name, fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')).dependencies ?? [] : []];
}));

const missing = addons.flatMap(name => dependencies[name].filter(dep => !addons.includes(dep)).map(dep => `${name} depends on ${dep}, which isn't in add-ons/`));
if (missing.length) {
  for (const problem of missing) console.log(`::error::${problem}`);
  process.exit(1);
}

const isInstallable = (set) => set.every(name => dependencies[name].every(dep => set.includes(dep)));
const subsets = addons.reduce((sets, name) => [...sets, ...sets.map(set => [...set, name])], [[]]).filter(isInstallable);

// Adds only the top add-ons of a set, so simpl add's dependency install gets tested too.
const entry = (set) => ({
  name: set.length ? `core + ${set.join(' + ')}` : 'core only',
  add: set.filter(name => !set.some(other => dependencies[other].includes(name))).join(' '),
  db: set.includes('db'),
});

const full = entry(addons);
const partial = subsets.filter(set => set.length < addons.length).map(entry);
if (partial.length > MAX_JOBS) {
  console.log(`::error::${partial.length} add-on combinations is more than the ${MAX_JOBS} jobs GitHub allows in one matrix`);
  process.exit(1);
}

// The versions a project ships with: PHP from core's composer.json, MariaDB from the db add-on's compose.yaml default.
const php = JSON.parse(fs.readFileSync(path.join(REPO, 'core', 'composer.json'), 'utf8')).require.php.match(/\d+\.\d+/)[0];
const dbCompose = path.join(dir, 'db', 'compose.yaml');
const mariadb = fs.existsSync(dbCompose) ? fs.readFileSync(dbCompose, 'utf8').match(/image:\s*(mariadb):\$\{MARIADB_VERSION:-([^}]+)}/)?.slice(1).join(':') ?? '' : '';
if (addons.includes('db') && !mariadb) {
  console.log('::error::add-ons/db/compose.yaml has no "image: mariadb:${MARIADB_VERSION:-<version>}" line to take the MariaDB version from');
  process.exit(1);
}

const output = {full: JSON.stringify(full), partial: JSON.stringify(partial), php, mariadb};
if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, Object.entries(output).map(([key, value]) => `${key}=${value}\n`).join(''));
console.log(`PHP ${php}, ${mariadb || 'no MariaDB'}`);
console.log(`Full install: ${full.name}`);
console.log(`Linux combinations (${partial.length}): ${partial.map(combination => combination.name).join(', ')}`);
