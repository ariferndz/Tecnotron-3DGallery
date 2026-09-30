// Prepara un modelo GLB para la web.
//
//   npm run optimizar -- <original.glb> --medida <cm> [--salida modelos/<nombre>.glb]
//
// · Lo comprime: geometría cuantizada y texturas WebP de 2048 px como máximo (suele quedar entre 1 y 2 MB).
//   No simplifica la malla, así que no pierde detalle.
// · Si se indica --medida, lo escala para que su lado mayor en planta mida eso en centímetros:
//   así en realidad aumentada la máquina aparece a tamaño real. Es la mayor de «ancho» y «largo» de la ficha.
//
// El resultado no necesita decodificadores extra: lo abre cualquier visor GLB, model-viewer, Scene Viewer y Quick Look.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { NodeIO, getBounds } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);

const { values: opt, positionals } = parseArgs({
  allowPositionals: true,
  options: {
    medida: { type: 'string', short: 'm' },
    salida: { type: 'string', short: 'o' },
    textura: { type: 'string', default: '2048' },
  },
});
const entrada = positionals[0];
if (!entrada || !fs.existsSync(entrada)) {
  console.error('Uso: npm run optimizar -- <original.glb> --medida <cm> [--salida modelos/<nombre>.glb]');
  process.exit(1);
}
const nombre = path.basename(entrada, path.extname(entrada))
  .normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const salida = path.resolve(opt.salida || path.join(ROOT, 'modelos', `${nombre}.glb`));
const medida = opt.medida ? Number(String(opt.medida).replace(',', '.')) : null;
if (opt.medida && !(medida > 0)) {
  console.error(`--medida debe ser un número de centímetros (p. ej. 198), no «${opt.medida}»`);
  process.exit(1);
}

// 1. Compresión con la herramienta de línea de órdenes de glTF-Transform
const tmp = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'tnm-glb-')), 'optimizado.glb');
const cli = path.join(path.dirname(require.resolve('@gltf-transform/cli')), '..', 'bin', 'cli.js');
const r = spawnSync(process.execPath, [cli, 'optimize', entrada, tmp,
  '--compress', 'quantize', '--texture-compress', 'webp', '--texture-size', opt.textura, '--simplify', 'false'], { stdio: 'inherit' });
if (r.status !== 0) process.exit(r.status || 1);

// 2. Tamaño real
const io = new NodeIO().registerExtensions(ALL_EXTENSIONS);
const doc = await io.read(tmp);
const scene = doc.getRoot().getDefaultScene() || doc.getRoot().listScenes()[0];
const medir = () => {
  const b = getBounds(scene);
  return b.max.map((v, i) => v - b.min[i]); // metros: x = ancho, y = alto, z = fondo
};
if (medida) {
  const antes = medir();
  const f = medida / 100 / Math.max(antes[0], antes[2]);
  const raiz = doc.createNode('tamano-real').setScale([f, f, f]);
  for (const hijo of scene.listChildren()) {
    scene.removeChild(hijo);
    raiz.addChild(hijo);
  }
  scene.addChild(raiz);
  console.log(`Escala ×${f.toFixed(3)} para que el lado mayor mida ${medida} cm`);
}
fs.mkdirSync(path.dirname(salida), { recursive: true });
await io.write(salida, doc);
fs.rmSync(path.dirname(tmp), { recursive: true, force: true });

const s = medir().map(v => Math.round(v * 100));
const kb = f => Math.round(fs.statSync(f).size / 1024).toLocaleString('es-ES') + ' KB';
console.log(`\n✓ ${path.relative(process.cwd(), salida)}  ${kb(entrada)} → ${kb(salida)}`);
console.log(`  Medidas del modelo: ${s[0]} × ${s[2]} × ${s[1]} cm (ancho × fondo × alto)`);
if (!medida) console.log('  Sin --medida: se mantiene la escala original del archivo.');
