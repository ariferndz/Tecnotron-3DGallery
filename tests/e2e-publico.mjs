// Recorre la parte pública del catálogo como lo haría un cliente, en escritorio y en móvil.
//
//   BASE=http://localhost:8080 node tests/e2e-publico.mjs
//
// Necesita el catálogo de demostración cargado (npm run dev:datos).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { PDFDocument } from 'pdf-lib';
import { BASE, CAPTURAS, abrirNavegador, comprobar, diferencias, estilosCalculados, terminar, vigilar } from './comun.mjs';

const navegador = await abrirNavegador();
const shot = (p, nombre, opciones = {}) => p.screenshot({ path: `${CAPTURAS}/publico-${nombre}.png`, ...opciones });
const visibles = p => p.locator('.tnm-card:not([hidden])').count();

try {
  /* ---------- escritorio: catálogo ---------- */
  const d = vigilar(await navegador.newPage({ viewport: { width: 1440, height: 900 } }), 'escritorio');
  await d.goto(BASE + '/maquinas/');
  await d.waitForSelector('.tnm-card');
  const total = await d.locator('.tnm-card').count();
  comprobar(total === 42, 'el catálogo muestra las 42 máquinas', total);
  await shot(d, '1-catalogo');

  await d.fill('[data-tnm-q]', 'peppa');
  const peppa = await visibles(d);
  comprobar(peppa > 0 && peppa < total, 'el buscador filtra', `«peppa» → ${peppa}`);
  await d.fill('[data-tnm-q]', '');
  await d.click('[data-tnm-cat="carruseles"]');
  const carruseles = await visibles(d);
  comprobar(carruseles > 0 && carruseles < total, 'el filtro de categoría funciona', `carruseles → ${carruseles}`);
  await d.click('[data-tnm-cat="all"]');
  await d.click('[data-tnm-only3d]');
  comprobar((await visibles(d)) === 3, 'el filtro «con 3D» deja las 3 máquinas con modelo', await visibles(d));
  await d.click('[data-tnm-only3d]');

  /* ---------- ventana de ficha ---------- */
  await d.locator('.tnm-card[data-url$="/peppa-bus/"] [data-tnm-open].tnm-pill').click();
  await d.waitForSelector('[data-tnm-modal] [data-tnm-ficha]');
  comprobar(d.url().endsWith('/maquinas/peppa-bus/'), 'abrir la ficha cambia la dirección', d.url());
  const pestanas = await d.$$eval('[data-tnm-modal] [data-tnm-tab]', bs => bs.map(b => `${b.dataset.tnmTab}${b.getAttribute('aria-selected') === 'true' ? '*' : ''}`).join(' '));
  comprobar(pestanas === '3d* imagenes', 'con modelo 3D la ficha abre en «3D · AR», con «Imágenes» al lado', pestanas);
  await d.waitForFunction(() => document.querySelector('[data-tnm-modal] model-viewer')?.loaded, null, { timeout: 60000 });
  await d.waitForTimeout(1200);
  const nota = await d.textContent('[data-tnm-modal] .tnm-stage-note');
  comprobar(/180 × 92 × 145 cm/.test(nota), 'el modelo 3D carga a escala real', nota.trim());
  await d.click('[data-tnm-modal] .tnm-qr-open');
  await d.waitForSelector('.tnm-qr-pop svg');
  comprobar(true, 'se genera el QR del visor');
  await shot(d, '3-ficha-3d-qr');
  await d.click('[data-tnm-modal] [data-tnm-tab="imagenes"]');
  await d.waitForTimeout(500);
  await shot(d, '2-ficha-imagenes');
  await d.click('[data-tnm-modal] [data-tnm-car="1"]');
  // El paso es un desplazamiento suave: en un ordenador lento (CI, con el 3D de la otra pestaña) tarda más de 700 ms
  await d.waitForFunction(() => /^2\s*\/\s*4$/.test(document.querySelector('[data-tnm-modal] [data-tnm-car-count]')?.textContent.trim() || ''), null, { timeout: 5000 }).catch(() => {});
  const cuenta = (await d.textContent('[data-tnm-modal] [data-tnm-car-count]')).replace(/\s/g, '');
  comprobar(cuenta === '2/4', 'en «Imágenes» el carrusel avanza', cuenta);
  await d.click('[data-tnm-modal] [data-tnm-next]');
  await d.waitForTimeout(900);
  comprobar(!d.url().endsWith('/peppa-bus/'), '«siguiente» pasa a otra máquina', d.url());
  await d.keyboard.press('Escape');
  await d.waitForTimeout(400);
  comprobar(d.url().endsWith('/maquinas/'), 'cerrar la ventana vuelve al catálogo', d.url());

  /* ---------- solicitud de presupuesto ---------- */
  await d.click('.tnm-card[data-url$="/diggy/"] [data-tnm-add]');
  await d.click('.tnm-card[data-url$="/block-car/"] [data-tnm-add]');
  comprobar(await d.isVisible('[data-tnm-tray]'), 'la bandeja muestra las máquinas elegidas', (await d.textContent('[data-tnm-tray-txt]')).trim());
  await d.locator('#tnm-contacto').scrollIntoViewIfNeeded();
  await d.waitForTimeout(400);
  await d.click('form[data-tnm-form] [type=submit]');
  const invalidos = await d.locator('.tnm-field.tnm-invalid').count();
  comprobar(invalidos === 5, 'el formulario vacío marca los 5 campos obligatorios', invalidos);
  await d.fill('#tnm-nombre', 'Ana Prueba');
  await d.fill('#tnm-email', 'ana@ejemplo.es');
  await d.fill('#tnm-tel', '600 000 000');
  await d.fill('#tnm-ciudad', 'Madrid');
  await d.fill('#tnm-msg', 'Quiero presupuesto para un centro comercial');
  await d.check('form[data-tnm-form] [name=consentimiento]');
  await d.waitForTimeout(3100); // el formulario rechaza envíos en menos de 3 s (antirrobots)
  await d.click('form[data-tnm-form] [type=submit]');
  await d.waitForSelector('[data-tnm-form-ok]:not([hidden])', { timeout: 20000 });
  comprobar(true, 'la solicitud se envía');
  await shot(d, '4-solicitud-enviada');

  /* ---------- página de una máquina ---------- */
  await d.goto(BASE + '/maquinas/diggy/');
  await d.waitForSelector('.tnm-ficha--pagina');
  const titulo = (await d.textContent('h1.tnm-f-name')).trim();
  comprobar(titulo.includes('Diggy'), 'la página de Diggy tiene su título', titulo);
  comprobar((await d.locator('.tnm-spec').count()) === 8, 'la ficha de Diggy tiene los 8 datos técnicos', await d.locator('.tnm-spec').count());
  comprobar((await d.locator('script[type="application/ld+json"]').count()) === 1, 'incluye datos de producto para Google (JSON-LD)');
  comprobar((await d.inputValue('[data-tnm-maquinas]')) !== '', 'el formulario llega con la máquina preseleccionada');
  const soloImagenes = await d.$$eval('.tnm-ficha--pagina [data-tnm-tab]', bs => bs.map(b => b.dataset.tnmTab).join(' '));
  comprobar(soloImagenes === 'imagenes', 'sin modelo 3D sólo aparece la pestaña «Imágenes»', soloImagenes);
  const icono = await d.$eval('.tnm-dl .tnm-fi', e => Math.round(e.getBoundingClientRect().width));
  comprobar(icono === 40, 'en «Descargas» el icono no se estira', `${icono} px`);
  await shot(d, '5-diggy', { fullPage: true });
  const descarga = d.waitForEvent('download', { timeout: 60000 });
  await d.click('[data-tnm-pdf]');
  const pdf = await descarga;
  const bytes = fs.readFileSync(await pdf.path());
  const paginas = (await PDFDocument.load(bytes)).getPageCount();
  comprobar(pdf.suggestedFilename() === 'ficha-tecnica-diggy.pdf' && paginas === 2, 'la ficha técnica se descarga como PDF de 2 páginas', `${pdf.suggestedFilename()}, ${paginas} páginas, ${Math.round(bytes.length / 1024)} KB`);
  fs.writeFileSync(`${CAPTURAS}/ficha-tecnica-diggy.pdf`, bytes);

  await d.goto(BASE + '/maquinas/grua-de-tren/');
  comprobar((await d.locator('.tnm-dl[href$=".pdf"]').count()) === 2, 'la Grúa de tren ofrece sus 2 fichas PDF');
  await d.goto(BASE + '/maquinas/categoria/carruseles/');
  const enCategoria = await d.locator('.tnm-card').count();
  comprobar(enCategoria === carruseles, 'la página de categoría lista sus máquinas', `${enCategoria} · ${(await d.textContent('.tnm-hero-title')).trim()}`);
  const api = await (await d.request.get(BASE + '/wp-json/tecnotron/v1/maquinas')).json();
  comprobar(Array.isArray(api) && api.length === 42, 'la API pública devuelve las 42 máquinas');
  await d.close();

  /* ---------- con los estilos de un tema agresivo (tests/tema-agresivo.css) el catálogo no cambia ---------- */
  const temaCss = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), 'tema-agresivo.css'), 'utf8');
  const t = vigilar(await navegador.newPage({ viewport: { width: 1280, height: 900 } }), 'tema');
  await t.goto(BASE + '/maquinas/');
  await t.waitForSelector('.tnm-card');
  await t.addStyleTag({ content: '*{transition:none!important;animation:none!important}' });
  for (const [estado, preparar] of [['catálogo', async () => {}], ['ficha abierta', async () => {
    await t.locator('.tnm-card[data-url$="/peppa-bus/"] [data-tnm-open].tnm-pill').click();
    await t.waitForSelector('[data-tnm-modal] [data-tnm-ficha]');
    // Abre en 3D: se mide cuando el modelo ya ha cargado (al cargar aparece la nota «A escala real»)
    await t.waitForFunction(() => document.querySelector('[data-tnm-modal] model-viewer')?.loaded && document.querySelector('[data-tnm-modal] .tnm-stage-note'), null, { timeout: 60000 });
    await t.waitForTimeout(800);
  }]]) {
    await preparar();
    await t.mouse.move(0, 0);
    const sinTema = await estilosCalculados(t);
    const estilo = await t.addStyleTag({ content: temaCss });
    await t.evaluate(() => document.body.classList.add('elementor-kit-7'));
    const conTema = await estilosCalculados(t);
    if (estado === 'catálogo') {
      const alturas = await t.$$eval('.tnm-stats li', lis => lis.map(li => Math.round(li.getBoundingClientRect().height)));
      comprobar(alturas.length > 1 && alturas.every(h => h === alturas[0]), 'las píldoras de datos tienen la misma altura', alturas.join(', '));
    }
    const dif = diferencias(sinTema, conTema);
    comprobar(dif.length === 0, `con el CSS de un tema agresivo no cambia nada (${estado})`, dif.length ? `${dif.length} diferencias:\n    ` + dif.slice(0, 8).join('\n    ') : `${sinTema.length} elementos`);
    await t.screenshot({ path: `${CAPTURAS}/publico-tema-${estado.replace(/\s+/g, '-')}.png` });
    await estilo.evaluate(e => e.remove());
    await t.evaluate(() => document.body.classList.remove('elementor-kit-7'));
  }
  await t.close();

  /* ---------- tema con cabecera fija o superpuesta (dev/mu-plugins simula la de Impreza con ?cabecera=) ---------- */
  const c = vigilar(await navegador.newPage({ viewport: { width: 1280, height: 900 } }), 'cabecera');
  for (const modo of ['fija', 'absoluta']) {
    for (const [ruta, primero] of [['/maquinas/diggy/', '.tnm-crumbs'], ['/maquinas/', '.tnm-hero']]) {
      await c.goto(`${BASE}${ruta}?cabecera=${modo}`);
      await c.waitForLoadState('load');
      await c.waitForTimeout(300);
      const m = await c.evaluate(sel => ({
        cabecera: Math.round(document.querySelector('header.wp-block-template-part').getBoundingClientRect().bottom),
        contenido: Math.round(document.querySelector(sel).getBoundingClientRect().top),
      }), primero);
      comprobar(m.contenido >= m.cabecera, `la cabecera ${modo} del tema no tapa ${ruta}`, `cabecera hasta ${m.cabecera} px, contenido desde ${m.contenido} px`);
    }
  }
  await c.goto(`${BASE}/maquinas/?cabecera=fija`);
  await c.waitForLoadState('load');
  await c.mouse.wheel(0, 1600);
  await c.waitForTimeout(800);
  const barra = await c.evaluate(() => ({
    cabecera: Math.round(document.querySelector('header.wp-block-template-part').getBoundingClientRect().bottom),
    barra: Math.round(document.querySelector('.tnm-toolbar').getBoundingClientRect().top),
  }));
  comprobar(Math.abs(barra.barra - barra.cabecera) <= 1, 'al bajar, la barra de filtros se queda justo debajo de la cabecera fija', `${barra.barra} px / ${barra.cabecera} px`);
  await c.close();

  /* ---------- móvil ---------- */
  const ctx = await navegador.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const m = vigilar(await ctx.newPage(), 'móvil');
  await m.goto(BASE + '/maquinas/');
  await m.waitForSelector('.tnm-card');
  await shot(m, '7-movil-catalogo');
  const ancho = await m.evaluate(() => document.documentElement.scrollWidth);
  comprobar(ancho <= 390, 'en móvil no hay desplazamiento horizontal', `${ancho}px`);
  await m.locator('.tnm-card[data-url$="/bluey-family-car/"] .tnm-pill').tap();
  await m.waitForSelector('[data-tnm-modal] [data-tnm-ficha]');
  await m.waitForTimeout(800);
  await shot(m, '8-movil-ficha');
  await m.goto(BASE + '/maquinas/bluey-family-car/visor/');
  await m.waitForFunction(() => document.querySelector('model-viewer')?.loaded, null, { timeout: 60000 });
  await m.waitForTimeout(1000);
  comprobar(true, 'el visor del QR carga el modelo a pantalla completa');
  await shot(m, '9-movil-visor');
  await ctx.close();
} catch (e) {
  comprobar(false, 'la prueba terminó sin excepciones', e.message.split('\n')[0]);
} finally {
  await terminar(navegador);
}
