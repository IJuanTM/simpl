#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const COLORS = {
  reset: '\x1b[0m', green: '\x1b[32m', yellow: '\x1b[33m', red: '\x1b[31m', cyan: '\x1b[36m', blue: '\x1b[34m', gray: '\x1b[90m', bold: '\x1b[1m', dim: '\x1b[2m'
};

const log = (message, color = 'reset') => console.log(`${COLORS[color]}${message}${COLORS.reset}`);

function extractMarkers(content) {
  const markers = [];

  content.split('\n').forEach((line, i) => {
    const afterMatch = line.match(/@addon-insert:after\s*\(\s*["'](.+?)["']\s*\)/);
    const beforeMatch = line.match(/@addon-insert:before\s*\(\s*["'](.+?)["']\s*\)/);

    if (afterMatch) markers.push({type: 'after', lineIndex: i, searchText: afterMatch[1]}); else if (beforeMatch) markers.push({type: 'before', lineIndex: i, searchText: beforeMatch[1]}); else if (line.includes('@addon-insert:prepend')) markers.push({type: 'prepend', lineIndex: i}); else if (line.includes('@addon-insert:append')) markers.push({type: 'append', lineIndex: i});
  });

  return markers;
}

function copyDirectory(src, dest, baseDir) {
  const copied = [], skipped = [], toMerge = [];

  if (!fs.existsSync(dest)) fs.mkdirSync(dest, {recursive: true});

  fs.readdirSync(src, {withFileTypes: true}).forEach(entry => {
    if (entry.name === 'README.md') return;

    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    const relativePath = path.relative(baseDir, destPath).replace(/\\/g, '/');

    if (entry.isDirectory()) {
      const results = copyDirectory(srcPath, destPath, baseDir);
      copied.push(...results.copied);
      skipped.push(...results.skipped);
      toMerge.push(...results.toMerge);
    } else if (fs.existsSync(destPath)) {
      const markers = extractMarkers(fs.readFileSync(srcPath, 'utf8'));

      if (markers.length > 0 || entry.name === '.env') toMerge.push({srcPath, destPath, relativePath, markers}); else skipped.push(relativePath);
    } else {
      fs.copyFileSync(srcPath, destPath);
      copied.push(relativePath);
    }
  });

  return {copied, skipped, toMerge};
}

const findInsertIndex = (lines, searchText, type) => {
  for (let i = 0; i < lines.length; i++) if (lines[i].includes(searchText)) return type === 'before' ? i : i + 1;
  return -1;
};

const collectContentBetweenMarkers = (lines, startIndex) => {
  const content = [];

  for (let i = startIndex + 1; i < lines.length; i++) {
    if (lines[i].trim().includes('@addon-end')) break;

    content.push(lines[i]);
  }

  return content;
};

const normalizeContent = (lines) => lines.map(l => l.trim()).filter(l => l && !l.startsWith('//') && !l.startsWith('#') && !l.startsWith('/*') && !l.startsWith('*')).join('|');

function mergeFile(targetPath, addonPath, markers) {
  const targetContent = fs.readFileSync(targetPath, 'utf8');
  const addonLines = fs.readFileSync(addonPath, 'utf8').split('\n');
  const operations = [];
  let newContent = targetContent;

  markers.forEach(marker => {
    const content = collectContentBetweenMarkers(addonLines, marker.lineIndex);

    if (content.length === 0) return;

    const signature = normalizeContent(content);
    const targetSignature = normalizeContent(newContent.split('\n'));

    if (signature && targetSignature.includes(signature)) {
      operations.push({success: false, type: marker.type, lines: content.length, searchText: marker.searchText});
      return;
    }

    if (marker.type === 'prepend') {
      newContent = content.join('\n') + '\n' + newContent;
      operations.push({success: true, type: 'prepend', lines: content.length});
    } else if (marker.type === 'append') {
      if (!newContent.endsWith('\n')) newContent += '\n';

      newContent += '\n' + content.join('\n') + '\n';
      operations.push({success: true, type: 'append', lines: content.length});
    } else if ((marker.type === 'after' || marker.type === 'before') && marker.searchText) {
      const targetLines = newContent.split('\n');
      const insertIndex = findInsertIndex(targetLines, marker.searchText, marker.type);

      if (insertIndex === -1) {
        operations.push({success: false, type: 'notfound', searchText: marker.searchText});
        return;
      }

      targetLines.splice(insertIndex, 0, ...content);
      newContent = targetLines.join('\n');
      operations.push({success: true, type: marker.type, lines: content.length, searchText: marker.searchText});
    }
  });

  if (newContent !== targetContent) fs.writeFileSync(targetPath, newContent, 'utf8');
  return {modified: newContent !== targetContent, operations};
}

function mergeEnvFile(targetPath, addonPath) {
  const targetContent = fs.readFileSync(targetPath, 'utf8');
  const addonLines = fs.readFileSync(addonPath, 'utf8').split('\n');
  const linesToAdd = [];
  const comments = [];

  let inBlock = false;

  addonLines.forEach(line => {
    const trimmed = line.trim();

    if (trimmed.includes('@addon-insert:append') || trimmed.includes('@addon:insert-append')) {
      inBlock = true;
      return;
    }

    if (trimmed.includes('@addon-end') || trimmed.includes('@addon:insert-end')) {
      inBlock = false;
      return;
    }

    if (!inBlock) return;

    if (trimmed.startsWith('#') || !trimmed) {
      comments.push(line);
      return;
    }

    const match = line.match(/^([A-Z_][A-Z0-9_]*)=/);
    if (match && !new RegExp(`^${match[1]}=`, 'm').test(targetContent)) linesToAdd.push(line);
  });

  if (linesToAdd.length === 0) return {modified: false, added: 0};

  let newContent = targetContent;
  if (!newContent.endsWith('\n')) newContent += '\n';
  newContent += '\n' + comments.join('\n') + '\n' + linesToAdd.join('\n') + '\n';

  fs.writeFileSync(targetPath, newContent, 'utf8');
  return {modified: true, added: linesToAdd.length};
}

function printMergeResults(relativePath, isEnv, result) {
  const indent = '    ';

  if (isEnv) {
    if (result.modified) log(`${indent}${COLORS.green}✓${COLORS.reset} Added ${COLORS.bold}${result.added}${COLORS.reset} environment variable${result.added !== 1 ? 's' : ''}`); else log(`${indent}${COLORS.gray}○${COLORS.reset} ${COLORS.dim}All variables already exist${COLORS.reset}`);
    return result.modified;
  }

  let hasChanges = false;
  result.operations.forEach(op => {
    if (op.success) {
      hasChanges = true;

      if (op.type === 'prepend') log(`${indent}${COLORS.green}✓${COLORS.reset} Prepended ${COLORS.bold}${op.lines}${COLORS.reset} line${op.lines !== 1 ? 's' : ''} to file start`); else if (op.type === 'append') log(`${indent}${COLORS.green}✓${COLORS.reset} Appended ${COLORS.bold}${op.lines}${COLORS.reset} line${op.lines !== 1 ? 's' : ''} to file end`); else if (op.type === 'after') log(`${indent}${COLORS.green}✓${COLORS.reset} Inserted ${COLORS.bold}${op.lines}${COLORS.reset} line${op.lines !== 1 ? 's' : ''} ${COLORS.cyan}after${COLORS.reset} "${COLORS.dim}${op.searchText}${COLORS.reset}"`); else if (op.type === 'before') log(`${indent}${COLORS.green}✓${COLORS.reset} Inserted ${COLORS.bold}${op.lines}${COLORS.reset} line${op.lines !== 1 ? 's' : ''} ${COLORS.cyan}before${COLORS.reset} "${COLORS.dim}${op.searchText}${COLORS.reset}"`);
    } else if (op.type === 'notfound') log(`${indent}${COLORS.yellow}⚠${COLORS.reset} ${COLORS.yellow}Could not find target:${COLORS.reset} "${COLORS.dim}${op.searchText}${COLORS.reset}"`); else log(`${indent}${COLORS.gray}○${COLORS.reset} ${COLORS.dim}Content already exists (${op.type})${COLORS.reset}`);
  });

  return hasChanges;
}

function mergeFiles(toMerge) {
  if (toMerge.length === 0) return {merged: [], failed: [], unchanged: []};

  const merged = [], failed = [], unchanged = [];

  toMerge.forEach(({srcPath, destPath, relativePath, markers}) => {
    const isEnv = path.basename(destPath) === '.env';
    log(`\n  ${COLORS.blue}•${COLORS.reset} ${COLORS.bold}${relativePath}${COLORS.reset}`);

    try {
      const result = isEnv ? mergeEnvFile(destPath, srcPath) : mergeFile(destPath, srcPath, markers);
      if (printMergeResults(relativePath, isEnv, result)) merged.push(relativePath); else unchanged.push(relativePath);
    } catch (error) {
      log(`    ${COLORS.red}✗ Error:${COLORS.reset} ${error.message}`, 'red');
      failed.push(relativePath);
    }
  });

  return {merged, failed, unchanged};
}

function main() {
  const addonName = process.argv[2];

  if (!addonName) {
    log('\n  Usage: node install-addon.js <add-on-name>', 'red');
    log('  Example: node install-addon.js auth\n', 'cyan');
    process.exit(1);
  }

  const addonsDir = path.join(__dirname, 'add-ons');
  const addonPath = path.join(addonsDir, addonName);
  const srcDir = path.join(__dirname, 'src');

  if (!fs.existsSync(addonsDir)) {
    log(`\n  ${COLORS.red}✗${COLORS.reset} Add-ons directory not found: ${addonsDir}\n`);
    process.exit(1);
  }

  if (!fs.existsSync(addonPath)) {
    log(`\n  ${COLORS.red}✗${COLORS.reset} Add-on "${COLORS.bold}${addonName}${COLORS.reset}" not found`);

    const available = fs.readdirSync(addonsDir, {withFileTypes: true}).filter(entry => entry.isDirectory()).map(entry => entry.name);
    if (available.length > 0) {
      log(`\n  Available add-ons:`, 'cyan');
      available.forEach(name => log(`    • ${name}`));
    }
    console.log();
    process.exit(1);
  }

  console.log();
  log(`  ╭${'─'.repeat(62)}╮`);
  log(`  │  ${COLORS.bold}Installing add-on: ${COLORS.cyan}${addonName}${COLORS.reset}${' '.repeat(41 - addonName.length)}│`);
  log(`  ╰${'─'.repeat(62)}╯`);
  console.log();

  log('  📦 Copying new files...', 'bold');
  const {copied, skipped, toMerge} = copyDirectory(addonPath, srcDir, srcDir);

  if (copied.length > 0) {
    console.log();
    log(`  ${COLORS.green}✓${COLORS.reset} Copied ${COLORS.bold}${copied.length}${COLORS.reset} new file${copied.length !== 1 ? 's' : ''}`);
  }

  if (skipped.length > 0) {
    console.log();
    log(`  ${COLORS.gray}○${COLORS.reset} ${COLORS.dim}Skipped ${skipped.length} file${skipped.length !== 1 ? 's' : ''} (no merge markers)${COLORS.reset}`);
  }

  if (toMerge.length > 0) {
    console.log();
    log('  🔀 Merging existing files...', 'bold');
    const {merged, failed, unchanged} = mergeFiles(toMerge);

    console.log();
    log('  ─'.repeat(16), 'gray');
    console.log();

    if (merged.length > 0) log(`  ${COLORS.green}✓${COLORS.reset} Successfully merged ${COLORS.bold}${merged.length}${COLORS.reset} file${merged.length !== 1 ? 's' : ''}`);
    if (unchanged.length > 0) log(`  ${COLORS.gray}○${COLORS.reset} ${COLORS.dim}${unchanged.length} file${unchanged.length !== 1 ? 's' : ''} unchanged (content already exists)${COLORS.reset}`);

    if (failed.length > 0) {
      console.log();
      log(`  ${COLORS.yellow}⚠${COLORS.reset} ${COLORS.yellow}${failed.length} file${failed.length !== 1 ? 's' : ''} failed to merge${COLORS.reset}`);
      log(`  ${COLORS.yellow}Please review manually:${COLORS.reset}`);

      failed.forEach(file => log(`    • ${file}`, 'cyan'));
    }
  }

  console.log();
  log(`  ${COLORS.green}✓${COLORS.reset} ${COLORS.bold}${COLORS.green}Installation complete!${COLORS.reset}`, 'green');
  console.log();
}

main();
