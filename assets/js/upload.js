/* Tony Ford's Free Image Gallery - drag & drop multi-upload with per-file progress */
(function () {
  'use strict';
  var doc = document, root = doc.getElementById('uploader');
  if (!root) return;
  var $ = function (id) { return doc.getElementById(id); };
  var dz = $('dropzone'), input = $('file-input'), queue = $('queue'), startBtn = $('up-start'), countEl = $('up-count');
  var endpoint = root.dataset.endpoint, maxBytes = parseInt(root.dataset.max, 10) || 0, csrf = doc.body.dataset.csrf;
  var OK = { 'image/jpeg': 1, 'image/png': 1, 'image/gif': 1, 'image/webp': 1 };
  var list = [], running = false;

  function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
  function titleOf(name) { return name.replace(/\.[^.]+$/, '').replace(/[_\-]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 150); }
  function refresh() {
    var n = list.filter(function (x) { return x.state === 'ready'; }).length;
    startBtn.disabled = running || n === 0;
    countEl.textContent = n ? '(' + n + ' picture' + (n === 1 ? '' : 's') + ')' : '';
  }
  function el(tag, cls, text) { var e = doc.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  function add(files) {
    Array.prototype.forEach.call(files, function (f) {
      var it = { file: f, state: 'ready', node: el('div', 'q-item') };
      var th = el('img'); th.alt = '';
      var main = el('div', 'q-main'), t = el('input', 'q-title'); t.value = titleOf(f.name); t.maxLength = 150; t.setAttribute('aria-label', 'Title');
      var bar = el('div', 'q-bar'), fill = el('span'); bar.appendChild(fill);
      var msg = el('div', 'q-msg', size(f.size));
      var x = el('button', 'q-x', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Remove');
      main.appendChild(t); main.appendChild(bar); main.appendChild(msg);
      it.node.appendChild(th); it.node.appendChild(main); it.node.appendChild(x);
      it.title = t; it.fill = fill; it.msg = msg; it.x = x;
      if (!OK[f.type]) { it.state = 'err'; msg.textContent = 'Not a JPG, PNG, GIF or WebP image.'; it.node.classList.add('err'); t.disabled = true; }
      else if (maxBytes && f.size > maxBytes) { it.state = 'err'; msg.textContent = 'Too large (' + size(f.size) + ', limit ' + size(maxBytes) + ').'; it.node.classList.add('err'); t.disabled = true; }
      else if (window.URL && URL.createObjectURL) { th.src = URL.createObjectURL(f); th.addEventListener('load', function () { URL.revokeObjectURL(th.src); }, { once: true }); }
      x.addEventListener('click', function () { if (it.state === 'sending') return; list.splice(list.indexOf(it), 1); it.node.remove(); refresh(); });
      list.push(it); queue.appendChild(it.node);
    });
    refresh();
  }

  function send(it) {
    return new Promise(function (resolve) {
      var fd = new FormData();
      fd.append('file', it.file); fd.append('title', it.title.value.trim() || titleOf(it.file.name));
      fd.append('category_id', $('up-cat').value); fd.append('tags', $('up-tags').value); fd.append('description', $('up-desc').value);
      fd.append('csrf', csrf);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', endpoint); xhr.setRequestHeader('X-Requested-With', 'xhr'); xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = function (e) { if (e.lengthComputable) { var p = Math.round(e.loaded / e.total * 100); it.fill.style.width = p + '%'; it.msg.textContent = p < 100 ? 'Uploading ' + p + '%' : 'Processing…'; } };
      xhr.onload = function () {
        var r = null; try { r = JSON.parse(xhr.responseText); } catch (e) {}
        if (r && r.ok) {
          it.state = 'done'; it.node.classList.add('done'); it.fill.style.width = '100%';
          it.msg.textContent = '';
          var a = el('a', null, r.status === 'pending' ? 'Uploaded - waiting for approval' : 'Uploaded - view picture'); a.href = r.url;
          it.msg.appendChild(a);
          it.title.disabled = true; it.x.hidden = true;
        } else { fail(it, (r && r.error) || 'Upload failed (HTTP ' + xhr.status + ').'); }
        resolve();
      };
      xhr.onerror = function () { fail(it, 'Network error.'); resolve(); };
      xhr.ontimeout = function () { fail(it, 'Timed out.'); resolve(); };
      xhr.timeout = 600000;
      it.state = 'sending'; it.title.disabled = true; it.x.disabled = true; it.fill.style.width = '2%'; it.msg.textContent = 'Starting…';
      xhr.send(fd);
    });
  }
  function fail(it, m) { it.state = 'err'; it.node.classList.add('err'); it.msg.textContent = m; it.x.disabled = false; it.title.disabled = false; it.fill.style.width = '0'; }

  function run() {
    if (running) return; running = true; refresh();
    var chain = Promise.resolve();
    list.filter(function (x) { return x.state === 'ready'; }).forEach(function (it) { chain = chain.then(function () { return send(it); }); });
    chain.then(function () {
      running = false; refresh();
      var ok = list.filter(function (x) { return x.state === 'done'; }).length;
      if (ok && window.TFG) window.TFG.toast(ok + ' picture' + (ok === 1 ? '' : 's') + ' uploaded.');
    });
  }

  dz.addEventListener('click', function () { input.click(); });
  dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', function () { add(input.files); input.value = ''; });
  ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('over'); }); });
  dz.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) add(e.dataTransfer.files); });
  /* stop the browser from navigating to a picture dropped outside the drop zone */
  ['dragover', 'drop'].forEach(function (ev) { window.addEventListener(ev, function (e) { if (!dz.contains(e.target)) e.preventDefault(); }); });
  startBtn.addEventListener('click', run);
  window.addEventListener('beforeunload', function (e) { if (running) { e.preventDefault(); e.returnValue = ''; } });
})();
