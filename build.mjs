/**
 * UserReminder release builder.
 *
 * Replaces the old phing build. Pipeline:
 *   1. Stage admin + site + task plugin + package manifests into releases/build.
 *   2. Bundle/minify the admin JS with Vite (vendored libs copied verbatim).
 *   3. Replace ##VERSION## in every staged manifest.
 *   4. Zip inner extension zips, then the package zip, into releases/.
 *
 * Usage:  npm run build [-- --version=x.y.z]
 * (version defaults to "version" in package.json; all manifests carry ##VERSION## placeholders)
 */

import archiver from 'archiver';
import { build as viteBuild } from 'vite';
import {
  copyFileSync,
  cpSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  writeFileSync,
} from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createWriteStream } from 'node:fs';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const RELEASES = path.join(ROOT, 'releases');
const STAGING = path.join(RELEASES, 'build');
const PKG_INNER = path.join(STAGING, 'packages', 'pkg_userreminder');
const COMP_DIR = path.join(PKG_INNER, 'com_userreminder');
const PLUGIN_DIR = path.join(PKG_INNER, 'plg_task_userreminder');

const ADMIN = path.join(ROOT, 'administrator', 'components', 'com_userreminder');
const SITE = path.join(ROOT, 'components', 'com_userreminder');
const PLUGIN = path.join(ROOT, 'plugins', 'task', 'userreminder');

// Custom JS bundled by Vite. Anything else in media/js (e.g. the vendored,
// already-minified chart.umd.min.js) is copied verbatim.
const JS_SOURCES = ['dashboard.js', 'optoutusers-select.js', 'userreminder-sidebar.js'];

function resolveVersion() {
  // --version=x.y.z wins; otherwise the version lives in package.json.
  const arg = process.argv.find((a) => a.startsWith('--version='));
  if (arg) {
    return arg.split('=')[1];
  }

  const pkg = JSON.parse(readFileSync(path.join(ROOT, 'package.json'), 'utf8'));

  if (pkg.version) {
    return pkg.version;
  }

  throw new Error('No version: set "version" in package.json or pass --version=x.y.z.');
}

function copyTree(src, dest, { exclude = [] } = {}) {
  cpSync(src, dest, { recursive: true, filter: (s) => !exclude.includes(path.basename(s)) });
}

function collectFiles(dir, ext, acc = []) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) {
      collectFiles(full, ext, acc);
    } else if (entry.name.endsWith(ext)) {
      acc.push(full);
    }
  }

  return acc;
}

function zipDir(src, dest) {
  return new Promise((resolve, reject) => {
    const output = createWriteStream(dest);
    const zip = archiver('zip', { zlib: { level: 9 } });

    output.on('close', () => resolve());
    zip.on('warning', (err) => reject(err));
    zip.on('error', (err) => reject(err));

    zip.pipe(output);
    // Forward slashes are mandatory — backslash separators extract as literal
    // "a\b" filenames on Linux and break the Joomla installer.
    zip.glob('**/*', { cwd: src, dot: true });
    zip.finalize();
  });
}

async function bundleJs(srcDir, outDir) {
  mkdirSync(outDir, { recursive: true });

  for (const file of readdirSync(srcDir)) {
    const src = path.join(srcDir, file);

    if (JS_SOURCES.includes(file)) {
      await viteBuild({
        root: srcDir,
        configFile: false,
        logLevel: 'error',
        build: {
          outDir,
          emptyOutDir: false,
          write: true,
          minify: 'esbuild',
          rollupOptions: {
            input: src,
            output: {
              format: 'iife',
              entryFileNames: file,
            },
          },
        },
      });
    } else {
      copyFileSync(src, path.join(outDir, file));
    }
  }
}

const version = resolveVersion();

rmSync(STAGING, { recursive: true, force: true });
mkdirSync(COMP_DIR, { recursive: true });
mkdirSync(PLUGIN_DIR, { recursive: true });

// --- 1. Stage ---------------------------------------------------------------

copyTree(ADMIN, path.join(COMP_DIR, 'admin'), { exclude: ['userreminder.xml', 'media'] });
copyTree(SITE, path.join(COMP_DIR, 'site'), { exclude: ['com_userreminder.xml'] });

// Root manifest + installer script resolve from the package zip root.
copyFileSync(path.join(ADMIN, 'userreminder.xml'), path.join(COMP_DIR, 'userreminder.xml'));
copyFileSync(path.join(ADMIN, 'script.php'), path.join(COMP_DIR, 'script.php'));

// <media folder="media"> resolves from the manifest root.
copyTree(path.join(ADMIN, 'media'), path.join(COMP_DIR, 'media'));

copyTree(PLUGIN, PLUGIN_DIR, { exclude: ['legacy'] });
copyFileSync(path.join(ROOT, 'pkg_script.php'), path.join(STAGING, 'pkg_script.php'));
copyFileSync(path.join(ROOT, 'pkg_userreminder.xml'), path.join(STAGING, 'pkg_userreminder.xml'));

// --- 2. JS ------------------------------------------------------------------

await bundleJs(path.join(ADMIN, 'media', 'js'), path.join(COMP_DIR, 'media', 'js'));

// --- 3. Version -------------------------------------------------------------

for (const xml of [...collectFiles(STAGING, '.xml'), ...collectFiles(STAGING, '.json')]) {
  writeFileSync(xml, readFileSync(xml, 'utf8').replaceAll('##VERSION##', version));
}

// --- 4. Package -------------------------------------------------------------

await zipDir(COMP_DIR, path.join(PKG_INNER, 'com_userreminder.zip'));
await zipDir(PLUGIN_DIR, path.join(PKG_INNER, 'plg_task_userreminder.zip'));
rmSync(COMP_DIR, { recursive: true, force: true });
rmSync(PLUGIN_DIR, { recursive: true, force: true });

const outFile = path.join(RELEASES, `pkg_userreminder_v${version}.zip`);
await zipDir(STAGING, outFile);
rmSync(STAGING, { recursive: true, force: true });

console.log(`Built ${path.relative(ROOT, outFile)} (v${version})`);
