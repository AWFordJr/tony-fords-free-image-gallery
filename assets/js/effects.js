/* Tony Ford's Free Image Gallery - animated theme effects.
   One transparent <canvas id="fx"> behind the page (pointer-events:none).
   - requestAnimationFrame only, paused while the tab is hidden
   - off by default for visitors who prefer reduced motion (the ✨ button can still turn it on)
   - no libraries, no network */
(function () {
  'use strict';
  var doc = document, body = doc.body, root = doc.documentElement;
  var cv = doc.getElementById('fx');
  if (!cv || !cv.getContext) return;
  var ctx = cv.getContext('2d');
  var EFFECT_OF = {
    'toxic-rain': 'rain', 'matrix': 'matrix', 'dragons-lair': 'lair', 'midnight-starfield': 'stars',
    'ember-glow': 'embers', 'frozen-snowfall': 'snow', 'neon-synthwave': 'synthwave', 'ocean-depths': 'bubbles'
  };
  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  var W = 0, H = 0, dpr = 1, raf = 0, last = 0, eff = null, effName = 'none', enabled = true;

  function rnd(a, b) { return a + Math.random() * (b - a); }
  function pick(a) { return a[(Math.random() * a.length) | 0]; }

  /* ---------- enable / disable ---------- */
  function readPref() {
    var v = null; try { v = localStorage.getItem('tfg_fx'); } catch (e) {}
    if (v === '1') return true;
    if (v === '0') return false;
    if (mq.matches) return false;
    return body.dataset.fxDefault !== '0';
  }
  function savePref(on) { try { localStorage.setItem('tfg_fx', on ? '1' : '0'); } catch (e) {} }
  var btns = doc.querySelectorAll('[data-toggle-fx]');
  function syncButtons() {
    btns.forEach(function (b) {
      b.setAttribute('aria-pressed', enabled ? 'true' : 'false');
      b.title = enabled ? 'Animated effects: on (click to turn off)' : 'Animated effects: off (click to turn on)';
      b.hidden = effName === 'none';
    });
  }
  btns.forEach(function (b) { b.addEventListener('click', function () { enabled = !enabled; savePref(enabled); apply(); }); });
  if (mq.addEventListener) mq.addEventListener('change', function () { var v = null; try { v = localStorage.getItem('tfg_fx'); } catch (e) {} if (v === null) { enabled = readPref(); apply(); } });

  /* ---------- sizing ---------- */
  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    W = window.innerWidth; H = window.innerHeight;
    cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (eff && eff.init) eff.init();
  }
  var rt = 0;
  window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(resize, 120); });

  /* ---------- loop ---------- */
  function loop(t) {
    raf = requestAnimationFrame(loop);
    var dt = Math.max(0.001, Math.min(0.05, (t - last) / 1000 || 0.016));
    if (eff.fps && (t - last) < 1000 / eff.fps - 2) return;
    last = t;
    eff.frame(dt, t / 1000);
  }
  function start() { if (!raf && eff) { last = performance.now(); raf = requestAnimationFrame(loop); } }
  function stop() { if (raf) { cancelAnimationFrame(raf); raf = 0; } }
  doc.addEventListener('visibilitychange', function () { if (doc.hidden) stop(); else if (enabled && eff) start(); });

  function apply() {
    stop();
    var theme = root.getAttribute('data-theme');
    effName = EFFECT_OF[theme] || 'none';
    eff = (enabled && effName !== 'none' && EFFECTS[effName]) ? EFFECTS[effName]() : null;
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
    cv.classList.toggle('on', !!eff);
    syncButtons();
    if (eff) { resize(); if (!doc.hidden) start(); }
  }
  doc.addEventListener('tfg:theme', apply);

  function clear() { ctx.clearRect(0, 0, W, H); }

  /* ---------- the effects ---------- */
  var EFFECTS = {};

  /* Toxic Rain: neon-green rain with splashes where drops land */
  EFFECTS.rain = function () {
    var drops = [], splashes = [], parts = [];
    var wind = 0.22;
    function mk(initial) {
      var d = { x: rnd(-60, W + 60), y: initial ? rnd(-H, H) : rnd(-200, -10), l: rnd(10, 28), v: rnd(620, 1050), a: rnd(0.25, 0.8) };
      d.ground = rnd(H * 0.30, H * 0.98);
      return d;
    }
    return {
      init: function () { var n = Math.min(260, Math.round(W * H / 7500)); drops = []; for (var i = 0; i < n; i++) drops.push(mk(true)); splashes = []; parts = []; },
      frame: function (dt) {
        clear();
        ctx.globalCompositeOperation = 'lighter'; ctx.lineCap = 'round';
        for (var i = 0; i < drops.length; i++) {
          var d = drops[i];
          d.y += d.v * dt; d.x += d.v * wind * dt;
          if (d.y >= d.ground) {
            if (splashes.length < 90) splashes.push({ x: d.x, y: d.ground, r: 1, max: rnd(8, 20), a: d.a });
            if (parts.length < 160) for (var k = 0; k < 3; k++) parts.push({ x: d.x, y: d.ground, vx: rnd(-60, 60), vy: rnd(-150, -60), life: 1 });
            drops[i] = mk(false); continue;
          }
          ctx.strokeStyle = 'rgba(80,255,60,' + d.a + ')'; ctx.lineWidth = 1.2;
          ctx.beginPath(); ctx.moveTo(d.x, d.y); ctx.lineTo(d.x - d.l * wind, d.y - d.l); ctx.stroke();
        }
        for (var s = splashes.length - 1; s >= 0; s--) {
          var sp = splashes[s]; sp.r += 38 * dt; var p = sp.r / sp.max;
          if (p >= 1) { splashes.splice(s, 1); continue; }
          ctx.strokeStyle = 'rgba(120,255,90,' + (0.55 * (1 - p) * sp.a) + ')'; ctx.lineWidth = 1;
          ctx.beginPath(); ctx.ellipse(sp.x, sp.y, Math.max(0.1, sp.r), Math.max(0.05, sp.r * 0.38), 0, 0, 6.2832); ctx.stroke();
        }
        ctx.fillStyle = '#b6ff00';
        for (var q = parts.length - 1; q >= 0; q--) {
          var pt = parts[q]; pt.vy += 420 * dt; pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.life -= dt * 2.4;
          if (pt.life <= 0) { parts.splice(q, 1); continue; }
          ctx.globalAlpha = Math.max(0, pt.life) * 0.8; ctx.fillRect(pt.x, pt.y, 1.6, 1.6);
        }
        ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
      }
    };
  };

  /* Matrix: falling green glyphs */
  EFFECTS.matrix = function () {
    var size = 16, cols = 0, ys = [], speeds = [], chars = 'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝ0123456789ZT*+=<>';
    var acc = 0;
    return {
      fps: 24,
      init: function () {
        cols = Math.ceil(W / size); ys = []; speeds = [];
        for (var i = 0; i < cols; i++) { ys.push(rnd(-H / size, 0)); speeds.push(rnd(0.35, 1)); }
        clear();
      },
      frame: function () {
        ctx.globalCompositeOperation = 'destination-out'; ctx.fillStyle = 'rgba(0,0,0,0.09)'; ctx.fillRect(0, 0, W, H);
        ctx.globalCompositeOperation = 'source-over'; ctx.font = size + 'px monospace'; ctx.textBaseline = 'top';
        for (var i = 0; i < cols; i++) {
          if (Math.random() > 0.55 + speeds[i] * 0.4) continue;
          var ch = chars.charAt((Math.random() * chars.length) | 0), x = i * size, y = ys[i] * size;
          ctx.fillStyle = '#c8ffd4'; ctx.fillText(ch, x, y);
          ctx.fillStyle = 'rgba(0,255,65,0.9)'; ctx.fillText(chars.charAt((Math.random() * chars.length) | 0), x, y - size);
          ys[i] += 1;
          if (y > H && Math.random() > 0.975) { ys[i] = rnd(-20, 0); speeds[i] = rnd(0.35, 1); }
        }
      }
    };
  };

  /* soft glowing sprite used by embers / lair */
  function glowSprite(r, g, b) {
    var c = doc.createElement('canvas'); c.width = c.height = 32; var x = c.getContext('2d');
    var gr = x.createRadialGradient(16, 16, 0, 16, 16, 16);
    gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(0.25, 'rgba(' + r + ',' + g + ',' + b + ',0.9)'); gr.addColorStop(1, 'rgba(' + r + ',' + g + ',' + b + ',0)');
    x.fillStyle = gr; x.fillRect(0, 0, 32, 32); return c;
  }

  /* Dragon's Lair: fast fire sparks rising and flickering */
  EFFECTS.lair = function () {
    var sprites = [glowSprite(255, 177, 59), glowSprite(229, 56, 59), glowSprite(255, 214, 102)], ps = [];
    function mk(init) { return { x: rnd(0, W), y: init ? rnd(0, H) : H + rnd(0, 40), vx: rnd(-14, 14), vy: -rnd(40, 150), s: rnd(3, 9), sp: pick(sprites), ph: rnd(0, 6.28), f: rnd(4, 10) }; }
    return {
      init: function () { ps = []; var n = Math.min(110, Math.round(W / 14)); for (var i = 0; i < n; i++) ps.push(mk(true)); },
      frame: function (dt, t) {
        clear(); ctx.globalCompositeOperation = 'lighter';
        for (var i = 0; i < ps.length; i++) {
          var p = ps[i]; p.x += (p.vx + Math.sin(t * 2 + p.ph) * 22) * dt; p.y += p.vy * dt;
          var life = Math.max(0, p.y / H), fl = 0.55 + 0.45 * Math.sin(t * p.f + p.ph);
          if (p.y < -20 || life === 0) { ps[i] = mk(false); continue; }
          ctx.globalAlpha = Math.min(1, life * 1.4) * fl * 0.9;
          ctx.drawImage(p.sp, p.x - p.s, p.y - p.s, p.s * 2, p.s * 2);
        }
        ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
      }
    };
  };

  /* Midnight Starfield: twinkling stars drifting slowly + rare shooting star */
  EFFECTS.stars = function () {
    var st = [], shoot = null, next = 3;
    return {
      init: function () { st = []; var n = Math.min(260, Math.round(W * H / 6500)); for (var i = 0; i < n; i++) { var z = Math.random(); st.push({ x: rnd(0, W), y: rnd(0, H), z: z, r: 0.4 + z * 1.4, ph: rnd(0, 6.28), sp: rnd(0.6, 2.2) }); } },
      frame: function (dt, t) {
        clear();
        for (var i = 0; i < st.length; i++) {
          var s = st[i]; s.x -= (3 + s.z * 10) * dt; s.y += (1 + s.z * 3) * dt;
          if (s.x < -4) s.x = W + 4; if (s.y > H + 4) s.y = -4;
          var a = 0.35 + 0.65 * (0.5 + 0.5 * Math.sin(t * s.sp + s.ph));
          ctx.globalAlpha = a * (0.5 + s.z * 0.5); ctx.fillStyle = s.z > 0.8 ? '#cfe0ff' : '#ffffff';
          ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 6.2832); ctx.fill();
        }
        next -= dt;
        if (!shoot && next <= 0) { shoot = { x: rnd(W * 0.3, W), y: rnd(0, H * 0.4), vx: -rnd(500, 800), vy: rnd(180, 320), life: 1 }; next = rnd(6, 14); }
        if (shoot) {
          shoot.x += shoot.vx * dt; shoot.y += shoot.vy * dt; shoot.life -= dt * 1.1;
          if (shoot.life <= 0) shoot = null; else {
            var g = ctx.createLinearGradient(shoot.x, shoot.y, shoot.x - shoot.vx * 0.12, shoot.y - shoot.vy * 0.12);
            g.addColorStop(0, 'rgba(255,255,255,' + shoot.life + ')'); g.addColorStop(1, 'rgba(138,180,255,0)');
            ctx.globalAlpha = 1; ctx.strokeStyle = g; ctx.lineWidth = 1.6; ctx.beginPath(); ctx.moveTo(shoot.x, shoot.y); ctx.lineTo(shoot.x - shoot.vx * 0.12, shoot.y - shoot.vy * 0.12); ctx.stroke();
          }
        }
        ctx.globalAlpha = 1;
      }
    };
  };

  /* Ember Glow: warm embers floating up */
  EFFECTS.embers = function () {
    var sprites = [glowSprite(255, 122, 26), glowSprite(255, 61, 31), glowSprite(255, 177, 59)], ps = [];
    function mk(init) { return { x: rnd(0, W), y: init ? rnd(0, H) : H + rnd(10, 60), vy: -rnd(18, 60), s: rnd(2, 7), sp: pick(sprites), ph: rnd(0, 6.28), a: rnd(0.4, 1) }; }
    return {
      init: function () { ps = []; var n = Math.min(90, Math.round(W / 16)); for (var i = 0; i < n; i++) ps.push(mk(true)); },
      frame: function (dt, t) {
        clear(); ctx.globalCompositeOperation = 'lighter';
        for (var i = 0; i < ps.length; i++) {
          var p = ps[i]; p.y += p.vy * dt; p.x += Math.sin(t * 0.9 + p.ph) * 14 * dt;
          if (p.y < -20) { ps[i] = mk(false); continue; }
          var fade = Math.min(1, p.y / (H * 0.9) + 0.1), tw = 0.7 + 0.3 * Math.sin(t * 3 + p.ph);
          ctx.globalAlpha = p.a * fade * tw; ctx.drawImage(p.sp, p.x - p.s, p.y - p.s, p.s * 2, p.s * 2);
        }
        ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
      }
    };
  };

  /* Frozen Snowfall: soft snow with sway */
  EFFECTS.snow = function () {
    var fl = [];
    function mk(init) { var z = Math.random(); return { x: rnd(0, W), y: init ? rnd(0, H) : -10, z: z, r: 0.8 + z * 2.6, v: 22 + z * 60, ph: rnd(0, 6.28), sw: rnd(8, 26) }; }
    return {
      init: function () { fl = []; var n = Math.min(220, Math.round(W * H / 8500)); for (var i = 0; i < n; i++) fl.push(mk(true)); },
      frame: function (dt, t) {
        clear();
        for (var i = 0; i < fl.length; i++) {
          var f = fl[i]; f.y += f.v * dt; f.x += Math.sin(t * 0.8 + f.ph) * f.sw * dt;
          if (f.y > H + 10) { fl[i] = mk(false); continue; }
          if (f.x > W + 10) f.x = -10; if (f.x < -10) f.x = W + 10;
          ctx.globalAlpha = 0.35 + f.z * 0.6; ctx.fillStyle = f.z > 0.7 ? '#ffffff' : '#d9f1ff';
          ctx.beginPath(); ctx.arc(f.x, f.y, f.r, 0, 6.2832); ctx.fill();
        }
        ctx.globalAlpha = 1;
      }
    };
  };

  /* Neon Synthwave: striped sun + moving perspective grid at the horizon */
  EFFECTS.synthwave = function () {
    var off = 0;
    return {
      fps: 40,
      init: function () {},
      frame: function (dt) {
        clear();
        var hy = H * 0.62, cx = W / 2;
        // sun
        var sr = Math.min(W, H) * 0.20, sy = hy - sr * 0.35;
        var g = ctx.createLinearGradient(0, sy - sr, 0, sy + sr);
        g.addColorStop(0, 'rgba(255,225,77,0.55)'); g.addColorStop(0.55, 'rgba(255,43,214,0.5)'); g.addColorStop(1, 'rgba(120,30,200,0.15)');
        ctx.save(); ctx.beginPath(); ctx.rect(0, 0, W, hy); ctx.clip();
        ctx.fillStyle = g; ctx.beginPath(); ctx.arc(cx, sy, sr, 0, 6.2832); ctx.fill();
        ctx.globalCompositeOperation = 'destination-out';
        for (var i = 0; i < 7; i++) { var by = sy + sr * (0.05 + i * 0.14), bh = 1 + i * 1.1; ctx.fillRect(cx - sr, by, sr * 2, bh); }
        ctx.restore(); ctx.globalCompositeOperation = 'source-over';
        // floor fade
        var fg = ctx.createLinearGradient(0, hy, 0, H); fg.addColorStop(0, 'rgba(30,5,70,0.65)'); fg.addColorStop(1, 'rgba(18,5,43,0.05)');
        ctx.fillStyle = fg; ctx.fillRect(0, hy, W, H - hy);
        // grid
        ctx.lineWidth = 1; ctx.strokeStyle = 'rgba(255,43,214,0.55)';
        ctx.beginPath(); for (var k = -24; k <= 24; k++) { ctx.moveTo(cx, hy); ctx.lineTo(cx + k * W * 0.09, H); } ctx.stroke();
        off = (off + dt * 0.35) % 1; ctx.strokeStyle = 'rgba(0,229,255,0.5)'; ctx.beginPath();
        for (var j = 0; j < 14; j++) { var p = (j + off) / 14, y = hy + (H - hy) * p * p; ctx.moveTo(0, y); ctx.lineTo(W, y); }
        ctx.stroke();
        ctx.strokeStyle = 'rgba(0,229,255,0.7)'; ctx.beginPath(); ctx.moveTo(0, hy); ctx.lineTo(W, hy); ctx.stroke();
      }
    };
  };

  /* Ocean Depths: rising bubbles with wobble */
  EFFECTS.bubbles = function () {
    var bs = [];
    function mk(init) { return { x: rnd(0, W), y: init ? rnd(0, H) : H + rnd(10, 80), r: rnd(2, 13), v: rnd(18, 70), ph: rnd(0, 6.28), w: rnd(6, 24) }; }
    return {
      init: function () { bs = []; var n = Math.min(70, Math.round(W / 22)); for (var i = 0; i < n; i++) bs.push(mk(true)); },
      frame: function (dt, t) {
        clear();
        for (var i = 0; i < bs.length; i++) {
          var b = bs[i]; b.y -= b.v * dt * (0.6 + b.r / 14); var x = b.x + Math.sin(t * 1.1 + b.ph) * b.w;
          if (b.y < -20) { bs[i] = mk(false); continue; }
          var a = Math.min(1, b.y / H + 0.25);
          ctx.globalAlpha = 0.55 * a; ctx.strokeStyle = 'rgba(140,255,240,0.9)'; ctx.lineWidth = 1;
          ctx.beginPath(); ctx.arc(x, b.y, b.r, 0, 6.2832); ctx.stroke();
          ctx.globalAlpha = 0.12 * a; ctx.fillStyle = '#7ff'; ctx.fill();
          ctx.globalAlpha = 0.7 * a; ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(x - b.r * 0.35, b.y - b.r * 0.35, Math.max(0.8, b.r * 0.16), 0, 6.2832); ctx.fill();
        }
        ctx.globalAlpha = 1;
      }
    };
  };

  enabled = readPref();
  apply();
})();
