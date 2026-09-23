#!/usr/bin/env node
/**
 * tools/check_screenshot_css.js
 *
 * Les docs/screenshots/*.html sont des snapshots autonomes : chacun embarque
 * une copie complète du CSS dans un unique <style>. Après un changement de
 * public/css/style.css, ces copies divergent et les PNG ne reflètent plus
 * l'application. Cet outil signale la dérive sans réécrire (les snapshots
 * contiennent des sélecteurs locaux qu'une copie verbatim supprimerait).
 *
 * Un snapshot sans bloc <style> est un snapshot cassé, pas un cas à ignorer :
 * il est compté comme échec (et non passé en silence), sinon un fichier vidé
 * ou tronqué disparaîtrait du contrôle.
 *
 * Usage: node tools/check_screenshot_css.js [--check]
 *   --check : sortie 1 si au moins un snapshot diverge ou est cassé (CI-friendly).
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const css = fs.readFileSync(path.join(ROOT, 'public', 'css', 'style.css'), 'utf8')
  .replace(/\s+/g, ' ')
  .trim();

const dir = path.join(ROOT, 'docs', 'screenshots');
const files = fs.readdirSync(dir).filter((f) => f.endsWith('.html'));
const checkOnly = process.argv.includes('--check');
let stale = 0;
let broken = 0;

for (const file of files) {
  const html = fs.readFileSync(path.join(dir, file), 'utf8');
  const m = html.match(/<style[^>]*>([\s\S]*?)<\/style>/);
  if (!m) {
    broken++;
    console.log(`BROKEN ${file} (pas de bloc <style>)`);
    continue;
  }
  const embedded = m[1].replace(/\s+/g, ' ').trim();
  if (embedded !== css) {
    stale++;
    console.log(`STALE ${file}`);
  }
}

const failed = stale + broken;
console.log(`\n${stale}/${files.length} snapshot(s) divergent(s) de style.css`);
if (broken > 0) {
  console.log(`${broken} snapshot(s) sans bloc <style> — compté(s) comme échec.`);
}
if (checkOnly) {
  process.exit(failed === 0 ? 0 : 1);
}