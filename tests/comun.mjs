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

// Posición y tamaño se miden en pantalla (getBoundingClientRect), que es lo que se ve; el resto, con getComputedStyle.
const PROPIEDADES = ['display', 'paddingTop', 'paddingLeft', 'borderTopWidth', 'borderTopColor',
  'borderRadius', 'backgroundColor', 'color', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'textTransform', 'letterSpacing', 'lineHeight', 'boxShadow',
  'textDecorationLine', 'outlineStyle'];

/** Estilos calculados de todos los elementos del plugin en la página (para comparar con y sin el CSS de un tema). */
export const estilosCalculados = pagina => pagina.evaluate(props => [...document.querySelectorAll('.tnm, .tnm *')]
  .filter(e => !(e.closest('svg') && e.tagName.toLowerCase() !== 'svg'))
  .map(e => {
    const c = getComputedStyle(e);
    const nombre = `${e.tagName.toLowerCase()}${typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).join('.') : ''}`;
    // Posición relativa al contenedor .tnm más cercano: lo que haya fuera del plugin (la cabecera del tema) no cuenta.
    const r = e.getBoundingClientRect();
    const raiz = e.parentElement && e.parentElement.closest('.tnm');
    const o = raiz && !e.matches('.tnm') ? raiz.getBoundingClientRect() : r;
    const caja = r.width || r.height ? `caja: ${Math.round(r.x - o.x)},${Math.round(r.y - o.y)} ${Math.round(r.width)}×${Math.round(r.height)}` : 'caja: oculto';
    return [nombre, [caja, ...props.map(k => `${k}: ${c[k]}`)]];
  }), PROPIEDADES);

/** Diferencias entre dos listas de estilosCalculados(). */
export function diferencias(a, b) {
  const out = [];
  a.forEach(([nombre, props], i) => props.forEach((v, j) => { if (b[i] && v !== b[i][1][j]) out.push(`${nombre} ${v} → ${b[i][1][j].split(': ')[1]}`); }));
  return out;
}
