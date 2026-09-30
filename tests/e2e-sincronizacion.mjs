// Sincronización con Plataformas: conexión en Ajustes, primera lectura del catálogo, descargas sin repetir, bloqueo
// de la edición, aviso firmado, retirada de máquinas y desconexión.
//
//   BASE=http://localhost:8080 WP_USER=admin WP_PASS=admin123 node tests/e2e-sincronizacion.mjs
//
// Levanta en este ordenador un Plataformas de mentira (puerto 8099) que WordPress alcanza, desde Docker, en
// http://host.docker.internal:8099 (PLATAFORMAS_PUERTO y PLATAFORMAS_DESDE_WP para cambiarlos). Al terminar deja el
// catálogo como estaba: vuelve a publicar lo que retira, manda a la papelera la máquina que crea y desconecta.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { createHmac } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { BASE, CLAVE, USUARIO, abrirNavegador, comprobar, terminar, vigilar, CAPTURAS } from './comun.mjs';

const PUERTO = Number(process.env.PLATAFORMAS_PUERTO || 8099);
const DESDE_WP = (process.env.PLATAFORMAS_DESDE_WP || `http://host.docker.internal:${PUERTO}`).replace(/\/$/, '');
const CLAVE_API = 'clave-de-prueba';
const SECRETO = 'secreto-de-prueba';
const MODELOS = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'modelos');
const PDF = Buffer.from('%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF');

/* ---------- el Plataformas de mentira ---------- */
const ahora = () => new Date().toISOString();
const maquina = (id, slug, name, extra = {}) => ({
  id, slug, name, code: null, category: null, description: null, w_cm: 100, l_cm: 100, h_cm: null, weight_kg: null,
  power: null, supply: null, conformity: null, photos: [], model: null, docs: [],
  web: { publish: true, order: null, featured: false }, edit_url: `${DESDE_WP}/maquinas/${id}`, updated_at: ahora(), ...extra
});
const fichero = f => `${DESDE_WP}/uploads/machines/${f}`;
const catalogo = [
  maquina('pla-block', 'block-car', 'Block Car', {
    code: 'KQ100', category: 'Kiddie rides', w_cm: 141, l_cm: 72.5, h_cm: 156, weight_kg: 134, power: '0,3 kW', supply: '220/240 V 50 Hz', conformity: 'CE',
    description: 'Coche de bloques escrito en Plataformas.\n\n• Luces LED\n• Música original',
    photos: [1, 2, 3, 4].map(i => fichero(`block-car-${i}.png`)),
    model: { url: fichero('block-car.glb'), updated_at: ahora() },
    docs: [{ label: 'Especificaciones (Plataformas)', url: `${DESDE_WP}/uploads/docs/ficha.pdf` }]
  }),
  maquina('pla-nueva', 'maquina-de-plataformas', 'Máquina de Plataformas', {
    category: 'Carruseles', description: 'Nació en Plataformas.', w_cm: 200, l_cm: 180, h_cm: 250, photos: [fichero('peppa-bus-1.png')]
  }),
  maquina('pla-typhoon', 'typhoon', 'Typhoon', { category: 'Simuladores y arcade', w_cm: 145, l_cm: 231, h_cm: 201, web: { publish: false, order: null, featured: false } })
];
const descargas = {};
let lecturas = 0;
const servidor = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://x');
  if (url.pathname === '/api/catalog') {
    if (req.headers.authorization !== `Bearer ${CLAVE_API}`) { res.writeHead(401, { 'content-type': 'application/json' }); return res.end('{"error":"clave"}'); }
    lecturas++;
    res.writeHead(200, { 'content-type': 'application/json' });
    return res.end(JSON.stringify({ version: 1, source: DESDE_WP, machines: catalogo }));
  }
  descargas[url.pathname] = (descargas[url.pathname] || 0) + 1;
  if (url.pathname === '/uploads/docs/ficha.pdf') { res.writeHead(200, { 'content-type': 'application/pdf' }); return res.end(PDF); }
  const m = /^\/uploads\/machines\/([\w-]+\.(png|glb))$/.exec(url.pathname);
  if (m && fs.existsSync(path.join(MODELOS, m[1]))) {
    res.writeHead(200, { 'content-type': m[2] === 'png' ? 'image/png' : 'model/gltf-binary' });
    return res.end(fs.readFileSync(path.join(MODELOS, m[1])));
  }
  res.writeHead(404); res.end();
});
await new Promise(ok => servidor.listen(PUERTO, '0.0.0.0', ok));

