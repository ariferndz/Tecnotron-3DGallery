/* Tecnotron Máquinas — catálogo (filtros, ventana de ficha), ficha (carrusel, 3D/AR, QR, impresión)
   y solicitud de presupuesto. Sin dependencias: el visor 3D (model-viewer) y el QR se cargan al usarlos. */
(() => {
  'use strict';
  const T = window.TNM || {};
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const icon = n => `<svg class="tnm-ic" viewBox="0 0 24 24" aria-hidden="true">${(T.icons || {})[n] || ''}</svg>`;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const norm = s => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const fmt = n => Number(n).toLocaleString('es-ES', { maximumFractionDigits: 1 });

  function toast(msg) {
    let t = document.querySelector('.tnm-toast');
    if (!t) { t = document.createElement('div'); t.className = 'tnm-toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('tnm-show');
    clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('tnm-show'), 2400);
  }

  /* ---------- solicitud de presupuesto (se recuerda en este navegador) ---------- */
  const KEY = 'tnm-solicitud';
  const sel = new Map((() => { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch { return []; } })());
  const saveSel = () => { try { localStorage.setItem(KEY, JSON.stringify([...sel])); } catch { /* sin almacenamiento */ } };
  function toggleSel(id, title, force) {
    id = String(id);
    const on = force ?? !sel.has(id);
    if (on) sel.set(id, title || sel.get(id) || ''); else sel.delete(id);
    saveSel(); syncSel();
    return on;
  }
  function syncSel() {
    const names = [...sel.values()].filter(Boolean);
    $$('[data-tnm-add]').forEach(b => {
      const on = sel.has(b.dataset.tnmAdd), lbl = b.querySelector('.tnm-lbl');
      b.setAttribute('aria-pressed', String(on));
      b.title = on ? 'En tu solicitud' : 'Añadir a mi solicitud';
      if (lbl) { b.innerHTML = `${icon(on ? 'check' : 'plus')}<span class="tnm-lbl">${on ? 'En tu solicitud' : 'Añadir a mi solicitud'}</span>`; }
      else { b.innerHTML = icon(on ? 'check' : 'plus'); b.setAttribute('aria-label', `${on ? 'Quitar de' : 'Añadir a'} mi solicitud: ${b.dataset.title || ''}`); }
    });
    $$('[data-tnm-sel]').forEach(box => {
      box.innerHTML = sel.size ? [...sel].map(([id, t]) => `<span class="tnm-tag">${esc(t)}<button type="button" data-tnm-unsel="${esc(id)}" aria-label="Quitar ${esc(t)}">${icon('x')}</button></span>`).join('')
        : '<span class="tnm-muted">Ninguna todavía. Añádelas con el botón + de cada máquina.</span>';
    });
    $$('[data-tnm-maquinas]').forEach(i => { i.value = [...sel.keys()].join(','); });
    // Formulario externo (Contact Form 7, WPForms…): campo «maquinas» con los nombres
    $$('.tnm-form--externo input[name="maquinas"], .tnm-form--externo textarea[name="maquinas"]').forEach(i => { i.value = names.join(', '); });
    $$('[data-tnm-tray]').forEach(tray => {
      tray.hidden = !sel.size || document.body.classList.contains('tnm-at-contact');
      tray.querySelector('[data-tnm-tray-n]').textContent = sel.size;
      tray.querySelector('[data-tnm-tray-txt]').innerHTML = `<b>${sel.size === 1 ? '1 máquina' : `${sel.size} máquinas`}</b> · ${esc(names.join(', '))}`;
    });
  }

  /* ---------- catálogo: filtros y orden sin recargar ---------- */
  function initCatalog(root) {
    const grid = root.querySelector('[data-tnm-grid]'), cards = $$('.tnm-card', grid);
    const st = { q: '', cat: 'all', only3d: false, sort: 'orden' };
    const count = root.querySelector('[data-tnm-count]'), empty = root.querySelector('[data-tnm-empty]');
    function apply() {
      const q = norm(st.q.trim());
      const vis = cards.filter(c => (st.cat === 'all' || c.dataset.cat === st.cat) && (!st.only3d || c.dataset['3d'] === '1') && (!q || c.dataset.name.includes(q)));
      const area = c => Number(c.dataset.area) || Infinity;
      const by = {
        orden: (a, b) => a.dataset.pos - b.dataset.pos,
        az: (a, b) => a.dataset.title.localeCompare(b.dataset.title, 'es'),
        small: (a, b) => area(a) - area(b),
        big: (a, b) => (Number(b.dataset.area) || 0) - (Number(a.dataset.area) || 0),
      }[st.sort];
      [...cards].sort(by).forEach(c => grid.insertBefore(c, empty));
      cards.forEach(c => { c.hidden = !vis.includes(c); });
      empty.hidden = vis.length > 0;
      count.innerHTML = vis.length === cards.length ? `<b>${cards.length}</b> máquinas` : `<b>${vis.length}</b> de ${cards.length} máquinas`;
      $$('[data-tnm-cat]', root).forEach(b => b.setAttribute('aria-pressed', String(b.dataset.tnmCat === st.cat)));
      const o3 = root.querySelector('[data-tnm-only3d]'); if (o3) o3.setAttribute('aria-pressed', String(st.only3d));
    }
    root.querySelector('[data-tnm-q]')?.addEventListener('input', e => { st.q = e.target.value; apply(); });
    root.querySelector('[data-tnm-sort]')?.addEventListener('change', e => { st.sort = e.target.value; apply(); });
    root.querySelector('[data-tnm-only3d]')?.addEventListener('click', () => { st.only3d = !st.only3d; apply(); });
    root.addEventListener('click', e => { const b = e.target.closest('[data-tnm-cat]'); if (b) { st.cat = b.dataset.tnmCat; apply(); } });
    initModal(root, () => cards.filter(c => !c.hidden));
  }

  /* ---------- ventana de ficha: se abre sin salir del catálogo, pero la dirección es la de la máquina ---------- */
  function initModal(root, visibleCards) {
    const dlg = root.querySelector('[data-tnm-modal]'); if (!dlg || !T.rest) return;
    const body = dlg.querySelector('[data-tnm-modal-body]'), crumb = dlg.querySelector('[data-tnm-crumb]');
    const cache = new Map();
    let current = null, pushed = false, cerrada = true;
    const base = location.pathname + location.search;

    // Deja la página como estaba (dirección, título, visor 3D). Se llama en cuanto se pide cerrar: el evento «close»
    // del navegador puede llegar más de un segundo después si el hilo principal está ocupado dibujando el 3D.
    function alCerrar() {
      if (cerrada) return;
      cerrada = true;
      body.innerHTML = '';                             // libera el visor 3D
      current = null;
      document.title = dlg.dataset.title || document.title;
      if (history.state && history.state.tnm) { if (pushed) history.back(); else history.replaceState(null, '', base); }
      pushed = false;
    }
    function cerrar() { alCerrar(); if (dlg.open) dlg.close(); }

    async function open(card, { push = true } = {}) {
      const id = card.dataset.id;
      current = card;
      cerrada = false;
      crumb.textContent = `Productos · ${card.querySelector('.tnm-card-cat')?.textContent || ''}`;
      body.innerHTML = '<div class="tnm-loading">Cargando ficha…</div>';
      if (!dlg.open) dlg.showModal();
      if (push) { history.pushState({ tnm: id }, '', card.dataset.url); pushed = true; }
      else history.replaceState({ tnm: id }, '', card.dataset.url);
      try {
        if (!cache.has(id)) {
          const r = await fetch(`${T.rest}maquinas/${id}/ficha`, { headers: { Accept: 'application/json' } });
          if (!r.ok) throw new Error(r.status);
          cache.set(id, (await r.json()).html);
        }
        if (current !== card) return;
        body.innerHTML = cache.get(id);
        body.scrollTop = 0;
        const art = body.querySelector('[data-tnm-ficha]');
        hydrateFicha(art, { modal: true });
        art.querySelector('.tnm-f-name')?.focus({ preventScroll: true });
        document.title = `${card.dataset.title} · ${T.site || ''}`;
      } catch { location.href = card.dataset.url; }   // sin API: se abre la página de la máquina
    }
    function neighbour(step) {
      const list = visibleCards(), i = list.indexOf(current);
      return list.length ? list[(i + step + list.length) % list.length] : null;
    }
    root.addEventListener('click', e => {
      const a = e.target.closest('[data-tnm-open]');
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;   // abrir en pestaña nueva sigue funcionando
      e.preventDefault();
      open(a.closest('.tnm-card'));
    });
    dlg.querySelector('[data-tnm-close]').addEventListener('click', cerrar);
    dlg.querySelector('[data-tnm-prev]').addEventListener('click', () => { const c = neighbour(-1); if (c) open(c, { push: false }); });
    dlg.querySelector('[data-tnm-next]').addEventListener('click', () => { const c = neighbour(1); if (c) open(c, { push: false }); });
    dlg.addEventListener('click', e => { if (e.target === dlg) cerrar(); });
    dlg.addEventListener('keydown', e => {
      if (e.target.closest('input, textarea, select, model-viewer, .tnm-slides')) return;
      if (e.key === 'ArrowLeft') dlg.querySelector('[data-tnm-prev]').click();
      if (e.key === 'ArrowRight') dlg.querySelector('[data-tnm-next]').click();
    });
    dlg.addEventListener('cancel', alCerrar);          // Escape
    dlg.addEventListener('close', alCerrar);
    dlg.dataset.title = document.title;
    window.addEventListener('popstate', e => {
      const id = e.state && e.state.tnm;
      const card = id && root.querySelector(`.tnm-card[data-id="${CSS.escape(String(id))}"]`);
      if (card) open(card, { push: false });
      else if (dlg.open) { pushed = false; cerrar(); }
    });
    root._tnmCloseModal = cerrar;
  }

  /* ---------- ficha: pestañas, carrusel, 3D, descargas, compartir ---------- */
  function hydrateFicha(art, { modal = false } = {}) {
    if (!art || art._tnm) return; art._tnm = true;
    $$('[data-tnm-carousel]', art).forEach(initCarousel);
    const setView = v => {
      $$('[data-tnm-tab]', art).forEach(b => b.setAttribute('aria-selected', String(b.dataset.tnmTab === v)));
      $$('[data-tnm-view]', art).forEach(el => { el.hidden = el.dataset.tnmView !== v; });
      if (v === '3d') mount3D(art);
    };
    $$('[data-tnm-tab]', art).forEach(b => b.addEventListener('click', () => setView(b.dataset.tnmTab)));
    if (art.dataset.vista === '3d') mount3D(art);
    art.querySelector('[data-tnm-print]')?.addEventListener('click', () => printFicha(art));
    art.querySelector('[data-tnm-share]')?.addEventListener('click', async () => {
      const url = art.dataset.url, title = art.dataset.nombre;
      if (navigator.share) { try { await navigator.share({ title, url }); } catch { /* cancelado */ } return; }
      try { await navigator.clipboard.writeText(url); toast('Enlace copiado'); } catch { toast(url); }
    });
    art.querySelector('[data-tnm-quote]')?.addEventListener('click', e => {
      toggleSel(art.dataset.id, art.dataset.nombre, true);
      if (modal) {
        e.preventDefault();
        const root = art.closest('[data-tnm-catalogo]');
        root?._tnmCloseModal?.();
        setTimeout(() => root?.querySelector('#tnm-contacto')?.scrollIntoView({ behavior: 'smooth' }), 80);
      }
    });
    syncSel();
  }

  function initCarousel(car) {
    const track = car.querySelector('.tnm-slides'), slides = $$('.tnm-slide', track), n = slides.length;
    if (n < 2) return;
    const dots = $$('[data-tnm-dot]', car), count = car.querySelector('[data-tnm-car-count]');
    const prev = car.querySelector('[data-tnm-car="-1"]'), next = car.querySelector('[data-tnm-car="1"]');
    let idx = 0, raf = 0;
    const go = i => { track.scrollTo({ left: Math.max(0, Math.min(n - 1, i)) * track.clientWidth, behavior: 'smooth' }); };
    const update = () => {
      idx = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
      dots.forEach((d, i) => d.setAttribute('aria-current', String(i === idx)));
      if (count) count.textContent = `${idx + 1} / ${n}`;
      prev.disabled = idx === 0; next.disabled = idx === n - 1;
    };
    track.addEventListener('scroll', () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(update); }, { passive: true });
    car.addEventListener('click', e => {
      const b = e.target.closest('[data-tnm-car]'); if (b) go(idx + Number(b.dataset.tnmCar));
      const d = e.target.closest('[data-tnm-dot]'); if (d) go(Number(d.dataset.tnmDot));
    });
    track.addEventListener('keydown', e => {
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(idx - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); go(idx + 1); }
    });
    update();
  }

  /* ---------- visor 3D y realidad aumentada ---------- */
  let mvReady = null;
  const loadModelViewer = () => (mvReady ||= import(T.mv).then(() => customElements.whenDefined('model-viewer')));
  function mount3D(art) {
    const stage = art.querySelector('[data-tnm-view="3d"]');
    if (!stage || stage.querySelector('model-viewer') || stage._loading) return;
    stage._loading = true;
    stage.innerHTML = `<div class="tnm-stage-empty"><span class="tnm-ph">${icon('cube')}</span><p>Cargando modelo 3D…</p></div>`;
    loadModelViewer().then(() => {
      const mv = document.createElement('model-viewer');
      const attrs = {
        src: art.dataset.glb, alt: `Modelo 3D de ${art.dataset.nombre}`, poster: art.dataset.poster || '', 'camera-controls': '', 'touch-action': 'pan-y',
        'auto-rotate': '', 'auto-rotate-delay': '2500', 'rotation-per-second': '14deg', 'interaction-prompt': 'auto',
        'shadow-intensity': '1', 'shadow-softness': '.8', 'environment-image': 'neutral', exposure: '1.05',
        'camera-orbit': '-35deg 72deg auto', ar: '', 'ar-modes': 'webxr scene-viewer quick-look', 'ar-scale': 'fixed',
      };
      Object.entries(attrs).forEach(([k, v]) => mv.setAttribute(k, v));
      mv.innerHTML = `<button slot="ar-button" class="tnm-ar-btn" type="button">${icon('ar')}Ver en tu local · tamaño real</button><div slot="progress-bar" class="tnm-mv-progress"><i></i></div>`;
      mv.addEventListener('progress', e => {
        const bar = mv.querySelector('.tnm-mv-progress i');
        if (bar) bar.style.width = `${Math.round(e.detail.totalProgress * 100)}%`;
        if (e.detail.totalProgress >= 1) mv.querySelector('.tnm-mv-progress')?.remove();
      });
      // ar-scale="fixed": en realidad aumentada la máquina sale al tamaño del GLB. Se indica cuál es
      // y, si coincide con la ficha (±8 %), que está a escala real.
      mv.addEventListener('load', () => {
        const d = mv.getDimensions(), cm = [d.x, d.z, d.y].map(v => Math.round(v * 100));
        const ficha = (art.dataset.dims || '').split(',').map(Number);
        const real = ficha.length === 3 && ficha.every((v, i) => v && Math.abs(cm[i] - v) / v <= 0.08);
        const note = document.createElement('span');
        note.className = 'tnm-stage-note';
        note.innerHTML = `${icon(real ? 'check' : 'ruler')}${real ? 'A escala real' : 'Tamaño en AR'} · ${cm.map(fmt).join(' × ')} cm`;
        stage.appendChild(note);
        if (!mv.canActivateAR) {
          const b = document.createElement('button');
          b.type = 'button'; b.className = 'tnm-qr-open';
          b.innerHTML = `${icon('qr')}Verla en tu local con el móvil`;
          b.addEventListener('click', () => toggleQR(stage, art.dataset.visor));
          stage.appendChild(b);
        }
      }, { once: true });
      mv.addEventListener('error', () => { stage.innerHTML = `<div class="tnm-stage-empty"><span class="tnm-ph">${icon('cube')}</span><p><b>No se pudo cargar el modelo 3D</b></p></div>`; });
      stage.innerHTML = ''; stage.appendChild(mv);
    }).catch(() => { stage.innerHTML = `<div class="tnm-stage-empty"><span class="tnm-ph">${icon('cube')}</span><p><b>Visor 3D no disponible</b></p></div>`; })
      .finally(() => { stage._loading = false; });
  }

  let qrReady = null;
  const loadQR = () => (qrReady ||= new Promise((ok, ko) => {
    if (window.qrcode) return ok();
    const s = document.createElement('script'); s.src = T.qr; s.onload = ok; s.onerror = ko; document.head.appendChild(s);
  }));
  async function toggleQR(stage, url) {
    const old = stage.querySelector('.tnm-qr-pop'); if (old) return old.remove();
    const pop = document.createElement('div'); pop.className = 'tnm-qr-pop';
    let svg = '';
    try { await loadQR(); const q = window.qrcode(0, 'M'); q.addData(url); q.make(); svg = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true }); } catch { /* sin QR */ }
    pop.innerHTML = `${svg}<p><b>Escanéalo con tu móvil</b> y pulsa «Ver en tu local» para colocar la máquina a tamaño real.</p>`;
    stage.appendChild(pop);
  }

  /* ---------- ficha técnica para imprimir o guardar como PDF ---------- */
  function printFicha(art) {
    const img = art.dataset.poster || art.querySelector('.tnm-slide img')?.currentSrc || '';
    const rows = $$('.tnm-spec', art).map(r => `<tr><th>${esc(r.querySelector('dt').textContent)}</th><td>${esc(r.querySelector('dd').firstChild?.textContent || '')}</td></tr>`).join('');
    const desc = art.querySelector('.tnm-desc')?.innerHTML || '';
    const dim = art.querySelector('.tnm-dim')?.outerHTML || '';
    const code = art.querySelector('.tnm-f-code b')?.textContent;
    let sheet = document.getElementById('tnm-print');
    if (!sheet) { sheet = document.createElement('div'); sheet.id = 'tnm-print'; document.body.appendChild(sheet); }
    sheet.setAttribute('style', art.getAttribute('style') || '');
    sheet.innerHTML = `<div class="tnm-p-head"><span class="tnm-p-brand">${esc(T.site || '')}</span><span class="tnm-p-doc">Ficha técnica<br>${esc(T.hoy || '')}</span></div>
      <h1 class="tnm-p-title">${esc(art.dataset.nombre)}</h1><p class="tnm-p-sub">${esc(art.querySelector('.tnm-eyebrow')?.textContent || '')}${code ? ` · Código ${esc(code)}` : ''}</p>
      <div class="tnm-p-grid"><div class="tnm-p-img">${img ? `<img src="${esc(img)}" alt="">` : ''}</div><table class="tnm-p-table">${rows}</table></div>
      ${desc ? `<h2 class="tnm-p-h">Descripción</h2><div class="tnm-p-desc">${desc}</div>` : ''}
      ${dim ? `<div class="tnm-p-dim"><h2 class="tnm-p-h">Dimensiones</h2>${dim}</div>` : ''}
      <div class="tnm-p-foot"><span>${esc(T.site || '')} · ${esc(location.host)}</span><span>Medidas orientativas; confirmar con el equipo comercial.</span></div>`;
    document.body.classList.add('tnm-printing');
    const done = () => document.body.classList.remove('tnm-printing');
    window.addEventListener('afterprint', done, { once: true });
    const pic = sheet.querySelector('img');
    const go = () => setTimeout(() => window.print(), 60);
    if (pic && !pic.complete) { pic.onload = go; pic.onerror = go; } else go();
  }

  /* ---------- formulario ---------- */
  function initForm(form) {
    const msg = form.querySelector('[data-tnm-form-msg]');
    const ok = form.parentElement.querySelector('[data-tnm-form-ok]');
    form.addEventListener('input', e => { e.target.closest('.tnm-field')?.classList.remove('tnm-invalid'); e.target.closest('.tnm-consent')?.classList.remove('tnm-invalid'); });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      let first = null;
      const check = (name, test) => { const el = form.elements[name]; const good = test(el.value.trim()); el.closest('.tnm-field').classList.toggle('tnm-invalid', !good); if (!good && !first) first = el; };
      check('nombre', v => v.length > 1);
      check('email', v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v));
      check('telefono', v => v.replace(/\D/g, '').length >= 9);
      check('ciudad', v => v.length > 1);
      check('mensaje', v => v.length > 2);
      const consent = form.elements.consentimiento;
      consent.closest('.tnm-consent').classList.toggle('tnm-invalid', !consent.checked);
      if (first || !consent.checked) { (first || consent).focus(); msg.textContent = 'Revisa los campos marcados.'; return; }
      msg.textContent = '';
      const btn = form.querySelector('[type=submit]'); btn.disabled = true; btn.textContent = 'Enviando…';
      try {
        // form.action no sirve: el campo oculto «action» (lo exige WordPress) tapa esa propiedad
        const r = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data.ok) throw new Error(data.error || 'No se pudo enviar. Inténtalo de nuevo.');
        form.hidden = true; if (ok) { ok.hidden = false; ok.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        sel.clear(); saveSel(); syncSel();
      } catch (err) { msg.textContent = err.message; }
      finally { btn.disabled = false; btn.textContent = 'Enviar solicitud'; }
    });
  }

  /* ---------- arranque ---------- */
  document.addEventListener('click', e => {
    const add = e.target.closest('[data-tnm-add]');
    if (add) { const on = toggleSel(add.dataset.tnmAdd, add.dataset.title); toast(on ? 'Añadida a tu solicitud' : 'Quitada de tu solicitud'); return; }
    const un = e.target.closest('[data-tnm-unsel]');
    if (un) toggleSel(un.dataset.tnmUnsel, '', false);
  });
  $$('[data-tnm-contacto]').forEach(c => {
    (c.dataset.preselect || '').split(',').filter(Boolean).forEach(id => {
      const t = document.querySelector(`[data-tnm-add="${CSS.escape(id)}"]`)?.dataset.title;
      if (!sel.has(id)) sel.set(id, t || '');
    });
    new IntersectionObserver(([en]) => { document.body.classList.toggle('tnm-at-contact', en.isIntersecting); syncSel(); }, { threshold: 0.15 }).observe(c);
  });
  $$('[data-tnm-catalogo]').forEach(initCatalog);
  $$('.tnm-ficha--pagina[data-tnm-ficha]').forEach(a => hydrateFicha(a));
  $$('form[data-tnm-form]').forEach(initForm);
  syncSel();
})();
