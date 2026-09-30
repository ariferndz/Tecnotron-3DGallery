/*
 * Ficha técnica en PDF, generada en el navegador al pulsar «Ficha técnica» (sin servidor ni diálogo de impresión).
 * «npm run build» lo empaqueta con pdf-lib en tecnotron-maquinas/assets/vendor/ficha-pdf.js.
 *
 *   const blob = await fichaPDF(datos);   // datos: tnm_pdf_datos() en includes/render.php
 *
 * Dos páginas A4 en el estilo de las fichas de fábrica:
 *  1. Portada: fondo de triángulos, franjas diagonales, logo, banda con el nombre, foto grande, detalle en círculo
 *     y pie oscuro con teléfono, correo, web y dirección.
 *  2. Descripción con foto, «Características técnicas» con iconos y dibujo de dimensiones a escala.
 * Todo es vectorial salvo las fotos, el logo y los iconos (que se rasterizan a buena resolución).
 */
import { PDFDocument, StandardFonts, degrees, rgb } from 'pdf-lib';

const W = 595.28;
const H = 841.89;
const OSCURO = rgb(0.086, 0.086, 0.106);
const TINTA = rgb(0.11, 0.11, 0.13);
const GRIS = rgb(0.4, 0.4, 0.45);
const BLANCO = rgb(1, 1, 1);

export async function fichaPDF(d) {
  const doc = await PDFDocument.create();
  const f = {
    normal: await doc.embedFont(StandardFonts.Helvetica),
    negrita: await doc.embedFont(StandardFonts.HelveticaBold),
    cursiva: await doc.embedFont(StandardFonts.HelveticaOblique),
    negritaCursiva: await doc.embedFont(StandardFonts.HelveticaBoldOblique),
  };
  const admitidos = new Set(f.normal.getCharacterSet());
  const limpio = t => limpiar(t, admitidos);
  const marca = hexARgb(d.color || '#7c3aed');
  const semilla = [...(d.nombre || 'x')].reduce((a, c) => (a * 31 + c.charCodeAt(0)) >>> 0, 7);

  doc.setTitle(limpio(`Ficha técnica · ${d.nombre}`));
  doc.setAuthor(limpio(d.empresa.nombre || ''));
  doc.setSubject(limpio(`${d.categoria || ''} ${d.nombre}`.trim()));
  doc.setCreator('Tecnotron Máquinas (WordPress)');
  doc.setLanguage('es-ES');

  // Imágenes: se cargan a la vez; la que falle (otro dominio sin permiso, formato raro…) simplemente no sale.
  const [principal, detalle, segunda, logo] = await Promise.all([
    incrustar(doc, d.imagenes[0]),
    incrustar(doc, d.imagenes[1], { circulo: true }),
    incrustar(doc, d.imagenes[2] || d.imagenes[1] || d.imagenes[0]),
    incrustar(doc, d.empresa.logo, { max: 900, logo: true }),
  ]);
  const iconos = {};
  for (const [nombre, svg] of Object.entries({ ...d.iconos, ...Object.fromEntries(d.specs.map((s, i) => [`spec${i}`, s.icono])) })) {
    iconos[nombre] = await incrustarIcono(doc, svg);
  }

  const ctx = { doc, f, limpio, marca, d, principal, detalle, segunda, logo, iconos };
  portada(ctx, doc.addPage([W, H]), semilla);
  detalles(ctx, doc.addPage([W, H]), semilla + 1);

  return new Blob([await doc.save()], { type: 'application/pdf' });
}

/* ---------------------------------------------------------------- página 1 */

