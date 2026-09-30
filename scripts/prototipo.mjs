// Genera el prototipo HTML del catálogo en un único archivo que se abre sin servidor ni conexión:
// incluye model-viewer, el generador de QR, los GLB de modelos/ y sus imágenes.
//
//   npm run prototipo   → prototipo/catalogo-maquinas.html
//
// El prototipo es la maqueta que se aprobó antes de hacer el plugin; el código de producción está en tecnotron-maquinas/.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);
const MODELOS = path.join(ROOT, 'modelos');

let html = fs.readFileSync(path.join(ROOT, 'prototipo/catalogo.src.html'), 'utf8');
const qr = fs.readFileSync(require.resolve('qrcode-generator/qrcode.js'), 'utf8');
if (/<\/script|<!--/i.test(qr)) throw new Error('qrcode.js contiene secuencias que romperían el HTML');
const mv = fs.readFileSync(require.resolve('@google/model-viewer/dist/model-viewer.min.js'));

const posters = {};
const glbs = [];
for (const f of fs.readdirSync(MODELOS).filter(f => f.endsWith('.glb')).sort()) {
  const id = f.replace('.glb', '');
  const img = path.join(MODELOS, `${id}-1.png`);
  if (fs.existsSync(img)) {
    // Póster de la tarjeta: la vista 3/4 recortada y centrada en 4:3
    const t = await sharp(img).trim({ threshold: 1 }).toBuffer({ resolveWithObject: true });
    const { width: w, height: h } = t.info;
    const W = Math.round(Math.max(w * 1.1, (h * 1.1 * 4) / 3));
    const H = Math.round((W * 3) / 4);
    const buf = await sharp(t.data)
      .extend({
        top: Math.floor((H - h) / 2), bottom: Math.ceil((H - h) / 2),
        left: Math.floor((W - w) / 2), right: Math.ceil((W - w) / 2),
        background: { r: 0, g: 0, b: 0, alpha: 0 },
      })
      .resize({ width: 720, withoutEnlargement: true })
      .webp({ quality: 84, alphaQuality: 90 })
      .toBuffer();
    posters[id] = 'data:image/webp;base64,' + buf.toString('base64');
  }
  glbs.push(`<script type="application/octet-stream" id="glb-${id}">${fs.readFileSync(path.join(MODELOS, f)).toString('base64')}</script>`);
  console.log('✓', id);
}

const assets = [
  `<script>const POSTERS = ${JSON.stringify(posters)};</script>`,
  `<script type="text/plain" id="mv-lib">${mv.toString('base64')}</script>`,
  ...glbs,
].join('\n');
html = html.replace('<!--%%ASSETS%%-->', () => assets).replace('/*%%QR_LIB%%*/', () => qr);
const out = path.join(ROOT, 'prototipo/catalogo-maquinas.html');
fs.writeFileSync(out, html);
console.log(`✓ ${path.relative(ROOT, out)} · ${(fs.statSync(out).size / 1024 / 1024).toFixed(1)} MB`);