/* ---------- WordPress ---------- */
const api = async () => (await (await fetch(`${BASE}/wp-json/tecnotron/v1/maquinas?_=${Date.now()}`)).json());
const deApi = async slug => (await api()).find(x => x.slug === slug);
const esperar = async (cond, ms = 30000) => {
  for (const fin = Date.now() + ms; Date.now() < fin;) { if (await cond()) return true; await new Promise(r => setTimeout(r, 500)); }
  return false;
};
const navegador = await abrirNavegador();

try {
  const p = vigilar(await navegador.newPage({ viewport: { width: 1440, height: 1000 } }), 'sincronizacion');
  await p.goto(BASE + '/wp-login.php');
  await p.fill('#user_login', USUARIO);
  await p.fill('#user_pass', CLAVE);
  await p.click('#wp-submit');
  await p.waitForURL(/wp-admin/);
  const AJUSTES = BASE + '/wp-admin/edit.php?post_type=tn_maquina&page=tnm-ajustes';
  const sincronizarAhora = async () => {
    await p.goto(AJUSTES);
    await p.click('.tnm-sync-estado a.button');
    await p.waitForSelector('.tnm-sync-resultado', { timeout: 180000 });   // la primera descarga fotos y modelo
    return (await p.textContent('.tnm-sync-resultado')).trim();
  };

  /* ---------- conectar ---------- */
  await p.goto(AJUSTES);
  comprobar((await p.textContent('#tnm-plataformas + p')).includes('Plataformas'), 'Ajustes explica la sincronización');
  comprobar((await p.textContent('.form-table code:has-text("sincronizar")')).endsWith('/wp-json/tecnotron/v1/sincronizar'), 'Ajustes da la dirección del aviso para Plataformas');
  await p.fill('#tnm-sync-url', DESDE_WP);
  await p.fill('#tnm-sync-clave', 'clave-equivocada');
  await p.click('#submit');
  await p.waitForSelector('#setting-error-settings_updated');
  let r = await sincronizarAhora();
  comprobar(/no acepta la clave/.test(r), 'con una clave equivocada se explica el error', r);

  await p.fill('#tnm-sync-clave', CLAVE_API);
  await p.fill('#tnm-sync-secreto', SECRETO);
  await p.click('#submit');
  await p.waitForSelector('#setting-error-settings_updated');
  comprobar(/guardada/.test(await p.getAttribute('#tnm-sync-clave', 'placeholder')) && (await p.inputValue('#tnm-sync-clave')) === '', 'la clave guardada no se vuelve a mostrar');
  r = await sincronizarAhora();
  comprobar(/1 creadas, 2 actualizadas/.test(r), 'la primera sincronización crea la nueva y actualiza las que ya había', r);
  await p.screenshot({ path: `${CAPTURAS}/sync-1-ajustes.png`, fullPage: true });

  const block = await deApi('block-car');
  comprobar(block.codigo === 'KQ100' && block.consumo === '0,3 kW' && Number(block.peso_kg) === 134, 'Block Car recibe las características de Plataformas', `${block.codigo} · ${block.consumo} · ${block.peso_kg} kg`);
  comprobar(block.descripcion === 'Coche de bloques escrito en Plataformas.\n\n• Luces LED\n• Música original', 'y su descripción, con párrafos y lista', JSON.stringify(block.descripcion));
  comprobar(block.imagenes.length === 4 && /block-car-1/.test(block.imagenes[0]), 'y sus 4 fotos, la primera como principal', block.imagenes[0]);
  comprobar(/block-car.*\.glb$/.test(block.glb || ''), 'y su modelo 3D', block.glb);
  comprobar(block.fichas.length === 1 && block.fichas[0].label === 'Especificaciones (Plataformas)' && /\.pdf$/.test(block.fichas[0].url), 'y su PDF, copiado a la biblioteca', block.fichas[0]?.url);
  const nueva = await deApi('maquina-de-plataformas');
  comprobar(nueva && nueva.categoria === 'Carruseles' && nueva.imagenes.length === 1, 'la máquina nueva se crea publicada, con su categoría y su foto');
  comprobar(!(await deApi('typhoon')), 'la que no está publicada en Plataformas sale de la web');

  // Segunda vez: nada ha cambiado, no se descarga nada
  const antes = JSON.stringify(descargas);
  r = await sincronizarAhora();
  comprobar(/3 sin cambios/.test(r), 'sin cambios en Plataformas no se toca nada', r);
  comprobar(JSON.stringify(descargas) === antes && Object.values(descargas).every(n => n === 1), 'cada fichero se descarga una sola vez', JSON.stringify(descargas));

  /* ---------- la máquina se edita en Plataformas ---------- */
  await p.goto(`${BASE}/wp-admin/edit.php?post_type=tn_maquina`);
  comprobar(await p.isVisible('.tnm-sync-lista'), 'el listado avisa de que el catálogo se edita en Plataformas');
  await p.goto(`${BASE}/wp-admin/edit.php?post_type=tn_maquina&s=Block+Car`);
  comprobar((await p.getAttribute('#the-list tr:first-child .row-actions .tnm_plataformas a', 'href')) === `${DESDE_WP}/maquinas/pla-block`, 'las máquinas sincronizadas tienen «Editar en Plataformas»');
  await p.screenshot({ path: `${CAPTURAS}/sync-2-listado.png` });
  await p.goto(`${BASE}/wp-admin/post.php?post=${block.id}&action=edit`);
  await p.waitForSelector('.tnm-sync-aviso');
  comprobar((await p.getAttribute('.tnm-sync-aviso a.button-primary', 'href')) === `${DESDE_WP}/maquinas/pla-block`, 'la ficha lleva a editarla en Plataformas');
  const bloqueadas = await p.evaluate(() => ['#titlediv', '#postdivrich', '#tnm_ficha .inside', '#tnm_galeria .inside', '#tnm_fichas .inside', '#pageparentdiv .inside']
    .filter(sel => { const el = document.querySelector(sel); return !el || getComputedStyle(el).pointerEvents !== 'none'; }));
  comprobar(bloqueadas.length === 0, 'sus cajas quedan bloqueadas', bloqueadas.join(', '));
  await p.screenshot({ path: `${CAPTURAS}/sync-3-editar.png` });
  // Aunque alguien cambie algo por debajo del bloqueo, no se guarda (datos) o se deshace en la siguiente lectura (título)
  await p.evaluate(() => {
    document.querySelector('input[name="tnm[consumo]"]').value = '9 kW';
    document.querySelector('#title').value = 'Block Car tocado en WordPress';
  });
  await p.click('#publish');
  await p.waitForSelector('#message');
  const tocada = await deApi('block-car');
  comprobar(tocada.consumo === '0,3 kW', 'los datos de una máquina sincronizada no se guardan desde aquí', tocada.consumo);
  r = await sincronizarAhora();
  comprobar(/1 actualizadas/.test(r) && (await deApi('block-car')).nombre === 'Block Car', 'lo que se toque aquí vuelve a lo de Plataformas en la siguiente lectura', r);

  /* ---------- aviso de Plataformas ---------- */
  const avisar = (cuerpo, secreto = SECRETO) => fetch(`${BASE}/wp-json/tecnotron/v1/sincronizar`, {
    method: 'POST', headers: { 'content-type': 'application/json', 'x-plataformas-signature': 'sha256=' + createHmac('sha256', secreto).update(cuerpo).digest('hex') }, body: cuerpo
  });
  const cuerpo = JSON.stringify({ event: 'catalog.updated', ids: ['pla-nueva'], at: ahora() });
  comprobar((await avisar(cuerpo, 'otro-secreto')).status === 401, 'un aviso mal firmado se rechaza');
  Object.assign(catalogo[1], { name: 'Máquina de Plataformas renombrada', updated_at: ahora() });
  const aviso = await avisar(cuerpo);
  comprobar(aviso.status === 202, 'un aviso bien firmado se acepta', aviso.status);
  const llego = await esperar(async () => {
    await fetch(`${BASE}/wp-cron.php`).catch(() => {});                 // como una visita cualquiera
    return (await deApi('maquina-de-plataformas'))?.nombre === 'Máquina de Plataformas renombrada';
  });
  comprobar(llego, 'tras el aviso la web se actualiza sola');

  /* ---------- retirar y volver a publicar ---------- */
  const [quitada] = catalogo.splice(1, 1);
  r = await sincronizarAhora();
  comprobar(/1 retiradas/.test(r) && !(await deApi('maquina-de-plataformas')), 'la que desaparece de Plataformas se retira de la web (borrador)', r);
  catalogo[1].web = { publish: true, order: null, featured: false };
  catalogo[1].updated_at = ahora();
  r = await sincronizarAhora();
  comprobar(!!(await deApi('typhoon')), 'al publicarla en Plataformas vuelve a la web', r);

  /* ---------- vista inicial elegida en Plataformas, con la otra si falta la elegida ---------- */
  const vista = async slug => { const m = await deApi(slug); return (/data-vista="(\w+)"/.exec((await (await fetch(`${BASE}/wp-json/tecnotron/v1/maquinas/${m.id}/ficha`)).json()).html) || [])[1]; };
  catalogo[0].web = { ...catalogo[0].web, view: 'images' };
  catalogo[1].web = { ...catalogo[1].web, view: '3d' };
  Object.assign(catalogo[0], { updated_at: ahora() }); Object.assign(catalogo[1], { updated_at: ahora() });
  await sincronizarAhora();
  comprobar((await vista('block-car')) === 'imagenes', 'Plataformas elige la vista inicial de la ficha (Block Car en «Imágenes» aunque tenga 3D)');
  comprobar((await vista('typhoon')) === 'imagenes', 'si se pide «3D» y no hay modelo, la ficha abre en «Imágenes»');

  catalogo[0].web = { ...catalogo[0].web, view: null };
  catalogo[1].web = { ...catalogo[1].web, view: null };
  await sincronizarAhora();
  comprobar((await vista('block-car')) === '3d', 'sin vista elegida, la de Ajustes (3D · AR)');

  /* ---------- Plataformas con ids nuevos (p. ej. su base de datos recreada): se reconocen por la dirección ---------- */
  Object.assign(catalogo[0], { id: 'pla-block-recreada', updated_at: ahora() });
  r = await sincronizarAhora();
  const blocks = (await api()).filter(x => x.slug.startsWith('block-car'));
  comprobar(/0 creadas, 1 actualizadas/.test(r) && blocks.length === 1 && blocks[0].slug === 'block-car', 'si cambian los ids de Plataformas no se duplican las fichas', r);

  /* ---------- dejarlo como estaba ---------- */
  await p.goto(`${BASE}/wp-admin/edit.php?post_type=tn_maquina&post_status=draft`);
  const fila = p.locator('#the-list tr', { hasText: quitada.name });
  if (await fila.count()) {
    await fila.hover();
    await fila.locator('.row-actions .trash a').click();
    await p.waitForSelector('#message');
  }
  comprobar((await p.locator('#the-list tr', { hasText: quitada.name }).count()) === 0, 'la máquina de prueba va a la papelera');
  await p.goto(AJUSTES);
  await p.check('input[name="tnm_plataformas[desconectar]"]');
  await p.click('#submit');
  await p.waitForSelector('#setting-error-settings_updated');
  comprobar((await p.textContent('.tnm-sync-estado')).includes('Sin conectar'), 'al desconectar, las máquinas vuelven a editarse aquí');
  await p.goto(`${BASE}/wp-admin/post.php?post=${block.id}&action=edit`);
  await p.waitForSelector('#tnm_ficha');
  comprobar(!(await p.isVisible('.tnm-sync-aviso')), 'la ficha ya no está bloqueada');
  comprobar(lecturas > 0, 'Plataformas se consultó con la clave', `${lecturas} lecturas`);
} catch (e) {
  comprobar(false, 'la prueba terminó sin excepciones', e.message.split('\n')[0]);
} finally {
  servidor.close();
  await terminar(navegador);
}