function portada(ctx, p, semilla) {
  const { f, limpio, marca, d, principal, detalle, logo } = ctx;
  fondoTriangulos(p, semilla);

  // Franjas diagonales arriba (color y oscura), como las fichas de fábrica
  poligono(p, [[0, 0], [W, 0], [W, 22], [0, 112]], marca);
  poligono(p, [[0, 112], [W, 22], [W, 30], [0, 150]], OSCURO);
  ponerLogo(ctx, p, { x: 34, y: 24, alto: 46, maxAncho: 220 }, logo);

  // Banda del título con su «cola» oscura inclinada
  const y0 = 182;
  const y1 = 332;
  poligono(p, [[196, y0], [236, y0], [180, y1], [140, y1]], OSCURO);
  poligono(p, [[228, y0], [W, y0], [W, y1], [172, y1]], marca);
  const titulo = ajustarTitulo(limpio(d.nombre.toUpperCase()), f.negrita, W - 262 - 26, [34, 30, 26, 22, 19], 3);
  const sub = limpio((d.categoria || '').toUpperCase());
  const altoSub = sub ? 24 : 0;
  const alto = titulo.lineas.length * titulo.tam * 1.05 + altoSub;
  let y = y0 + (y1 - y0 - alto) / 2;
  for (const linea of titulo.lineas) {
    texto(p, linea, 250, y, { font: f.negrita, size: titulo.tam, color: BLANCO });
    y += titulo.tam * 1.05;
  }
  if (sub) texto(p, sub, 250, y + 6, { font: f.negrita, size: 15, color: OSCURO });

  // Foto grande y detalle en círculo
  const caja = detalle ? { x: 28, y: 352, w: 420, h: 418 } : { x: 40, y: 352, w: W - 80, h: 418 };
  if (principal) {
    const r = encajar(principal, caja, 'abajo');
    p.drawEllipse({ x: r.x + r.w / 2, y: H - (r.y + r.h - 2), xScale: r.w * 0.42, yScale: 9, color: OSCURO, opacity: 0.16 });
    p.drawImage(principal, { x: r.x, y: H - r.y - r.h, width: r.w, height: r.h });
  } else if (d.medidas) {
    dibujoMedidas(p, f, marca, d.medidas, { x: 90, y: 380, w: W - 180, h: 370 });
  } else if (ctx.iconos.categoria) {
    p.drawCircle({ x: W / 2, y: H - 560, size: 110, color: marca, opacity: 0.14 });
    p.drawCircle({ x: W / 2, y: H - 560, size: 80, color: marca });
    p.drawImage(ctx.iconos.categoria, { x: W / 2 - 48, y: H - 560 - 48, width: 96, height: 96 });
  }
  if (detalle) {
    const cx = 468;
    const cy = 438;
    const r = 86;
    p.drawCircle({ x: cx + 3, y: H - cy - 5, size: r + 6, color: OSCURO, opacity: 0.18 });
    p.drawCircle({ x: cx, y: H - cy, size: r + 6, color: BLANCO });
    p.drawImage(detalle, { x: cx - r, y: H - cy - r, width: r * 2, height: r * 2 });
    p.drawCircle({ x: cx, y: H - cy, size: r + 6, borderColor: marca, borderWidth: 2.5 });
  }

  // Pie oscuro con los datos de contacto
  poligono(p, [[0, 786], [W, 786], [W, H], [0, H]], OSCURO);
  poligono(p, [[290, 786], [W, 752], [W, 764], [390, 786]], marca);
  const e = d.empresa;
  const datos = [['phone', e.telefono], ['mail', e.email], ['globe', e.web], ['pin', e.direccion]].filter(([, v]) => v && String(v).trim());
  datos.forEach(([icono, valor], i) => {
    const c = i % 2;
    const r = Math.floor(i / 2);
    const x = 36 + c * 270;
    const yy = 800 + r * 22;
    p.drawCircle({ x: x + 8, y: H - yy - 6, size: 8.5, color: ctx.marca });
    if (ctx.iconos[icono]) p.drawImage(ctx.iconos[icono], { x: x + 3, y: H - yy - 11, width: 10, height: 10 });
    const lineas = String(valor).split(/\s*\n+\s*/).map(l => cortar(limpio(l), f.negrita, 8.6, 230)).filter(Boolean).slice(0, 2);
    const tam = lineas.length > 1 ? 7.4 : 8.6;
    lineas.forEach((l, i) => texto(p, l, x + 22, yy + (lineas.length > 1 ? -2 + i * 9 : 1.5), { font: f.negrita, size: tam, color: BLANCO }));
  });
}

/* ---------------------------------------------------------------- página 2 */

