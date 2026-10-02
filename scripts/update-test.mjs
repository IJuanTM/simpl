#!/usr/bin/env node
import {spawnSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {buildZips, C, die, divider, heading, info, item, line, out, PAD, plural, row, SIMPL, SITE_URL, snapshotWorkingTree, stripAnsi, styled, success, task, TEST_NAME, TEST_PROJECT, titleBox} from './shared.mjs';

const FOLDERS = ['src/scss', 'src/ts', 'src/views'];

const help = () => {
  titleBox('Update test install');
  line();
  heading('Usage:');
  row('node scripts/update-test.mjs');
  line();
  heading('Options:');
  row('--help, -h', 'Show this help message');
  line();
  heading('Note:');
  item(`Replaces ${FOLDERS.join(', ')} in the fresh-install-test install with the working tree's, then rebuilds its assets.`);
  line();
};

for (const arg of process.argv.slice(2)) {
  if (arg === '-h' || arg === '--help') {
    help();
    process.exit(0);
  }
  die(`Unknown option: ${arg}`, 'Run with --help to see all available options.');
}

titleBox('Update test install');
line();

let project;
try {
  project = JSON.parse(fs.readFileSync(path.join(TEST_PROJECT, '.simpl'), 'utf8'));
} catch {
  die(`No test install found in ${TEST_PROJECT}`, 'Create one first: node scripts/fresh-install-test.mjs');
}
const addons = project.addons ?? [];

const run = (command, cwd) => {
  const result = spawnSync(command, {cwd, shell: true, encoding: 'utf8', input: '', maxBuffer: 64 * 1024 * 1024});
  if (result.status === 0) return;
  stripAnsi(`${result.stdout || ''}${result.stderr || ''}`).trim().split('\n').slice(-18).forEach(logLine => out(PAD + C.dim + '| ' + logLine + C.reset));
  die(`Failed: ${command}`);
};

const build = fs.mkdtempSync(path.join(os.tmpdir(), 'simpl-update-'));
process.on('exit', () => {
  try {
    fs.rmSync(build, {recursive: true, force: true});
  } catch {
  }
});

// A throwaway install goes through the same `simpl new` + `simpl add` merges as the real one, so add-on patches to core files (main.scss, main.ts, header.phtml...) land the same way.
task(`🧰 Merging the working tree ${C.dim}(${['core', ...addons].join(', ')})${C.reset}...`);
try {
  buildZips(snapshotWorkingTree(build, addons), path.join(build, 'releases', project.version), addons);
} catch (err) {
  die('Could not build the release zips', err.stderr?.trim() || err.message);
}
process.env.SIMPL_LOCAL_RELEASES = path.join(build, 'releases');
run(`${SIMPL} new --local --version=${project.version} --name="${TEST_NAME}" --url="${SITE_URL}"`, build);
const merged = path.join(build, path.basename(TEST_PROJECT));
for (const addon of addons) run(`${SIMPL} add ${addon} --local`, merged);

const walk = (dir) => fs.existsSync(dir) ? fs.readdirSync(dir, {recursive: true, withFileTypes: true}).filter(e => e.isFile()).map(e => path.relative(dir, path.join(e.parentPath, e.name))) : [];
const changes = {added: [], updated: [], removed: []};
for (const folder of FOLDERS) {
  const from = path.join(merged, folder), to = path.join(TEST_PROJECT, folder);
  const incoming = new Set(walk(from));
  for (const file of incoming) {
    const target = path.join(to, file);
    const exists = fs.existsSync(target);
    if (exists && fs.readFileSync(target).equals(fs.readFileSync(path.join(from, file)))) continue;
    fs.mkdirSync(path.dirname(target), {recursive: true});
    fs.copyFileSync(path.join(from, file), target);
    changes[exists ? 'updated' : 'added'].push(path.join(folder, file));
  }
  for (const file of walk(to).filter(file => !incoming.has(file))) {
    fs.rmSync(path.join(to, file));
    changes.removed.push(path.join(folder, file));
  }
}

const total = changes.added.length + changes.updated.length + changes.removed.length;
line();
if (!total) info('Already up to date');
else {
  success(`${plural(total, 'file')} changed`);
  for (const [kind, files] of Object.entries(changes)) for (const file of files) item(`${file.replaceAll('\\', '/')} ${C.dim}(${kind})${C.reset}`);
}

divider();
task(`🎨 Building assets ${C.dim}(npm run build)${C.reset}...`);
run('npm run build', TEST_PROJECT);
line();
success(styled('Test install updated!', C.bold, C.green), true);
line();
