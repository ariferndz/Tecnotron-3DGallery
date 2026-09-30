// Regenera el plugin listo para instalar: dist/tecnotron-maquinas.zip
//
//   npm ci          (la primera vez)
//   npm run build
//
// 1. Copia las librerías de terceros desde node_modules a tecnotron-maquinas/assets/vendor/
//    y comprueba que sus versiones coinciden con las del plugin (TNM_MV_VERSION, TNM_QR_VERSION y readme.txt).
// 2. Comprueba que la versión del plugin es la misma en la cabecera, en TNM_VERSION y en readme.txt.
// 3. Revisa la sintaxis de todos los PHP (si hay PHP instalado).
// 4. Empaqueta la carpeta en un zip reproducible: mismo contenido → mismo zip, byte a byte.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { zipSync } from 'fflate';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SLUG = 'tecnotron-maquinas';
const PLUGIN = path.join(ROOT, SLUG);
const DIST = path.join(ROOT, 'dist');
const require = createRequire(import.meta.url);

const errores = [];
const leer = f => fs.readFileSync(path.join(PLUGIN, f), 'utf8');
const pkgVersion = name => JSON.parse(fs.readFileSync(require.resolve(`${name}/package.json`), 'utf8')).version;
const buscar = (texto, re, que) => {
  const m = texto.match(re);
  if (!m) errores.push(`No encuentro ${que}`);
  return m ? m[1] : null;
};

/* 1. Librerías de terceros */
const principal = leer(`${SLUG}.php`);
const readme = leer('readme.txt');
const vendor = [
  {
    nombre: 'model-viewer',
    paquete: '@google/model-viewer',
    constante: 'TNM_MV_VERSION',
    archivos: { 'dist/model-viewer.min.js': 'model-viewer.min.js', LICENSE: 'LICENSE-model-viewer.txt' },
  },
  {
    nombre: 'qrcode-generator',
    paquete: 'qrcode-generator',
    constante: 'TNM_QR_VERSION',
    archivos: { 'qrcode.js': 'qrcode.js' },
  },
];
for (const v of vendor) {
  const instalada = pkgVersion(v.paquete);
  const enPlugin = buscar(principal, new RegExp(`define\\(\\s*'${v.constante}',\\s*'([^']+)'`), `${v.constante} en ${SLUG}.php`);
  const enReadme = buscar(readme, new RegExp(`\\* ${v.nombre} ([0-9.]+)`), `la versión de ${v.nombre} en readme.txt`);
  if (enPlugin && enPlugin !== instalada) errores.push(`${v.paquete} instalado es ${instalada} pero ${v.constante} dice ${enPlugin}: cambia la constante en ${SLUG}.php`);
  if (enReadme && enReadme !== instalada) errores.push(`${v.paquete} instalado es ${instalada} pero readme.txt dice ${enReadme}: actualiza la sección «Terceros»`);
  const base = path.dirname(require.resolve(`${v.paquete}/package.json`));
  for (const [origen, destino] of Object.entries(v.archivos)) {
    fs.copyFileSync(path.join(base, origen), path.join(PLUGIN, 'assets/vendor', destino));
  }
  console.log(`✓ ${v.nombre} ${instalada} → assets/vendor/`);
}

/* 2. Versión del plugin */
const cabecera = buscar(principal, /^\s*\*\s*Version:\s*(\S+)/m, 'la línea «Version:» de la cabecera');
const constante = buscar(principal, /define\(\s*'TNM_VERSION',\s*'([^']+)'/, 'TNM_VERSION');
const estable = buscar(readme, /^Stable tag:\s*(\S+)/m, '«Stable tag:» en readme.txt');
if (cabecera && (cabecera !== constante || cabecera !== estable)) {
  errores.push(`Versión distinta: cabecera ${cabecera}, TNM_VERSION ${constante}, readme.txt ${estable}. Deben coincidir.`);
}

/* 3. Sintaxis PHP */
const archivos = [];
(function recorrer(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => (a.name < b.name ? -1 : 1))) {
    if (e.name === '.DS_Store' || e.name === 'Thumbs.db' || e.name.startsWith('.git')) continue;
    const abs = path.join(dir, e.name);
    if (e.isDirectory()) {
      archivos.push({ abs, rel: path.relative(PLUGIN, abs).split(path.sep).join('/') + '/', dir: true });
      recorrer(abs);
    } else {
      archivos.push({ abs, rel: path.relative(PLUGIN, abs).split(path.sep).join('/') });
    }
  }
})(PLUGIN);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });
if (php.status === 0) {
  let n = 0;
  for (const f of archivos.filter(f => f.rel.endsWith('.php'))) {
    const r = spawnSync('php', ['-l', f.abs], { encoding: 'utf8' });
    if (r.status !== 0) errores.push(`PHP con errores en ${f.rel}:\n${r.stdout}${r.stderr}`);
    n++;
  }
  console.log(`✓ sintaxis PHP (${n} archivos, ${php.stdout.split('\n')[0].split(' (')[0]})`);
} else {
  console.log('· PHP no está instalado: me salto la comprobación de sintaxis');
}

if (errores.length) {
  console.error('\n✗ No se ha generado el zip:\n- ' + errores.join('\n- '));
  process.exit(1);
}

/* 4. Zip reproducible: orden fijo y fecha fija (en hora local, así sale igual en cualquier zona horaria) */
const FECHA = new Date(2026, 0, 1, 0, 0, 0);
const entradas = { [`${SLUG}/`]: [new Uint8Array(0), { mtime: FECHA }] };
for (const f of archivos) {
  entradas[`${SLUG}/${f.rel}`] = f.dir ? [new Uint8Array(0), { mtime: FECHA }] : [fs.readFileSync(f.abs), { mtime: FECHA }];
}
const zip = zipSync(entradas, { level: 9 });
fs.mkdirSync(DIST, { recursive: true });
const salida = path.join(DIST, `${SLUG}.zip`);
fs.writeFileSync(salida, zip);
const nArchivos = archivos.filter(f => !f.dir).length;
console.log(`✓ ${path.relative(ROOT, salida)} · versión ${cabecera} · ${nArchivos} archivos · ${Math.round(zip.length / 1024)} KB`);
