/* Tony Ford's Free Image Gallery - on-device helper AI (v1.0.1).
   Rule- and stat-based only. No machine learning, no personal data,
   nothing is sent anywhere. Memory is localStorage on this device.
   Visitors cannot turn her off; she never overrides a choice they made. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement, body = doc.body;
  if (!body) return;
  var D = body.dataset;
  var KEY = {
    theme: 'tfg_helper_theme',
    density: 'tfg_helper_density',
    lastCat: 'tfg_helper_last_category',
    userTheme: 'tfg_helper_user_theme'
  };
  var PRELOAD_CAP = 4;

  function store(k, v) {
    try { localStorage.setItem(k, v); } catch (e) {}
  }
  function load(k) {
    try { return localStorage.getItem(k); } catch (e) { return null; }
  }
  function mq(q) {
    return !!(window.matchMedia && window.matchMedia(q).matches);
  }
  function saveData() {
    var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    return !!(c && c.saveData);
  }

  function pickTheme() {
    var w = window.innerWidth || 1024;
    var dpr = window.devicePixelRatio || 1;
    var coarse = mq('(pointer: coarse)');
    var light = mq('(prefers-color-scheme: light)');
    var reduce = mq('(prefers-reduced-motion: reduce)');
    if (reduce || light) return light ? 'classic-light' : 'classic-dark';
    if (coarse || w < 800 || (dpr >= 2 && w < 1100)) return 'classic-dark';
    if (w >= 1280 && dpr <= 1.5) return 'midnight-starfield';
    return 'classic-dark';
  }

  function pickDensity() {
    var w = window.innerWidth || 1024;
    var dpr = window.devicePixelRatio || 1;
    var coarse = mq('(pointer: coarse)');
    if (coarse && w < 700) return 'roomy';
    if (coarse) return 'cozy';
    if (w < 600) return 'compact';
    if (dpr >= 2 && w >= 1100) return 'compact';
    return 'cozy';
  }

  function userChoseTheme() {
    if (D.themeChosen === '1') return true;
    if (load(KEY.userTheme) === '1') return true;
    if (/(?:^|;\s*)tfg_theme=/.test(doc.cookie || '')) return true;
    return false;
  }

  function applyDensity() {
    var d = pickDensity();
    root.setAttribute('data-density', d);
    store(KEY.density, d);
  }

  function applyTheme() {
    if (D.allowThemes !== '1') return;
    if (userChoseTheme()) return;
    var id = pickTheme();
    store(KEY.theme, id);
    if (window.TFG && typeof window.TFG.setTheme === 'function') {
      window.TFG.setTheme(id, false);
    } else {
      root.setAttribute('data-theme', id);
      root.setAttribute('data-tone', id === 'classic-light' ? 'light' : 'dark');
    }
  }

  function markUserTheme() {
    store(KEY.userTheme, '1');
  }

  function wrapSetTheme() {
    if (!window.TFG || typeof window.TFG.setTheme !== 'function' || window.TFG.setTheme._tfgHelper) return;
    var orig = window.TFG.setTheme;
    window.TFG.setTheme = function (id, save) {
      if (save !== false) markUserTheme();
      return orig(id, save);
    };
    window.TFG.setTheme._tfgHelper = true;
  }

  function rememberCategory() {
    var el = doc.querySelector('[data-tfg-category]');
    if (!el) return;
    var slug = el.getAttribute('data-tfg-category') || '';
    if (!slug) return;
    var rec = {
      slug: slug,
      name: el.getAttribute('data-tfg-category-name') || slug,
      url: el.getAttribute('data-tfg-category-href') || ''
    };
    store(KEY.lastCat, JSON.stringify(rec));
  }

  function canPreload() { return !saveData(); }

  function addPreload(href) {
    if (!href || !canPreload()) return false;
    var existing = doc.querySelectorAll('link[data-tfg-helper-preload]');
    for (var i = 0; i < existing.length; i++) {
      if (existing[i].getAttribute('href') === href) return false;
    }
    if (existing.length >= PRELOAD_CAP) return false;
    var l = doc.createElement('link');
    l.rel = 'preload';
    l.as = 'image';
    l.href = href;
    l.setAttribute('data-tfg-helper-preload', '1');
    doc.head.appendChild(l);
    return true;
  }

  function preloadNeighbors() {
    if (!canPreload()) return;
    var stage = doc.querySelector('.viewer-stage');
    if (stage) {
      addPreload(stage.getAttribute('data-prev-full'));
      addPreload(stage.getAttribute('data-next-full'));
    }
    var links = doc.querySelectorAll('[data-gallery] .g-link[data-full]');
    if (links.length) {
      addPreload(links[0].getAttribute('data-full'));
      if (links[1]) addPreload(links[1].getAttribute('data-full'));
    }
  }

  function preloadAround(link) {
    if (!canPreload() || !link) return;
    var g = link.closest('[data-gallery]');
    var list = g ? Array.prototype.slice.call(g.querySelectorAll('.g-link[data-full]')) : [link];
    var i = list.indexOf(link);
    if (i < 0) i = 0;
    if (list[i - 1]) addPreload(list[i - 1].getAttribute('data-full'));
    if (list[i + 1]) addPreload(list[i + 1].getAttribute('data-full'));
    if (list[i]) addPreload(list[i].getAttribute('data-full'));
  }

  wrapSetTheme();
  applyDensity();
  applyTheme();
  rememberCategory();
  preloadNeighbors();

  doc.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a.g-link');
    if (a) preloadAround(a);
  });
  doc.addEventListener('pointerover', function (e) {
    var a = e.target.closest && e.target.closest('a.g-link');
    if (a) preloadAround(a);
  }, { passive: true });
})();
