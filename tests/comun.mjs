// Utilidades compartidas por las pruebas de extremo a extremo.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

export const BASE = (process.env.BASE || 'http://localhost:8080').replace(/\/$/, '');
export const USUARIO = process.env.WP_USER || 'admin';
export const CLAVE = process.env.WP_PASS || 'admin123';
export const CAPTURAS = path.join(path.dirname(fileURLToPath(import.meta.url)), 'capturas');
fs.mkdirSync(CAPTURAS, { recursive: true });

let fallos = 0;
export const errores = [];

/** Muestra ✓ o ✗ y cuenta los fallos. */
export function comprobar(condicion, texto, detalle = '') {
  console.log(`${condicion ? '✓' : '✗'} ${texto}${detalle !== '' ? ` — ${detalle}` : ''}`);
  if (!condicion) fallos++;
}

/** Recoge errores de JavaScript, de consola y respuestas HTTP fallidas de una página. */
export function vigilar(pagina, etiqueta) {
  const ignorar = /favicon|gravatar|fonts\.g|ERR_TUNNEL|ERR_CERT|ERR_NAME/;
  pagina.on('pageerror', e => errores.push(`[${etiqueta}] ${e.message}`));
  pagina.on('console', m => {
    if (m.type() === 'error' && !ignorar.test(m.text())) errores.push(`[${etiqueta}] ${m.text()}`);
  });
  pagina.on('response', r => {
    if (r.status() >= 400 && !ignorar.test(r.url())) errores.push(`[${etiqueta}] HTTP ${r.status()} ${r.url()}`);
  });
  return pagina;
}

/** Chromium sin GPU: WebGL con SwiftShader para que el visor 3D funcione en servidores y CI. */
export const abrirNavegador = () =>
  chromium.launch({ args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });

/** Cierra el navegador, informa de los errores y deja el código de salida (0 = todo bien). */
export async function terminar(navegador) {
  await navegador.close();
  comprobar(errores.length === 0, 'sin errores de JavaScript ni HTTP', errores.length ? '\n  ' + errores.join('\n  ') : '');
  console.log(fallos ? `\n✗ ${fallos} comprobación(es) fallida(s)` : '\n✓ Todo correcto');
  console.log(`Capturas en ${CAPTURAS}`);
  process.exitCode = fallos ? 1 : 0;
}
