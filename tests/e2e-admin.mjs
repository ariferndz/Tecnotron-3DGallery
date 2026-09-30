// Recorre el escritorio de WordPress: listado, edición de una máquina, categorías, ajustes,
// exportar e importar CSV, solicitudes y biblioteca de modelos 3D.
//
//   BASE=http://localhost:8080 WP_USER=admin WP_PASS=admin123 node tests/e2e-admin.mjs
//
// Necesita el catálogo de demostración cargado (npm run dev:datos). Modifica «Block Car» (consumo, peso y un PDF).
import fs from 'node:fs';
import { BASE, CAPTURAS, CLAVE, USUARIO, abrirNavegador, comprobar, terminar, vigilar } from './comun.mjs';

const navegador = await abrirNavegador();
const shot = (p, nombre, opciones = {}) => p.screenshot({ path: `${CAPTURAS}/admin-${nombre}.png`, ...opciones });
const texto = async (p, sel) => ((await p.textContent(sel)) || '').replace(/\s+/g, ' ').trim();

try {
  const p = vigilar(await navegador.newPage({ viewport: { width: 1440, height: 1000 } }), 'admin');
  await p.goto(BASE + '/wp-login.php');
  await p.fill('#user_login', USUARIO);
  await p.fill('#user_pass', CLAVE);
  await p.click('#wp-submit');
  await p.waitForURL(/wp-admin/);
  comprobar(true, 'acceso al escritorio');

  await p.goto(BASE + '/wp-admin/edit.php?post_type=tn_maquina');
  const filas = await p.locator('#the-list tr').count();
  comprobar(filas > 0, 'el listado de máquinas tiene filas', filas);
  await shot(p, '1-listado');

  /* ---------- editar una máquina ---------- */
  const id = await p.evaluate(async () => (await (await fetch('/wp-json/tecnotron/v1/maquinas')).json()).find(m => m.slug === 'block-car').id);
  await p.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await p.waitForSelector('#tnm_ficha');
  await p.locator('#tnm_modelo').scrollIntoViewIfNeeded(); // model-viewer no carga hasta que es visible
  await p.waitForFunction(() => document.querySelector('[data-tnm-glb-stage] model-viewer')?.loaded, null, { timeout: 60000 });
  await p.waitForTimeout(800);
  const glb = await texto(p, '[data-tnm-glb-check]');
  comprobar(/cm/.test(glb), 'la vista previa del GLB mide el modelo', glb);
  comprobar((await p.locator('[data-tnm-galeria] li').count()) === 3, 'la galería tiene 3 imágenes (más la principal)');
  await shot(p, '2-editar-modelo');
  await p.locator('#tnm_ficha').scrollIntoViewIfNeeded();
  await shot(p, '3-editar-ficha');

  await p.fill('input[name="tnm[consumo]"]', '0,2 kW');
  await p.fill('input[name="tnm[peso]"]', '120,5');
  const pdfsAntes = await p.locator('#tnm_fichas tbody tr').count();
  await p.click('[data-tnm-pdf-add]');
  await p.fill('#tnm_fichas tbody tr:last-child input[type=text]', 'Manual (ENG)');
  await p.fill('#tnm_fichas tbody tr:last-child [data-tnm-pdf-url]', 'https://example.com/manual.pdf');
  await p.click('#publish');
  await p.waitForSelector('#message');
  comprobar((await p.inputValue('input[name="tnm[consumo]"]')) === '0,2 kW', 'se guarda el consumo');
  comprobar((await p.inputValue('input[name="tnm[peso]"]')) === '120,5', 'se guarda el peso');
  comprobar((await p.locator('#tnm_fichas tbody tr').count()) === pdfsAntes + 1, 'se añade la ficha PDF');
  const pub = await p.evaluate(async () => (await (await fetch('/wp-json/tecnotron/v1/maquinas')).json()).find(m => m.slug === 'block-car'));
  comprobar(pub.consumo === '0,2 kW' && Number(pub.peso_kg) === 120.5, 'la API refleja los cambios', `${pub.consumo} · ${pub.peso_kg} kg`);

  /* ---------- categorías, ajustes, importar/exportar ---------- */
  await p.goto(BASE + '/wp-admin/edit-tags.php?taxonomy=tn_categoria&post_type=tn_maquina');
  comprobar((await p.locator('#the-list tr').count()) === 5, 'hay 5 categorías');
  await shot(p, '4-categorias');
  await p.goto(BASE + '/wp-admin/edit.php?post_type=tn_maquina&page=tnm-ajustes');
  await shot(p, '5-ajustes', { fullPage: true });
  await p.goto(BASE + '/wp-admin/edit.php?post_type=tn_maquina&page=tnm-importar');
  await shot(p, '6-importar', { fullPage: true });

  const descarga = p.waitForEvent('download');
  await p.click('a:has-text("Descargar CSV")');
  const csv = fs.readFileSync(await (await descarga).path(), 'utf8');
  const cabecera = csv.split('\n')[0].replace('﻿', '');
  comprobar(cabecera.startsWith('nombre;slug;categoria'), 'se exporta el CSV', cabecera.slice(0, 40) + '…');
  const tmp = `${CAPTURAS}/exportado.csv`;
  fs.writeFileSync(tmp, csv);
  await p.setInputFiles('input[name="tnm_csv"]', tmp);
  await p.click('#submit');
  await p.waitForSelector('.tnm-import-result');
  const actualizadas = await p.locator('.tnm-st-actualizada').count();
  const conError = await p.locator('.tnm-st-error').count();
  comprobar(actualizadas === 42 && conError === 0, 'reimportar el CSV actualiza las 42 sin errores', `${actualizadas} actualizadas, ${conError} errores`);

  /* ---------- solicitudes y biblioteca ---------- */
  await p.goto(BASE + '/wp-admin/edit.php?post_type=tn_solicitud');
  comprobar(true, 'listado de solicitudes', `${await p.locator('#the-list tr:not(.no-items)').count()} solicitud(es)`);
  await shot(p, '7-solicitudes');
  await p.goto(BASE + '/wp-admin/upload.php?post_mime_type=model/gltf-binary&mode=list');
  comprobar((await p.locator('#the-list tr').count()) === 3, 'la biblioteca filtra los 3 modelos 3D');
} catch (e) {
  comprobar(false, 'la prueba terminó sin excepciones', e.message.split('\n')[0]);
} finally {
  await terminar(navegador);
}
