/* Tecnotron Máquinas — pantalla de edición: galería, modelo GLB con vista previa y fichas PDF. */
jQuery(function ($) {
  'use strict';

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