function detalles(ctx, p, semilla) {
  const { f, limpio, marca, d, segunda, logo } = ctx;
  fondoTriangulos(p, semilla);
  poligono(p, [[0, 0], [W, 0], [W, 72], [0, 14]], marca);
  poligono(p, [[0, 14], [W, 72], [W, 96], [0, 19]], OSCURO);
  ponerLogo(ctx, p, { x: W - 34, y: 16, alto: 36, maxAncho: 180, derecha: true }, logo);

  // Título entre comillas, como las fichas de fábrica
  const nombre = ajustarTitulo(limpio(d.nombre.toUpperCase()), f.negrita, W - 100 - 60, [26, 23, 20, 18], 2);
  let y = 124;
  texto(p, '“', 50, y - 8, { font: f.negrita, size: 56, color: marca });
  nombre.lineas.forEach((l, i) => texto(p, l, 94, y + i * nombre.tam * 1.08, { font: f.negrita, size: nombre.tam, color: TINTA }));
  const ult = nombre.lineas[nombre.lineas.length - 1];
  const yUlt = y + (nombre.lineas.length - 1) * nombre.tam * 1.08;
  texto(p, '”', 94 + f.negrita.widthOfTextAtSize(ult, nombre.tam) + 6, yUlt - 6, { font: f.negrita, size: 56, color: marca });
  y += nombre.lineas.length * nombre.tam * 1.08 + 4;
  const sub = [d.categoria, d.codigo ? `Código ${d.codigo}` : ''].filter(Boolean).join('  ·  ');
  if (sub) { texto(p, limpio(sub.toUpperCase()), 94, y, { font: f.negrita, size: 10, color: marca }); y += 18; }

  // Descripción a la izquierda y foto a la derecha
  const top = Math.max(y + 14, 196);
  const fondoDesc = 468;
  const bloques = bloquesDescripcion(d.descripcion, limpio);
  const conFoto = Boolean(segunda);
  const anchoTexto = conFoto ? 262 : W - 140;
  let bajo = top;
  if (bloques.length) {
    bajo = escribirBloques(p, f, marca, bloques, { x: 70, y: top, w: anchoTexto, h: fondoDesc - top });
  }
  if (segunda) {
    const caja = bloques.length ? { x: 344, y: top - 10, w: W - 344 - 36, h: Math.max(170, bajo - top + 10) } : { x: 110, y: top, w: W - 220, h: 230 };
    const r = encajar(segunda, caja, 'centro');
    p.drawImage(segunda, { x: r.x, y: H - r.y - r.h, width: r.w, height: r.h });
    bajo = Math.max(bajo, r.y + r.h);
  }

  // «Características técnicas»: debajo de lo que ocupen la descripción y la foto, dejando sitio a la lista
  const specs = d.specs;
  const yb = Math.round(Math.min(Math.max(bajo + 26, 300), 786 - 52 - specs.length * 34));
  poligono(p, [[58, yb], [430, yb], [418, yb + 34], [58, yb + 34]], marca);
  poligono(p, [[436, yb], [500, yb], [488, yb + 34], [424, yb + 34]], OSCURO);
  texto(p, 'CARACTERÍSTICAS TÉCNICAS', 74, yb + 9, { font: f.negrita, size: 17, color: BLANCO });

  const y0 = yb + 52;
  const alto = Math.min(40, (786 - y0) / Math.max(1, specs.length));
  const anchoSpecs = d.medidas ? 236 : W - 150;
  specs.forEach((s, i) => {
    const yy = y0 + i * alto;
    const rc = Math.min(14, alto / 2 - 3);
    p.drawCircle({ x: 84, y: H - yy - rc, size: rc, color: marca });
    const ic = ctx.iconos[`spec${i}`];
    if (ic) p.drawImage(ic, { x: 84 - rc * 0.62, y: H - yy - rc - rc * 0.62, width: rc * 1.24, height: rc * 1.24 });
    texto(p, cortar(limpio(s.etiqueta.toUpperCase()), f.negrita, 8, anchoSpecs), 108, yy + 2, { font: f.negrita, size: 8, color: marca });
    const valor = limpio(s.valor);
    const tamValor = f.cursiva.widthOfTextAtSize(valor, 10.5) > anchoSpecs ? 9 : 10.5;
    texto(p, cortar(valor, f.cursiva, tamValor, anchoSpecs), 108, yy + 13, { font: f.cursiva, size: tamValor, color: TINTA });
    if (s.nota && alto >= 36) texto(p, limpio(s.nota), 108, yy + 25, { font: f.normal, size: 7, color: GRIS });
  });

  if (d.medidas) dibujoMedidas(p, f, marca, d.medidas, { x: 332, y: y0 + 4, w: W - 332 - 34, h: Math.min(260, 780 - y0) });

  // Pie: código vertical en el borde, esquina de color y aviso de medidas
  poligono(p, [[0, H - 64], [64, H], [0, H]], marca);
  poligono(p, [[0, H - 30], [30, H], [0, H]], OSCURO);
  const lateral = limpio([d.codigo, 'Ficha técnica', d.fecha].filter(Boolean).join(' · '));
  p.drawText(lateral, { x: 26, y: 300, size: 7, font: f.normal, color: GRIS, rotate: degrees(90) });
  p.drawLine({ start: { x: 70, y: H - 800 }, end: { x: W - 36, y: H - 800 }, thickness: 0.6, color: GRIS, opacity: 0.5 });
  const web = limpio(d.url.replace(/^https?:\/\//, '').replace(/\/$/, ''));
  texto(p, cortar(web, f.normal, 7.5, 260), 70, 808, { font: f.normal, size: 7.5, color: GRIS });
  const aviso = 'Medidas orientativas; confirmar con el equipo comercial.';
  texto(p, aviso, W - 36 - f.normal.widthOfTextAtSize(aviso, 7.5), 808, { font: f.normal, size: 7.5, color: GRIS });
}

/* ---------------------------------------------------------------- dibujo de dimensiones */

function dibujoMedidas(p, f, marca, [ancho, largo, alto], caja) {
  const k = 0.5;
  const cos = Math.cos(Math.PI / 6);
  const sin = Math.sin(Math.PI / 6);
  const persona = 175;
  const hueco = 26;
  const anchoTotal = ancho + largo * k * cos + hueco + 46;
  const altoTotal = Math.max(alto + largo * k * sin, persona);
  const s = Math.min((caja.w - 28) / anchoTotal, (caja.h - 44) / altoTotal);
  const x0 = caja.x + 22;
  const suelo = caja.y + caja.h - 30;
  const dx = largo * k * cos * s;
  const dy = largo * k * sin * s;
  const aw = ancho * s;
  const ah = alto * s;
  const pt = (x, y) => `${x.toFixed(2)} ${y.toFixed(2)}`;

  // suelo
  p.drawLine({ start: { x: caja.x, y: H - suelo }, end: { x: caja.x + caja.w, y: H - suelo }, thickness: 0.8, color: GRIS, opacity: 0.45 });
  // caras: lateral, superior y frontal
  camino(p, `M${pt(x0 + aw, suelo)} L${pt(x0 + aw + dx, suelo - dy)} L${pt(x0 + aw + dx, suelo - dy - ah)} L${pt(x0 + aw, suelo - ah)} Z`, { color: marca, opacity: 0.32, borde: marca });
  camino(p, `M${pt(x0, suelo - ah)} L${pt(x0 + aw, suelo - ah)} L${pt(x0 + aw + dx, suelo - ah - dy)} L${pt(x0 + dx, suelo - ah - dy)} Z`, { color: marca, opacity: 0.2, borde: marca });
  camino(p, `M${pt(x0, suelo)} L${pt(x0 + aw, suelo)} L${pt(x0 + aw, suelo - ah)} L${pt(x0, suelo - ah)} Z`, { color: marca, opacity: 0.12, borde: marca });
  // aristas ocultas
  p.drawLine({ start: { x: x0 + dx, y: H - (suelo - dy) }, end: { x: x0 + dx, y: H - (suelo - dy - ah) }, thickness: 0.6, color: marca, opacity: 0.5, dashArray: [3, 2] });
  p.drawLine({ start: { x: x0, y: H - suelo }, end: { x: x0 + dx, y: H - (suelo - dy) }, thickness: 0.6, color: marca, opacity: 0.5, dashArray: [3, 2] });
  p.drawLine({ start: { x: x0 + dx, y: H - (suelo - dy) }, end: { x: x0 + aw + dx, y: H - (suelo - dy) }, thickness: 0.6, color: marca, opacity: 0.5, dashArray: [3, 2] });

  const num = v => `${String(Math.round(v * 10) / 10).replace('.', ',')} cm`;
  // ancho (debajo)
  cota([x0, suelo + 10], [x0 + aw, suelo + 10]);
  centrado(p, num(ancho), x0 + aw / 2, suelo + 14, f.negrita, 9, TINTA);
  centrado(p, 'ANCHO', x0 + aw / 2, suelo + 24, f.normal, 6.5, GRIS);
  // alto (izquierda, en vertical)
  cota([x0 - 10, suelo], [x0 - 10, suelo - ah]);
  p.drawText(num(alto), { x: x0 - 14, y: H - (suelo - ah / 2) - f.negrita.widthOfTextAtSize(num(alto), 9) / 2, size: 9, font: f.negrita, color: TINTA, rotate: degrees(90) });
  p.drawText('ALTO', { x: x0 - 24, y: H - (suelo - ah / 2) - f.normal.widthOfTextAtSize('ALTO', 6.5) / 2, size: 6.5, font: f.normal, color: GRIS, rotate: degrees(90) });
  // largo (en diagonal, junto a la arista inferior derecha)
  cota([x0 + aw + 8, suelo + 2], [x0 + aw + dx + 8, suelo - dy + 2]);
  const tl = f.negrita.widthOfTextAtSize(num(largo), 9);
  const mx = x0 + aw + dx / 2 + 8 + 0.5 * 13 - cos * (tl / 2);
  const my = suelo - dy / 2 + 2 + 0.866 * 13 + sin * (tl / 2);
  p.drawText(num(largo), { x: mx, y: H - my, size: 9, font: f.negrita, color: TINTA, rotate: degrees(30) });
  const te = f.normal.widthOfTextAtSize('LARGO', 6.5);
  p.drawText('LARGO', { x: mx + 0.5 * 9 + cos * ((tl - te) / 2), y: H - (my + 0.866 * 9 - sin * ((tl - te) / 2)), size: 6.5, font: f.normal, color: GRIS, rotate: degrees(30) });
  // persona de 1,75 m para dar escala
  const px = x0 + aw + dx + hueco * s + 23 * s;
  const ph = persona * s;
  const gris = rgb(0.55, 0.55, 0.6);
  p.drawCircle({ x: px, y: H - (suelo - ph + 11 * s), size: 11 * s, color: gris });
  p.drawRectangle({ x: px - 17 * s, y: H - (suelo - ph + 24 * s) - 70 * s, width: 34 * s, height: 70 * s, color: gris });
  p.drawRectangle({ x: px - 13 * s, y: H - suelo, width: 11 * s, height: ph - 24 * s - 70 * s, color: gris });
  p.drawRectangle({ x: px + 2 * s, y: H - suelo, width: 11 * s, height: ph - 24 * s - 70 * s, color: gris });
  centrado(p, '1,75 m', px, suelo + 14, f.normal, 7, GRIS);

  function cota([ax, ay], [bx, by]) {
    p.drawLine({ start: { x: ax, y: H - ay }, end: { x: bx, y: H - by }, thickness: 0.7, color: TINTA, opacity: 0.8 });
    const ang = Math.atan2(by - ay, bx - ax) + Math.PI / 2;
    for (const [x, y] of [[ax, ay], [bx, by]]) {
      p.drawLine({ start: { x: x - Math.cos(ang) * 3, y: H - (y - Math.sin(ang) * 3) }, end: { x: x + Math.cos(ang) * 3, y: H - (y + Math.sin(ang) * 3) }, thickness: 0.7, color: TINTA, opacity: 0.8 });
    }
  }
}

/* ---------------------------------------------------------------- texto */

function texto(p, t, x, yTop, { font, size, color, opacity = 1 }) {
  if (!t) return;
  p.drawText(t, { x, y: H - yTop - size * 0.76, size, font, color, opacity });
}

function centrado(p, t, cx, yTop, font, size, color) {
  texto(p, t, cx - font.widthOfTextAtSize(t, size) / 2, yTop, { font, size, color });
}

/** Parte un texto en líneas de un ancho dado. */
function partir(t, font, size, ancho) {
  const lineas = [];
  let actual = '';
  for (const palabra of t.split(/\s+/).filter(Boolean)) {
    const prueba = actual ? `${actual} ${palabra}` : palabra;
    if (actual && font.widthOfTextAtSize(prueba, size) > ancho) {
      lineas.push(actual);
      actual = palabra;
    } else {
      actual = prueba;
    }
  }
  if (actual) lineas.push(actual);
  return lineas;
}

/** El tamaño más grande con el que el título cabe en el máximo de líneas. */
function ajustarTitulo(t, font, ancho, tamanos, maxLineas) {
  for (const tam of tamanos) {
    const lineas = partir(t, font, tam, ancho);
    if (lineas.length <= maxLineas && lineas.every(l => font.widthOfTextAtSize(l, tam) <= ancho)) return { tam, lineas };
  }
  const tam = tamanos[tamanos.length - 1];
  return { tam, lineas: partir(t, font, tam, ancho).slice(0, maxLineas) };
}

function cortar(t, font, size, ancho) {
  if (font.widthOfTextAtSize(t, size) <= ancho) return t;
  let s = t;
  while (s.length > 1 && font.widthOfTextAtSize(`${s}…`, size) > ancho) s = s.slice(0, -1);
  return `${s.trimEnd()}…`;
}

/** Descripción HTML → bloques de texto con negritas y cursivas: [{ tipo: 'p'|'li', trozos: [{ t, b, i }] }] */
function bloquesDescripcion(html, limpio) {
  if (!html) return [];
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
  const bloques = [];
  const recoger = (nodo, estilo, trozos) => {
    for (const n of nodo.childNodes) {
      if (n.nodeType === 3) {
        const t = limpio(n.textContent.replace(/\s+/g, ' '));
        if (t.trim()) trozos.push({ t, ...estilo });
        else if (t && trozos.length) trozos.push({ t: ' ', ...estilo });
      } else if (n.nodeType === 1) {
        const tag = n.tagName;
        if (tag === 'BR') { trozos.push({ salto: true }); continue; }
        recoger(n, { b: estilo.b || tag === 'STRONG' || tag === 'B' || /^H\d$/.test(tag), i: estilo.i || tag === 'EM' || tag === 'I' }, trozos);
      }
    }
    return trozos;
  };
  const visitar = nodo => {
    for (const n of nodo.children) {
      if (n.tagName === 'UL' || n.tagName === 'OL') {
        for (const li of n.children) bloques.push({ tipo: 'li', trozos: recoger(li, {}, []) });
      } else if (['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE'].includes(n.tagName)) {
        bloques.push({ tipo: 'p', trozos: recoger(n, { b: /^H\d$/.test(n.tagName) }, []) });
      } else if (n.tagName === 'DIV') {
        visitar(n);
      } else {
        bloques.push({ tipo: 'p', trozos: recoger(n, {}, []) });
      }
    }
  };
  visitar(doc.body.firstElementChild);
  return bloques.filter(b => b.trozos.some(t => t.t && t.t.trim()));
}

/** Escribe los bloques en la caja; si no caben, reduce la letra y, como último recurso, corta con «…». */
function escribirBloques(p, f, marca, bloques, caja) {
  const fuente = t => (t.b && t.i ? f.negritaCursiva : t.b ? f.negrita : t.i ? f.cursiva : f.normal);
  const maquetar = tam => {
    const interlinea = tam * 1.38;
    const lineas = [];
    for (const [n, bloque] of bloques.entries()) {
      const sangria = bloque.tipo === 'li' ? 13 : 0;
      const ancho = caja.w - sangria;
      let linea = [];
      let usado = 0;
      const cerrar = () => { lineas.push({ trozos: linea, sangria, vineta: bloque.tipo === 'li' && !lineas.some(l => l.bloque === n), bloque: n }); linea = []; usado = 0; };
      for (const trozo of bloque.trozos) {
        if (trozo.salto) { cerrar(); continue; }
        const font = fuente(trozo);
        for (const pieza of trozo.t.split(/(\s+)/)) {
          if (!pieza) continue;
          const esp = /^\s+$/.test(pieza);
          if (esp && !linea.length) continue;
          const w = font.widthOfTextAtSize(esp ? ' ' : pieza, tam);
          if (!esp && usado + w > ancho && linea.length) {
            while (linea.length && /^\s+$/.test(linea[linea.length - 1].t)) usado -= linea.pop().w;
            cerrar();
          }
          linea.push({ t: esp ? ' ' : pieza, font, w });
          usado += w;
        }
      }
      if (linea.length) cerrar();
      lineas.push({ hueco: true, bloque: n });
    }
    if (lineas.length && lineas[lineas.length - 1].hueco) lineas.pop();
    const alto = lineas.reduce((a, l) => a + (l.hueco ? interlinea * 0.45 : interlinea), 0);
    return { lineas, alto, interlinea };
  };
  let tam = 10.5;
  let m = maquetar(tam);
  while (m.alto > caja.h && tam > 8.5) { tam -= 0.5; m = maquetar(tam); }
  let y = caja.y;
  for (const [i, l] of m.lineas.entries()) {
    const paso = l.hueco ? m.interlinea * 0.45 : m.interlinea;
    if (y + paso > caja.y + caja.h) {
      // No cabe todo: «…» al final de la última línea escrita
      const prev = m.lineas.slice(0, i).reverse().find(x => !x.hueco);
      if (prev) texto(p, '…', caja.x + prev.sangria + prev.trozos.reduce((a, t) => a + t.w, 0) + 1, y - m.interlinea, { font: f.normal, size: tam, color: TINTA });
      return y;
    }
    if (!l.hueco) {
      if (l.vineta) p.drawCircle({ x: caja.x + 4, y: H - (y + tam * 0.45), size: 2.4, color: marca });
      let x = caja.x + l.sangria;
      for (const t of l.trozos) {
        texto(p, t.t, x, y, { font: t.font, size: tam, color: TINTA });
        x += t.w;
      }
    }
    y += paso;
  }
  return y;
}

/** Sólo caracteres que la fuente PDF sabe dibujar (WinAnsi: español incluido). */
function limpiar(t, admitidos) {
  const cambios = { ' ': ' ', ' ': ' ', ' ': ' ', '‑': '-', '−': '-', '→': '->', '←': '<-', '≈': '~', '≤': '<=', '≥': '>=', '✓': '·', '✔': '·', '●': '•' };
  let out = '';
  for (const c of String(t || '')) {
    if (admitidos.has(c.codePointAt(0))) out += c;
    else if (cambios[c]) out += cambios[c];
    else {
      const base = c.normalize('NFD')[0];
      if (base && admitidos.has(base.codePointAt(0))) out += base;
    }
  }
  return out;
}

/* ---------------------------------------------------------------- formas */

function poligono(p, puntos, color, opacity = 1) {
  camino(p, `M${puntos.map(([x, y]) => `${x.toFixed(2)} ${y.toFixed(2)}`).join(' L')} Z`, { color, opacity });
}

function camino(p, d, { color, opacity = 1, borde, grosor = 1 }) {
  p.drawSvgPath(d, { x: 0, y: H, color, opacity, ...(borde ? { borderColor: borde, borderWidth: grosor, borderOpacity: 1 } : {}) });
}

/** Fondo gris claro de triángulos (siempre el mismo para una misma máquina). */
function fondoTriangulos(p, semilla) {
  const azar = mulberry32(semilla);
  p.drawRectangle({ x: 0, y: 0, width: W, height: H, color: rgb(0.93, 0.93, 0.945) });
  const cols = 6;
  const filas = 9;
  const cw = W / cols;
  const ch = H / filas;
  const punto = (c, f) => [c === 0 ? -2 : c === cols ? W + 2 : c * cw + (azar() - 0.5) * cw * 0.7, f === 0 ? -2 : f === filas ? H + 2 : f * ch + (azar() - 0.5) * ch * 0.7];
  const pts = Array.from({ length: filas + 1 }, (_, fi) => Array.from({ length: cols + 1 }, (_, c) => punto(c, fi)));
  for (let fi = 0; fi < filas; fi++) {
    for (let c = 0; c < cols; c++) {
      const [a, b, cc, dd] = [pts[fi][c], pts[fi][c + 1], pts[fi + 1][c + 1], pts[fi + 1][c]];
      const tris = azar() > 0.5 ? [[a, b, cc], [a, cc, dd]] : [[a, b, dd], [b, cc, dd]];
      for (const t of tris) {
        const g = 0.895 + azar() * 0.085;
        const color = rgb(g, g, g + 0.01);
        p.drawSvgPath(`M${t.map(([x, y]) => `${x.toFixed(1)} ${y.toFixed(1)}`).join(' L')} Z`, { x: 0, y: H, color, borderColor: color, borderWidth: 0.8 });
      }
    }
  }
}

function mulberry32(a) {
  return () => {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function hexARgb(hex) {
  const h = String(hex).replace('#', '');
  const n = parseInt(h.length === 3 ? h.replace(/./g, c => c + c) : h, 16);
  return Number.isNaN(n) ? rgb(0.49, 0.23, 0.93) : rgb(((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255);
}

/* ---------------------------------------------------------------- imágenes */

function rectRedondeado(x, y, w, h, r) {
  const f = v => v.toFixed(2);
  return `M${f(x + r)} ${f(y)} H${f(x + w - r)} A${r} ${r} 0 0 1 ${f(x + w)} ${f(y + r)} V${f(y + h - r)} A${r} ${r} 0 0 1 ${f(x + w - r)} ${f(y + h)} H${f(x + r)} A${r} ${r} 0 0 1 ${f(x)} ${f(y + h - r)} V${f(y + r)} A${r} ${r} 0 0 1 ${f(x + r)} ${f(y)} Z`;
}

/** Rectángulo donde cabe la imagen entera dentro de la caja. */
function encajar(img, caja, alinear) {
  const e = Math.min(caja.w / img.width, caja.h / img.height);
  const w = img.width * e;
  const h = img.height * e;
  return { x: caja.x + (caja.w - w) / 2, y: alinear === 'abajo' ? caja.y + caja.h - h : caja.y + (caja.h - h) / 2, w, h };
}

function ponerLogo(ctx, p, { x, y, alto, maxAncho, derecha = false }, logo) {
  const { f, limpio, d } = ctx;
  if (logo) {
    const e = Math.min(alto / logo.height, maxAncho / logo.width);
    const w = logo.width * e;
    const h = logo.height * e;
    const px = derecha ? x - w : x;
    // Placa blanca de esquinas redondeadas para logos oscuros o de color; un logo blanco va directo sobre la franja
    if (!logo.tnmClaro) camino(p, rectRedondeado(px - 12, y - 9, w + 24, h + 18, 7), { color: BLANCO, opacity: 0.97 });
    p.drawImage(logo, { x: px, y: H - y - h, width: w, height: h });
  } else {
    const t = limpio((d.empresa.nombre || '').toUpperCase());
    const size = Math.min(22, alto * 0.55);
    const w = f.negrita.widthOfTextAtSize(t, size);
    texto(p, t, derecha ? x - w : x, y + (alto - size) / 2, { font: f.negrita, size, color: BLANCO });
  }
}

/** Descarga una imagen y la prepara para el PDF (JPEG si es opaca, PNG si tiene transparencia o es un círculo). */
async function incrustar(doc, url, { circulo = false, max = 1600, logo = false } = {}) {
  if (!url) return null;
  try {
    const img = await cargar(url);
    const iw = img.naturalWidth || img.width;
    const ih = img.naturalHeight || img.height;
    const c = document.createElement('canvas');
    const g = c.getContext('2d');
    if (circulo) {
      const lado = 520;
      c.width = c.height = lado;
      g.beginPath();
      g.arc(lado / 2, lado / 2, lado / 2, 0, Math.PI * 2);
      g.clip();
      const transparente = tieneTransparencia(img, iw, ih);
      g.fillStyle = transparente ? '#e9e9ee' : '#fff';
      g.fillRect(0, 0, lado, lado);
      // Fotos: llenan el círculo. Renders con fondo transparente: enteros y un poco más grandes que el hueco.
      const e = transparente ? Math.max(lado / iw, lado / ih) * 1.25 : Math.max(lado / iw, lado / ih);
      g.drawImage(img, (lado - iw * e) / 2, (lado - ih * e) / 2, iw * e, ih * e);
      return doc.embedPng(await aBytes(c, 'image/png'));
    }
    const e = Math.min(1, max / Math.max(iw, ih));
    c.width = Math.round(iw * e);
    c.height = Math.round(ih * e);
    g.drawImage(img, 0, 0, c.width, c.height);
    const png = logo || tieneTransparencia(img, iw, ih);
    const bytes = await aBytes(c, png ? 'image/png' : 'image/jpeg');
    const emb = png ? await doc.embedPng(bytes) : await doc.embedJpg(bytes);
    if (logo) emb.tnmClaro = esClaro(c);
    return emb;
  } catch (e) {
    console.warn('Ficha PDF: no se pudo usar la imagen', url, e);
    return null;
  }
}

/** Icono SVG de trazo en blanco → PNG. */
async function incrustarIcono(doc, interior) {
  if (!interior) return null;
  try {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="96" height="96" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${interior}</svg>`;
    const img = await cargar(`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`);
    const c = document.createElement('canvas');
    c.width = c.height = 96;
    c.getContext('2d').drawImage(img, 0, 0, 96, 96);
    return doc.embedPng(await aBytes(c, 'image/png'));
  } catch {
    return null;
  }
}

async function cargar(url) {
  const img = new Image();
  if (!url.startsWith('data:')) {
    // Se pide como blob: así el lienzo no queda «contaminado» y se puede exportar (mismo dominio o con CORS).
    let r;
    try {
      r = await fetch(url, { credentials: 'same-origin' });
    } catch (e) {
      // Otro dominio sin permiso (p. ej. www.tecnotron.es visto desde tecnotron.es): el mismo archivo en este dominio
      const u = new URL(url, location.href);
      if (u.origin === location.origin) throw e;
      r = await fetch(location.origin + u.pathname + u.search, { credentials: 'same-origin' });
    }
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    url = URL.createObjectURL(await r.blob());
  }
  img.src = url;
  await img.decode();
  return img;
}

function aBytes(canvas, tipo) {
  return new Promise((ok, ko) => canvas.toBlob(b => (b ? b.arrayBuffer().then(buf => ok(new Uint8Array(buf))) : ko(new Error('toBlob'))), tipo, 0.9));
}

/** ¿El logo es claro (blanco o casi)? Se mira la luminosidad media de sus píxeles visibles. */
function esClaro(canvas) {
  try {
    const w = Math.min(200, canvas.width);
    const h = Math.max(1, Math.round((w * canvas.height) / canvas.width));
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    const g = c.getContext('2d', { willReadFrequently: true });
    g.drawImage(canvas, 0, 0, w, h);
    const px = g.getImageData(0, 0, w, h).data;
    let suma = 0;
    let n = 0;
    for (let i = 0; i < px.length; i += 4) {
      if (px[i + 3] < 128) continue;
      suma += (0.2126 * px[i] + 0.7152 * px[i + 1] + 0.0722 * px[i + 2]) / 255;
      n++;
    }
    return n > 0 && suma / n > 0.8;
  } catch {
    return false;
  }
}

function tieneTransparencia(img, iw, ih) {
  try {
    const w = Math.min(160, iw);
    const h = Math.max(1, Math.round((w * ih) / iw));
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    const g = c.getContext('2d', { willReadFrequently: true });
    g.drawImage(img, 0, 0, w, h);
    const px = g.getImageData(0, 0, w, h).data;
    for (let i = 3; i < px.length; i += 4) if (px[i] < 250) return true;
    return false;
  } catch {
    return true;
  }
}
