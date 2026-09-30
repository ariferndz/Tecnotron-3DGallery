// Saca 4 imágenes de un modelo GLB (vista 3/4, frontal, lateral y trasera) con fondo transparente,
// recortadas y centradas en 4:3. Sirven de imagen principal y galería de la máquina en WordPress.
//
//   npm run imagenes -- modelos/block-car.glb [otro.glb ...]      → modelos/block-car-1.png … -4.png
//   npm run imagenes -- modelos/block-car.glb --salida carpeta/
//
// Usa Chromium a través de Playwright (npx playwright install chromium, la primera vez).
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { createRequire } from 'node:module';
import { parseArgs } from 'node:util';
import sharp from 'sharp';
import { chromium } from 'playwright';

const require = createRequire(import.meta.url);
const { values: opt, positionals: glbs } = parseArgs({
  allowPositionals: true,
  options: { salida: { type: 'string', short: 'o' }, ancho: { type: 'string', default: '1200' } },
});
if (!glbs.length || glbs.some(f => !fs.existsSync(f))) {
  console.error('Uso: npm run imagenes -- <modelo.glb> [otro.glb ...] [--salida carpeta/]');
  process.exit(1);
}

// Vistas: órbita de cámara de model-viewer (ángulo horizontal, ángulo vertical, distancia)
const VISTAS = ['-35deg 72deg auto', '0deg 80deg auto', '90deg 75deg auto', '150deg 70deg auto'];

const mv = require.resolve('@google/model-viewer/dist/model-viewer.min.js');
const pagina = `<!doctype html><script type="module" src="/mv.js"></script>
<style>body{margin:0;background:transparent}model-viewer{width:1200px;height:900px;--poster-color:transparent}</style>
<model-viewer id="mv" shadow-intensity="1" shadow-softness=".8" exposure="1.05" environment-image="neutral"
  field-of-view="24deg" interaction-prompt="none"></model-viewer>`;
const servidor = http.createServer((req, res) => {
  if (req.url === '/') return res.end(pagina);
  if (req.url === '/mv.js') {
    res.setHeader('content-type', 'text/javascript');
    return fs.createReadStream(mv).pipe(res);
  }
  const i = Number(req.url.replace('/m/', ''));
  if (!glbs[i]) return res.writeHead(404).end();
  res.setHeader('content-type', 'model/gltf-binary');
  fs.createReadStream(glbs[i]).pipe(res);
});
await new Promise(ok => servidor.listen(0, '127.0.0.1', ok));
const base = `http://127.0.0.1:${servidor.address().port}`;

// Sin GPU (servidores, CI) Chromium dibuja WebGL con SwiftShader
const browser = await chromium.launch({ args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
try {
  const p = await browser.newPage({ viewport: { width: 1200, height: 900 } });
  await p.goto(base + '/');
  for (const [i, glb] of glbs.entries()) {
    const id = path.basename(glb, '.glb');
    const dir = opt.salida || path.dirname(glb);
    fs.mkdirSync(dir, { recursive: true });
    await p.evaluate(async src => {
      const el = document.getElementById('mv');
      await customElements.whenDefined('model-viewer');
      await new Promise((ok, ko) => {
        el.addEventListener('load', ok, { once: true });
        el.addEventListener('error', ko, { once: true });
        el.src = src;
      });
    }, `/m/${i}`);
    for (const [n, orbita] of VISTAS.entries()) {
      const b64 = await p.evaluate(async orbita => {
        const el = document.getElementById('mv');
        el.cameraOrbit = orbita;
        el.jumpCameraToGoal();
        await new Promise(r => setTimeout(r, 500));
        const u = new Uint8Array(await (await el.toBlob({ mimeType: 'image/png' })).arrayBuffer());
        let s = '';
        for (let k = 0; k < u.length; k++) s += String.fromCharCode(u[k]);
        return btoa(s);
      }, orbita);
      // Recorta el espacio vacío y centra la máquina en un lienzo 4:3 con un poco de margen
      const t = await sharp(Buffer.from(b64, 'base64')).trim({ threshold: 1 }).toBuffer({ resolveWithObject: true });
      const { width: w, height: h } = t.info;
      const W = Math.round(Math.max(w * 1.12, (h * 1.12 * 4) / 3));
      const H = Math.round((W * 3) / 4);
      const archivo = path.join(dir, `${id}-${n + 1}.png`);
      await sharp(t.data)
        .extend({
          top: Math.floor((H - h) / 2), bottom: Math.ceil((H - h) / 2),
          left: Math.floor((W - w) / 2), right: Math.ceil((W - w) / 2),
          background: { r: 0, g: 0, b: 0, alpha: 0 },
        })
        .resize({ width: Number(opt.ancho), withoutEnlargement: true })
        .png({ compressionLevel: 9 })
        .toFile(archivo);
      console.log('✓', archivo);
    }
  }
} finally {
  await browser.close();
  servidor.close();
}
