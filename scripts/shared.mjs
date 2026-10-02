import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

export const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export const DEV_DIR = process.env.SIMPL_DEV_DIR || path.join(os.homedir(), 'Desktop', 'simpl-dev');
export const TEST_PROJECT = path.join(DEV_DIR, 'simpl-test');
export const TEST_NAME = 'Simpl Test';
export const DOMAIN = process.env.SIMPL_TEST_DOMAIN || 'simpl.test';
export const SITE_URL = `https://${DOMAIN}/`;
export const SIMPL = 'npx --yes @ijuantm/simpl';

// Output helpers, copied from the Simpl CLI's lib/ui.js.
const CODES = {
  reset: '\x1b[0m', green: '\x1b[32m', yellow: '\x1b[33m', red: '\x1b[31m',
  cyan: '\x1b[36m', blue: '\x1b[34m', gray: '\x1b[90m', bold: '\x1b[1m', dim: '\x1b[2m',
};
export const C = Object.fromEntries(Object.entries(CODES).map(([name, code]) => [name, process.stdout.hasColors?.() ? code : '']));
const BOX_WIDTH = 62;
export const PAD = '  ';
export const styled = (msg, ...styles) => styles.join('') + msg + C.reset;
export const line = (msg = '') => console.log(msg);
export const out = (msg, color = C.reset) => console.log(color + msg + C.reset);
const prefixed = (symbol, color, msg, bold = false, dim = false) => out(PAD + color + symbol + C.reset + ' ' + (bold ? styled(msg, C.bold) : dim ? styled(msg, C.dim) : msg));
export const success = (msg, bold = false) => prefixed('✓', C.green, msg, bold);
export const error = (msg, bold = false) => prefixed('✕', C.red, msg, bold);
export const warn = (msg, bold = false) => prefixed('⚠', C.yellow, msg, bold);
export const info = (msg) => prefixed('◌', C.cyan, msg, false, true);
export const task = (msg) => out(PAD + msg);
export const item = (msg, dim = false) => out(PAD + C.cyan + '•' + C.reset + ' ' + (dim ? styled(msg, C.dim) : msg));
export const heading = (msg) => out(PAD + styled(msg, C.bold), C.blue);
export const plural = (count, word) => `${styled(String(count), C.bold)} ${word}${count !== 1 ? 's' : ''}`;
export const row = (left, right = '') => out(PAD + styled(right ? left.padEnd(30) : left, C.dim) + right);
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
export const installBox = (name, version) => box(`Installing: ${C.cyan}${name}${C.reset} ${C.dim}(v${version})${C.reset}`);
export const titleBox = (title, detail) => box(`Simpl ${C.dim}-${C.reset} ${C.blue}${title}${C.reset}` + (detail ? ` ${C.dim}(${detail})${C.reset}` : ''));
export const divider = () => {
  line();
  out(PAD + '─'.repeat(16), C.dim);
  line();
};
export const die = (msg, ...hints) => {
  line();
  error(msg);
  hints.forEach(info);
  line();
  process.exit(1);
};
export const stripAnsi = (text) => text.replace(/\x1b\[[0-9;]*m/g, '');

export const git = (...args) => execFileSync('git', ['-C', REPO, ...args], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']}).trim();

// autocrlf is forced off so the zips hold LF files regardless of the local git config.
export const buildZips = (tree, dir, addons) => {
  fs.mkdirSync(path.join(dir, 'add-ons'), {recursive: true});
  const archive = (subtree, file) => git('-c', 'core.autocrlf=false', 'archive', '--format=zip', '-o', path.join(dir, file), `${tree}:${subtree}`);
  archive('core', 'core.zip');
  for (const name of addons) archive(`add-ons/${name}`, `add-ons/${name}.zip`);
};

export const listZips = (dir, addons) => {
  const files = ['core.zip', ...addons.map(name => `add-ons/${name}.zip`)];
  for (const file of files) item(`${file} ${C.dim}(${Math.ceil(fs.statSync(path.join(dir, file)).size / 1024)} KB)${C.reset}`);
};

export const snapshotWorkingTree = (dir, addons) => {
  const index = path.join(dir, 'index');
  // Seeded from the real index, since `git add -A` into an empty one would drop tracked-but-gitignored files like src/.env and .gitkeep.
  fs.copyFileSync(path.resolve(REPO, git('rev-parse', '--git-path', 'index')), index);
  const indexed = (...args) => execFileSync('git', ['-C', REPO, ...args], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], env: {...process.env, GIT_INDEX_FILE: index}}).trim();
  indexed('add', '-A', '--', 'core', ...addons.map(addon => `add-ons/${addon}`));
  return indexed('write-tree');
};
