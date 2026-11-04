/* Tony Ford's Free Image Gallery - fullscreen lightbox with keyboard, swipe and slideshow */
(function () {
  'use strict';
  var doc = document, body = doc.body;
  var lb = doc.getElementById('lightbox');
  if (!lb) return;
  var $ = function (id) { return doc.getElementById(id); };
  var img = $('lb-img'), title = $('lb-title'), count = $('lb-count'), likeBtn = $('lb-like'), likes = $('lb-likes'),
      playBtn = $('lb-play'), fullBtn = $('lb-full'), openLink = $('lb-open'), prog = $('lb-progress'), stage = $('lb-stage');
  var items = [], idx = 0, lastFocus = null, timer = 0, playing = false, token = 0;
  var secs = Math.max(2, parseInt(body.dataset.slide, 10) || 5);

  function collect(link) {
    var g = link.closest('[data-gallery]');
    var list = g ? g.querySelectorAll('.g-link') : [link];
    return Array.prototype.slice.call(list);
  }
  function preload(i) {
    if (!items.length) return;
    var a = items[(i + items.length) % items.length]; if (!a) return;
    var p = new Image(); p.src = a.getAttribute('data-full');
  }
  function show(i, dir) {
    if (!items.length) return;
    idx = (i + items.length) % items.length;
    var a = items[idx], my = ++token;
    count.textContent = (idx + 1) + ' / ' + items.length;
    title.textContent = a.getAttribute('data-title') || '';
    openLink.href = a.href;
    img.alt = a.getAttribute('data-title') || '';
    setLike(a.dataset.liked === '1', a.dataset.likes || 0);
    img.classList.add('swap');
    var src = a.getAttribute('data-full');
    var done = function () { if (my === token) img.classList.remove('swap'); };
    img.onload = done;
    img.onerror = function () { var t = a.querySelector('img'); if (t && my === token && img.src !== t.src) img.src = t.src; else done(); };
    img.src = src;
    if (img.complete && img.naturalWidth) done();
    preload(idx + 1); preload(idx - 1);
    $('lb-prev').hidden = $('lb-next').hidden = items.length < 2;
    playBtn.hidden = items.length < 2;
    if (playing) schedule();
  }
  function setLike(on, n) {
    likeBtn.classList.toggle('on', !!on);
    likeBtn.firstChild.nodeValue = on ? '♥ ' : '♡ ';
    likes.textContent = n;
    likeBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  function open(list, i) {
    items = list; lastFocus = doc.activeElement;
    lb.hidden = false; body.classList.add('lb-open'); body.style.overflow = 'hidden';
    show(i);
    $('lb-close').focus({ preventScroll: true });
  }
  function close() {
    stopPlay();
    if (doc.fullscreenElement && doc.exitFullscreen) doc.exitFullscreen();
    lb.hidden = true; body.classList.remove('lb-open'); body.style.overflow = ''; img.removeAttribute('src');
    token++;
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }
  function next() { show(idx + 1, 1); }
  function prev() { show(idx - 1, -1); }

  /* slideshow */
  function schedule() {
    clearTimeout(timer);
    prog.classList.remove('run'); void prog.offsetWidth;
    prog.style.animationDuration = secs + 's'; prog.classList.add('run');
    timer = setTimeout(next, secs * 1000);
  }
  function startPlay() { playing = true; playBtn.textContent = '❚❚'; playBtn.setAttribute('aria-label', 'Pause slideshow'); schedule(); }
  function stopPlay() { playing = false; clearTimeout(timer); prog.classList.remove('run'); playBtn.textContent = '▶'; playBtn.setAttribute('aria-label', 'Start slideshow'); }
  function togglePlay() { if (playing) stopPlay(); else startPlay(); }
  function toggleFull() {
    if (doc.fullscreenElement) doc.exitFullscreen();
    else if (lb.requestFullscreen) lb.requestFullscreen().catch(function () {});
  }

  /* open from gallery clicks */
  doc.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a.g-link');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (e.target.closest('[data-like]')) return;
    e.preventDefault();
    var list = collect(a);
    open(list, Math.max(0, list.indexOf(a)));
  });
  /* "?lightbox" style deep link not needed; slideshow button on albums */
  doc.querySelectorAll('[data-start-slideshow]').forEach(function (b) {
    b.addEventListener('click', function () {
      var g = doc.querySelector('[data-gallery]'); if (!g) return;
      var l = Array.prototype.slice.call(g.querySelectorAll('.g-link')); if (!l.length) return;
      open(l, 0); startPlay();
    });
  });

  $('lb-close').addEventListener('click', close);
  $('lb-next').addEventListener('click', function (e) { e.stopPropagation(); next(); });
  $('lb-prev').addEventListener('click', function (e) { e.stopPropagation(); prev(); });
  playBtn.addEventListener('click', togglePlay);
  fullBtn.addEventListener('click', toggleFull);
  likeBtn.addEventListener('click', function () { var a = items[idx]; if (a && window.TFG) window.TFG.like(a.getAttribute('data-id')); });
  doc.addEventListener('tfg:like', function (e) {
    items.forEach(function (a) { if (a.getAttribute('data-id') === e.detail.id) { a.dataset.liked = e.detail.liked ? '1' : '0'; a.dataset.likes = e.detail.count; } });
    var a = items[idx]; if (!lb.hidden && a && a.getAttribute('data-id') === e.detail.id) setLike(e.detail.liked, e.detail.count);
  });
  stage.addEventListener('click', function (e) { if (e.target === stage) close(); });

  doc.addEventListener('keydown', function (e) {
    if (lb.hidden) return;
    var k = e.key;
    if (k === 'Escape') { if (!doc.fullscreenElement) { e.preventDefault(); close(); } }
    else if (k === 'ArrowRight' || k === 'PageDown') { e.preventDefault(); next(); }
    else if (k === 'ArrowLeft' || k === 'PageUp') { e.preventDefault(); prev(); }
    else if (k === 'Home') { e.preventDefault(); show(0); }
    else if (k === 'End') { e.preventDefault(); show(items.length - 1); }
    else if (k === ' ' && !(e.target.tagName === 'BUTTON' || e.target.tagName === 'A')) { e.preventDefault(); togglePlay(); }
    else if (k === 'f' || k === 'F') { e.preventDefault(); toggleFull(); }
    else if (k === 'l' || k === 'L') { e.preventDefault(); likeBtn.click(); }
    else if (k === 'i' || k === 'I') { openLink.click(); }
    else if (k === 'Tab') {   // focus trap
      var f = Array.prototype.filter.call(lb.querySelectorAll('button, a[href]'), function (n) { return !n.hidden && n.offsetParent !== null; });
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* touch swipe: left/right = next/prev, swipe down = close */
  var sx = 0, sy = 0, st = 0, tracking = false;
  lb.addEventListener('touchstart', function (e) { if (e.touches.length !== 1) { tracking = false; return; } tracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY; st = Date.now(); }, { passive: true });
  lb.addEventListener('touchend', function (e) {
    if (!tracking) return; tracking = false;
    var t = e.changedTouches[0], dx = t.clientX - sx, dy = t.clientY - sy;
    if (Date.now() - st > 800) return;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.4) { dx < 0 ? next() : prev(); }
    else if (dy > 90 && Math.abs(dy) > Math.abs(dx) * 1.4) close();
  }, { passive: true });
  doc.addEventListener('visibilitychange', function () { if (doc.hidden && playing) stopPlay(); });
})();
