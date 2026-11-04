/* Tony Ford's Free Image Gallery - site behaviour (no dependencies, CSP-safe: no inline JS) */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement, body = doc.body;
  var D = body.dataset;
  root.classList.add('js');
  var LIGHT = { 'classic-light': 1 };
  var TFG = window.TFG = window.TFG || {};

  TFG.apiUrl = function (name) {
    return D.pretty === '1' ? (D.base || '') + '/api/' + name : (D.base || '') + '/index.php?r=api/' + name;
  };
  TFG.post = function (name, data) {
    var fd = new FormData();
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('csrf', D.csrf);
    return fetch(TFG.apiUrl(name), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected server reply.' }; }); });
  };
  TFG.toast = function (msg, kind) {
    var t = doc.createElement('div');
    t.className = 'toast ' + (kind || '');
    t.textContent = msg;
    t.setAttribute('role', 'status');
    body.appendChild(t);
    setTimeout(function () { t.classList.add('out'); }, 2800);
    setTimeout(function () { t.remove(); }, 3300);
  };

  /* ---- image fade-in ---- */
  function markLoaded(img) { img.classList.add('loaded'); }
  function watchImages(scope) {
    (scope || doc).querySelectorAll('.g-item img, .album-cover img').forEach(function (img) {
      if (img.complete && img.naturalWidth) markLoaded(img);
      else { img.addEventListener('load', function () { markLoaded(img); }, { once: true }); img.addEventListener('error', function () { markLoaded(img); }, { once: true }); }
    });
  }
  watchImages();

  /* ---- header: mobile nav + user menu ---- */
  var navBtn = doc.querySelector('[data-nav-toggle]'), nav = doc.getElementById('main-nav');
  if (navBtn && nav) navBtn.addEventListener('click', function () {
    var open = nav.classList.toggle('open'); navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  var um = doc.querySelector('[data-usermenu]');
  if (um) {
    var menu = um.parentNode.querySelector('.menu');
    um.addEventListener('click', function (e) { e.stopPropagation(); var h = menu.hidden; menu.hidden = !h; um.setAttribute('aria-expanded', h ? 'true' : 'false'); });
    doc.addEventListener('click', function (e) { if (!menu.contains(e.target)) { menu.hidden = true; um.setAttribute('aria-expanded', 'false'); } });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') { menu.hidden = true; um.setAttribute('aria-expanded', 'false'); } });
  }

  /* ---- themes ---- */
  function setCookie(name, val) {
    doc.cookie = name + '=' + encodeURIComponent(val) + ';path=' + ((D.base || '') + '/') + ';max-age=31536000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
  }
  TFG.currentTheme = function () { return root.getAttribute('data-theme'); };
  TFG.setTheme = function (id, save) {
    if (!id) return;
    root.setAttribute('data-theme', id);
    root.setAttribute('data-tone', LIGHT[id] ? 'light' : 'dark');
    var meta = doc.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', LIGHT[id] ? '#f4f6fb' : '#0e1220');
    doc.dispatchEvent(new CustomEvent('tfg:theme', { detail: { theme: id } }));
    doc.querySelectorAll('.theme-card').forEach(function (c) {
      var on = c.getAttribute('data-theme-id') === id;
      c.classList.toggle('selected', on && c.parentNode.getAttribute('data-theme-picker') === 'visitor');
      var r = c.querySelector('input'); if (r && c.parentNode.getAttribute('data-theme-picker') === 'visitor') r.checked = on;
    });
    if (save !== false && D.allowThemes === '1') { setCookie('tfg_theme', id); TFG.post('theme', { theme: id }); }
  };
  var toneBtn = doc.querySelector('[data-toggle-tone]');
  if (toneBtn) {
    if (D.allowThemes !== '1') toneBtn.hidden = true;
    toneBtn.addEventListener('click', function () {
      var cur = TFG.currentTheme(), prev = null;
      try { prev = localStorage.getItem('tfg_prev_theme'); } catch (e) {}
      if (LIGHT[cur]) { TFG.setTheme(prev && !LIGHT[prev] ? prev : 'classic-dark'); }
      else { try { localStorage.setItem('tfg_prev_theme', cur); } catch (e) {} TFG.setTheme('classic-light'); }
    });
  }
  var dlg = doc.getElementById('theme-dialog');
  doc.querySelectorAll('[data-open-themes]').forEach(function (b) {
    b.addEventListener('click', function () { if (dlg && dlg.showModal) dlg.showModal(); else if (dlg) dlg.setAttribute('open', ''); });
  });
  if (dlg) {
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    dlg.querySelectorAll('.theme-card input').forEach(function (r) {
      r.addEventListener('change', function () { TFG.setTheme(r.value); });
    });
  }
  /* admin form picker: highlight selection */
  doc.querySelectorAll('[data-theme-picker="form"] .theme-card input').forEach(function (r) {
    r.addEventListener('change', function () {
      doc.querySelectorAll('[data-theme-picker="form"] .theme-card').forEach(function (c) { c.classList.toggle('selected', c.contains(r) && r.checked); });
      TFG.setTheme(r.value, false);   // live preview of the whole site, not saved until the form is submitted
    });
  });

  /* ---- layout switch (justified / masonry / grid) ---- */
  var layout = D.layout || 'justified';
  try { layout = localStorage.getItem('tfg_layout') || layout; } catch (e) {}
  function applyLayout(l) {
    doc.querySelectorAll('[data-gallery]').forEach(function (g) { g.classList.remove('justified', 'masonry', 'grid'); g.classList.add(l); });
    doc.querySelectorAll('.layout-switch button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-layout') === l); });
  }
  applyLayout(layout);
  doc.querySelectorAll('.layout-switch button').forEach(function (b) {
    b.addEventListener('click', function () { layout = b.getAttribute('data-layout'); try { localStorage.setItem('tfg_layout', layout); } catch (e) {} applyLayout(layout); });
  });

  /* ---- likes ---- */
  TFG.applyLike = function (id, liked, count) {
    doc.querySelectorAll('[data-like="' + id + '"]').forEach(function (b) {
      b.classList.toggle('on', liked); b.setAttribute('aria-pressed', liked ? 'true' : 'false');
      var h = b.querySelector('.heart'); if (h) { h.textContent = liked ? '♥' : '♡'; h.classList.remove('pop'); void h.offsetWidth; h.classList.add('pop'); }
      var n = b.querySelector('.n'); if (n) n.textContent = count;
    });
    doc.querySelectorAll('.g-link[data-id="' + id + '"]').forEach(function (a) { a.dataset.liked = liked ? '1' : '0'; a.dataset.likes = count; });
    doc.dispatchEvent(new CustomEvent('tfg:like', { detail: { id: String(id), liked: liked, count: count } }));
  };
  TFG.like = function (id) {
    return TFG.post('like', { id: id }).then(function (r) {
      if (r && r.ok) TFG.applyLike(id, r.liked, r.count);
      else TFG.toast((r && r.error) || 'Could not like this picture.', 'err');
      return r;
    }).catch(function () { TFG.toast('Network problem - try again.', 'err'); });
  };
  doc.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-like]');
    if (!b) return;
    e.preventDefault(); e.stopPropagation();
    TFG.like(b.getAttribute('data-like'));
  });

  /* ---- confirm dialogs & small form helpers ---- */
  doc.addEventListener('submit', function (e) {
    var f = e.target, msg = f.getAttribute && f.getAttribute('data-confirm');
    var sub = e.submitter;
    if (sub && sub.getAttribute('data-confirm')) msg = sub.getAttribute('data-confirm');
    if (sub && sub.hasAttribute('data-confirm-if')) {
      var sel = f.querySelector('select[name="bulk"]');
      if (!sel || sel.value !== sub.getAttribute('data-confirm-if')) msg = null;
    }
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  doc.querySelectorAll('[data-check-all]').forEach(function (c) {
    c.addEventListener('change', function () { doc.querySelectorAll('input[name="ids[]"]').forEach(function (x) { x.checked = c.checked; }); });
  });
  doc.querySelectorAll('.flash, .alert.ok').forEach(function (a) { setTimeout(function () { a.style.transition = 'opacity .6s'; a.style.opacity = '0.0'; setTimeout(function () { a.remove(); }, 700); }, 6000); });

  /* "Fullscreen" button on the picture page opens the lightbox */
  doc.querySelectorAll('[data-open-lightbox]').forEach(function (b) {
    b.addEventListener('click', function () { var a = doc.querySelector('.g-link'); if (a) a.click(); });
  });
  TFG.watchImages = watchImages;
})();
