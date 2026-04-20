const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const PLUGIN_NAME = 'cardlink-payment-gateway';
const ROOT = path.resolve(__dirname, '..');
const OUTPUT_DIR = path.join(ROOT, 'plugin-zip');
const OUTPUT_FILE = path.join(OUTPUT_DIR, `${PLUGIN_NAME}.zip`);

const INCLUDE = [
    'assets',
    'languages',
    'lib',
    'src',
    'vendor',
    'index.php',
    'cardlink-payment-gateway.php',
    'LICENSE.txt',
    'README.txt',
    'README.md',
    'uninstall.php'
];

// Ensure output dir exists
fs.mkdirSync(OUTPUT_DIR, { recursive: true });

const output = fs.createWriteStream(OUTPUT_FILE);
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => {
    console.log(`✅ Plugin zip created: ${OUTPUT_FILE}`);
    console.log(`📦 Total size: ${archive.pointer()} bytes`);
});

archive.on('error', err => {
    throw err;
});

archive.pipe(output);

// Add files/folders
INCLUDE.forEach(item => {
    const fullPath = path.join(ROOT, item);

    if (!fs.existsSync(fullPath)) {
        console.warn(`⚠️ Skipped missing: ${item}`);
        return;
    }

    const stats = fs.statSync(fullPath);

    if (stats.isDirectory()) {
        archive.directory(fullPath, `${PLUGIN_NAME}/${item}`);
    } else {
        archive.file(fullPath, { name: `${PLUGIN_NAME}/${item}` });
    }
});

archive.finalize();
