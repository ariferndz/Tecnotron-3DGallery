/* Tecnotron Máquinas — pantalla de edición: panel de contenido, galería, modelo GLB (o OBJ convertido) con vista previa y fichas PDF. */
jQuery(function ($) {
  'use strict';

  /* ---------- panel «Contenido de la ficha»: lleva a cada caja y la abre si estaba plegada ---------- */
  function irA(id) {
    const box = document.getElementById(id); if (!box) return;
    box.classList.remove('closed'); box.hidden = false; box.style.display = '';
    $(box).find('.handlediv').attr('aria-expanded', 'true');
    const y = box.getBoundingClientRect().top + window.scrollY - 50;
    window.scrollTo({ top: y, behavior: 'smooth' });
    box.classList.add('tnm-resaltar'); setTimeout(() => box.classList.remove('tnm-resaltar'), 1600);
  }
  $('[data-tnm-ir]').on('click', function (e) { e.preventDefault(); irA(this.hash.slice(1)); });
  // Desde el listado se llega con #tnm_modelo, #tnm_galeria…
  if (location.hash && /^#(tnm_|postimagediv)/.test(location.hash)) setTimeout(() => irA(location.hash.slice(1)), 300);

  /* ---------- galería (carrusel) ---------- */
  const $gal = $('[data-tnm-galeria]'), $galIn = $('[data-tnm-galeria-input]');
  const syncGal = () => $galIn.val($gal.children('li').map((_, li) => li.dataset.id).get().join(','));
  $gal.sortable({ update: syncGal, tolerance: 'pointer' });
  $gal.on('click', '.tnm-rm', function () { $(this).closest('li').remove(); syncGal(); });
  let galFrame;
  $('[data-tnm-galeria-add]').on('click', function () {
    galFrame = galFrame || wp.media({ title: 'Imágenes del carrusel', button: { text: 'Añadir al carrusel' }, library: { type: 'image' }, multiple: 'add' });
    galFrame.off('select').on('select', function () {
      galFrame.state().get('selection').each(function (att) {
        const a = att.toJSON();
        if ($gal.children(`[data-id="${a.id}"]`).length) return;
        const src = (a.sizes && (a.sizes.thumbnail || a.sizes.medium) || a).url;
        $gal.append(`<li data-id="${a.id}"><img src="${src}" alt=""><button type="button" class="tnm-rm" aria-label="Quitar imagen">×</button></li>`);
      });
      syncGal();
    });
    galFrame.open();
  });

  /* ---------- modelo GLB + vista previa con comprobación de tamaño ---------- */
  const $prev = $('[data-tnm-glb-preview]'), $id = $('[data-tnm-glb-id]'), $url = $('[data-tnm-glb-url]');
  let mvReady = null;
  const loadMV = () => (mvReady = mvReady || import(TNM_ADMIN.mv).then(() => customElements.whenDefined('model-viewer')));
  const dims = () => ['ancho', 'largo', 'alto'].map(k => Number(String($(`[data-tnm-dim="${k}"]`).val() || '').replace(',', '.')));
  function preview(src) {
    const stage = $prev.find('[data-tnm-glb-stage]')[0], check = $prev.find('[data-tnm-glb-check]');
    check.text('').removeClass('tnm-ok tnm-warn');
    if (!src) { stage.innerHTML = '<span>Sin modelo 3D</span>'; return; }
    stage.innerHTML = '<span>Cargando modelo…</span>';
    loadMV().then(() => {
      const mv = document.createElement('model-viewer');
      mv.setAttribute('src', src); mv.setAttribute('camera-controls', ''); mv.setAttribute('auto-rotate', '');
      mv.setAttribute('shadow-intensity', '1'); mv.setAttribute('environment-image', 'neutral'); mv.setAttribute('camera-orbit', '-35deg 72deg auto');
      mv.addEventListener('load', () => {
        const d = mv.getDimensions(), cm = [d.x, d.z, d.y].map(v => Math.round(v * 100)), f = dims();
        const real = f.every(Boolean) && f.every((v, i) => Math.abs(cm[i] - v) / v <= 0.08);
        let txt = `Tamaño del modelo en realidad aumentada: ${cm.join(' × ')} cm (ancho × largo × alto).`;
        if (f.every(Boolean)) txt += real ? ' Coincide con la ficha: se verá a tamaño real.' : ` La ficha dice ${f.join(' × ')} cm: exporta el GLB a esas medidas para que la realidad aumentada sea fiel.`;
        check.text(txt).addClass(real ? 'tnm-ok' : 'tnm-warn');
      });
      mv.addEventListener('error', () => { check.text('No se pudo leer el archivo. ¿Es un .glb válido?').addClass('tnm-warn'); });
      stage.innerHTML = ''; stage.appendChild(mv);
    });
  }
  function setGlb(id, url, name) {
    $id.val(id || ''); if (url !== undefined && !id) $url.val(url);
    $('[data-tnm-glb-name]').text(name || '');
    $('[data-tnm-glb-clear]').prop('hidden', !(id || $url.val()));
    preview(id ? url : $url.val());
  }
  let glbFrame;
  $('[data-tnm-glb-pick]').on('click', function () {
    glbFrame = glbFrame || wp.media({ title: 'Modelo 3D (.glb)', button: { text: 'Usar este modelo' }, multiple: false });
    glbFrame.off('select').on('select', function () {
      const a = glbFrame.state().get('selection').first().toJSON();
      if (!/\.glb$/i.test(a.filename || a.url)) { window.alert('Elige un archivo .glb'); return; }
      $url.val('');
      setGlb(a.id, a.url, a.filename);
    });
    glbFrame.open();
  });
  $('[data-tnm-glb-clear]').on('click', () => { $url.val(''); setGlb('', '', ''); });
  $url.on('change', () => { if ($url.val()) setGlb('', $url.val(), ''); });
  $('[data-tnm-dim]').on('change', () => { const src = $prev.find('model-viewer').attr('src'); if (src) preview(src); });
  if ($prev.data('src')) preview($prev.data('src'));

  /* ---------- convertir un OBJ (con su .mtl y texturas) a GLB y subirlo a Medios ---------- */
  const $objIn = $('[data-tnm-obj-files]'), $objSt = $('[data-tnm-obj-status]');
  const mb = b => (b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toLocaleString('es-ES', { maximumFractionDigits: 1 }) + ' MB');
  const estado = (txt, cls) => $objSt.text(txt).attr('class', 'tnm-obj-status' + (cls ? ' ' + cls : ''));
  $('[data-tnm-obj-pick]').on('click', () => $objIn.trigger('click'));
  $objIn.on('change', async function () {
    const files = [...this.files]; this.value = '';
    if (!files.length) return;
    const obj = files.find(f => /\.obj$/i.test(f.name));
    if (!obj) { estado('Falta el archivo .obj: elige a la vez el .obj, su .mtl y sus texturas.', 'tnm-warn'); return; }
    const $btns = $('[data-tnm-obj-pick],[data-tnm-glb-pick]').prop('disabled', true);
    try {
      estado(`Convirtiendo ${obj.name} a GLB…`);
      const { objAGlb } = await import(TNM_ADMIN.obj);
      const [ancho, largo] = dims();
      const r = await objAGlb(files, { medidaCm: Math.max(ancho || 0, largo || 0), zArriba: $('[data-tnm-obj-z]').is(':checked') });
      if (r.blob.size > TNM_ADMIN.max) throw new Error(`el GLB ocupa ${mb(r.blob.size)} y el servidor admite ${mb(TNM_ADMIN.max)} por archivo. Reduce las texturas o usa «npm run optimizar» (ver README).`);
      const nombre = (obj.name.replace(/\.obj$/i, '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'modelo') + '.glb';
      estado(`Subiendo ${nombre} (${mb(r.blob.size)})…`);
      const res = await fetch(TNM_ADMIN.media, {
        method: 'POST', credentials: 'same-origin', body: r.blob,
        headers: { 'X-WP-Nonce': TNM_ADMIN.nonce, 'Content-Type': 'model/gltf-binary', 'Content-Disposition': `attachment; filename="${nombre}"` },
      });
      const a = await res.json().catch(() => ({}));
      if (!res.ok || !a.id) throw new Error(a.message ? a.message.replace(/<[^>]+>/g, '') : `el servidor respondió ${res.status}.`);
      $url.val('');
      setGlb(a.id, a.source_url, nombre);
      estado([`Convertido y subido: ${nombre}, ${mb(r.blob.size)}, ${r.medidas.join(' × ')} cm. Pulsa «Actualizar» para guardarlo en la máquina.`, ...r.avisos].join(' '), r.avisos.length ? 'tnm-warn' : 'tnm-ok');
    } catch (e) {
      estado('No se pudo convertir: ' + e.message, 'tnm-warn');
    } finally {
      $btns.prop('disabled', false);
    }
  });

  /* ---------- fichas PDF ---------- */
  const $pdfs = $('[data-tnm-pdfs] tbody');
  let n = $pdfs.children().length;
  $('[data-tnm-pdf-add]').on('click', () => { $pdfs.append($('#tmpl-tnm-pdf-row').html().replace(/__i__/g, 'n' + (n++))); });
  $pdfs.on('click', '[data-tnm-pdf-rm]', function () { $(this).closest('tr').remove(); });
  $pdfs.on('click', '[data-tnm-pdf-pick]', function () {
    const $row = $(this).closest('tr');
    const frame = wp.media({ title: 'Ficha PDF', button: { text: 'Usar este PDF' }, library: { type: 'application/pdf' }, multiple: false });
    frame.on('select', () => {
      const a = frame.state().get('selection').first().toJSON();
      $row.find('[data-tnm-pdf-id]').val(a.id); $row.find('[data-tnm-pdf-url]').val('');
      $row.find('[data-tnm-pdf-name]').text(a.filename);
      const $label = $row.find('input[type=text]'); if (!$label.val()) $label.val(a.title);
    });
    frame.open();
  });
  $pdfs.on('input', '[data-tnm-pdf-url]', function () { if (this.value) $(this).closest('tr').find('[data-tnm-pdf-id]').val(''); });
});
