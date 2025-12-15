#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const COLORS = {
  reset: '\x1b[0m', green: '\x1b[32m', yellow: '\x1b[33m', red: '\x1b[31m', cyan: '\x1b[36m', bold: '\x1b[1m'
};

const log = (message, color = 'reset') => console.log(`${COLORS[color]}${message}${COLORS.reset}`);

function extractMarkers(content) {
  const markers = [];
  const lines = content.split('\n');
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (line.includes('@addon-insert:after')) {
      const match = line.match(/@addon-insert:after\s*\(\s*["'](.+?)["']\s*\)/);
      if (match) markers.push({type: 'after', lineIndex: i, searchText: match[1]});
    } else if (line.includes('@addon-insert:before')) {
      const match = line.match(/@addon-insert:before\s*\(\s*["'](.+?)["']\s*\)/);
      if (match) markers.push({type: 'before', lineIndex: i, searchText: match[1]});
    } else if (line.includes('@addon-insert:prepend')) {
      markers.push({type: 'prepend', lineIndex: i});
    } else if (line.includes('@addon-insert:append')) {
      markers.push({type: 'append', lineIndex: i});
    }
  }
  return markers;
}

function copyDirectory(src, dest, baseDir) {
  const copied = [], skipped = [], toMerge = [];
  if (!fs.existsSync(dest)) fs.mkdirSync(dest, {recursive: true});

  const entries = fs.readdirSync(src, {withFileTypes: true});
  for (const entry of entries) {
    if (entry.name === 'README.md') continue;

    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    const relativePath = path.relative(baseDir, destPath).replace(/\\/g, '/');

    if (entry.isDirectory()) {
      const results = copyDirectory(srcPath, destPath, baseDir);
      copied.push(...results.copied);
      skipped.push(...results.skipped);
      toMerge.push(...results.toMerge);
    } else {
      if (fs.existsSync(destPath)) {
        const content = fs.readFileSync(srcPath, 'utf8');
        const markers = extractMarkers(content);
        if (markers.length > 0 || entry.name === '.env') toMerge.push({srcPath, destPath, relativePath, markers}); else skipped.push(relativePath);
      } else {
        fs.copyFileSync(srcPath, destPath);
        copied.push(relativePath);
      }
    }
  }
  return {copied, skipped, toMerge};
}

function findInsertIndex(lines, searchText, type = 'after') {
  for (let i = 0; i < lines.length; i++) if (lines[i].includes(searchText)) return type === 'before' ? i : i + 1;
  return -1;
}

function collectContentBetweenMarkers(addonLines, startIndex) {
  const content = [];
  for (let i = startIndex + 1; i < addonLines.length; i++) {
    const trimmed = addonLines[i].trim();
    if (trimmed.includes('@addon-end')) break;
    content.push(addonLines[i]);
  }
  return content;
}

function getContentSignature(lines) {
  return lines.map(l => l.trim()).filter(l => l && !l.startsWith('//') && !l.startsWith('#')).join('|');
}

function mergeFile(targetPath, addonPath, markers) {
  let targetContent = fs.readFileSync(targetPath, 'utf8');
  const addonLines = fs.readFileSync(addonPath, 'utf8').split('\n');
  let modified = false;

  for (const marker of markers) {
    if (marker.type === 'prepend') {
      const content = collectContentBetweenMarkers(addonLines, marker.lineIndex);
      const signature = getContentSignature(content);
      const targetSignature = getContentSignature(targetContent.split('\n'));

      if (signature && !targetSignature.includes(signature)) {
        targetContent = content.join('\n') + '\n' + targetContent;
        modified = true;
        log(`  ✓ Prepended ${content.length} line(s)`, 'green');
      } else log(`  ✓ Prepend content already exists`, 'green');
    } else if (marker.type === 'append') {
      const content = collectContentBetweenMarkers(addonLines, marker.lineIndex);
      const signature = getContentSignature(content);
      const targetSignature = getContentSignature(targetContent.split('\n'));

      if (signature && !targetSignature.includes(signature)) {
        if (!targetContent.endsWith('\n')) targetContent += '\n';
        targetContent += '\n' + content.join('\n') + '\n';
        modified = true;
        log(`  ✓ Appended ${content.length} line(s)`, 'green');
      } else log(`  ✓ Append content already exists`, 'green');
    } else if ((marker.type === 'after' || marker.type === 'before') && marker.searchText) {
      const targetLines = targetContent.split('\n');
      const insertIndex = findInsertIndex(targetLines, marker.searchText, marker.type);
      if (insertIndex === -1) {
        log(`  ⚠ Could not find "${marker.searchText}"`, 'yellow');
        continue;
      }
      const content = collectContentBetweenMarkers(addonLines, marker.lineIndex);
      const signature = getContentSignature(content);
      const targetSignature = getContentSignature(targetLines);

      if (signature && !targetSignature.includes(signature)) {
        targetLines.splice(insertIndex, 0, ...content);
        targetContent = targetLines.join('\n');
        modified = true;
        log(`  ✓ Inserted ${content.length} line(s) ${marker.type} "${marker.searchText}"`, 'green');
      } else log(`  ✓ Content already exists ${marker.type} "${marker.searchText}"`, 'green');
    }
  }

  if (modified) fs.writeFileSync(targetPath, targetContent, 'utf8');
  return modified;
}

function mergeEnvFile(targetPath, addonPath) {
  let targetContent = fs.readFileSync(targetPath, 'utf8');
  const addonLines = fs.readFileSync(addonPath, 'utf8').split('\n');
  const linesToAdd = [];
  let inMarkerBlock = false;
  let blockComment = [];

  for (const line of addonLines) {
    const trimmed = line.trim();

    if (trimmed.includes('@addon-insert:append') || trimmed.includes('@addon:insert-append')) {
      inMarkerBlock = true;
      continue;
    }
    if (trimmed.includes('@addon-end') || trimmed.includes('@addon:insert-end')) {
      inMarkerBlock = false;
      continue;
    }

    if (!inMarkerBlock) continue;

    if (trimmed.startsWith('#')) {
      blockComment.push(line);
      continue;
    }

    if (!trimmed) {
      blockComment.push(line);
      continue;
    }

    const match = line.match(/^([A-Z_][A-Z0-9_]*)=/);
    if (match && !new RegExp(`^${match[1]}=`, 'm').test(targetContent)) linesToAdd.push(line);
  }

  if (linesToAdd.length === 0) {
    log(`  ✓ All environment variables already exist`, 'green');
    return false;
  }

  if (!targetContent.endsWith('\n')) targetContent += '\n';
  targetContent += '\n' + blockComment.join('\n') + '\n' + linesToAdd.join('\n') + '\n';
  fs.writeFileSync(targetPath, targetContent, 'utf8');
  log(`  ✓ Added ${linesToAdd.length} environment variable(s)`, 'green');
  return true;
}

function mergeFiles(toMerge) {
  if (toMerge.length === 0) return {merged: [], failed: []};
  log('\n🔀 Auto-merging files...', 'bold');

  const merged = [], failed = [];
  for (const {srcPath, destPath, relativePath, markers} of toMerge) {
    log(`\n  Merging: ${relativePath}`, 'cyan');
    try {
      const success = markers.length > 0 ? mergeFile(destPath, srcPath, markers) : mergeEnvFile(destPath, srcPath);
      if (success) merged.push(relativePath);
    } catch (error) {
      log(`  ✗ Error: ${error.message}`, 'red');
      failed.push(relativePath);
    }
  }
  return {merged, failed};
}

function main() {
  const addonName = process.argv[2];
  if (!addonName) {
    log('Usage: node install-addon.js <addon-name>', 'red');
    log('Example: node install-addon.js auth', 'cyan');
    process.exit(1);
  }

  const addonsDir = path.join(__dirname, 'add-ons');
  const addonPath = path.join(addonsDir, addonName);
  const srcDir = path.join(__dirname, 'src');

  if (!fs.existsSync(addonsDir)) {
    log(`✗ Add-ons directory not found: ${addonsDir}`, 'red');
    process.exit(1);
  }

  if (!fs.existsSync(addonPath)) {
    log(`✗ Add-on "${addonName}" not found`, 'red');
    const available = fs.readdirSync(addonsDir, {withFileTypes: true}).filter(entry => entry.isDirectory()).map(entry => entry.name);
    if (available.length > 0) {
      log('\nAvailable add-ons:', 'cyan');
      available.forEach(name => log(`  • ${name}`, 'reset'));
    }
    process.exit(1);
  }

  log(`\n${COLORS.bold}Installing add-on: ${addonName}${COLORS.reset}\n`);
  log('📦 Copying files...', 'bold');

  const {copied, skipped, toMerge} = copyDirectory(addonPath, srcDir, srcDir);

  console.log();
  if (copied.length > 0) log(`✓ Copied ${copied.length} new file(s)`, 'green');
  if (skipped.length > 0) log(`⊘ Skipped ${skipped.length} existing file(s)`, 'yellow');

  const {merged, failed} = mergeFiles(toMerge);

  if (merged.length > 0) {
    console.log();
    log(`✓ Auto-merged ${merged.length} file(s)`, 'green');
  }

  if (failed.length > 0) {
    console.log();
    log(`⚠ ${failed.length} file(s) could not be auto-merged`, 'yellow');
    log('  Please review these files manually:', 'yellow');
    failed.forEach(file => log(`    • ${file}`, 'cyan'));
  }

  console.log();
  log(`✓ Add-on "${addonName}" installation complete!`, 'green');
  console.log();
}

main();
