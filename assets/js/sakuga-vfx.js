// assets/js/sakuga-vfx.js - OMIT NoteFlow Sakuga-Inspired Micro-VFX Engine (Zero Dependencies)
// Respects prefers-reduced-motion; automatically disabled on print views.
(function () {
  if (typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  const PALETTE_BRAND   = ['#6558f5', '#8b5cf6', '#68cfa3', '#a5a1d4', '#fbbf24'];
  const PALETTE_SUCCESS = ['#10b981', '#68cfa3', '#6558f5', '#34d399'];
  const PALETTE_WARN    = ['#f59e0b', '#fbbf24', '#6558f5', '#fb923c'];

  let canvas = null;
  let ctx = null;
  let particles = [];
  let rings = [];
  let rafId = null;

  function ensureCanvas() {
    if (canvas) return;
    canvas = document.createElement('canvas');
    canvas.id = 'omit-sakuga-canvas';
    canvas.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:9999;';
    document.body.appendChild(canvas);
    ctx = canvas.getContext('2d');
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas, { passive: true });
  }

  function resizeCanvas() {
    if (!canvas) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width  = Math.floor(window.innerWidth * dpr);
    canvas.height = Math.floor(window.innerHeight * dpr);
    if (ctx) ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function burst(x, y, opts) {
    const o = Object.assign({ count: 14, palette: PALETTE_BRAND, spread: 58, ring: true, size: 4.5 }, opts || {});
    ensureCanvas();

    if (o.ring) {
      rings.push({
        x, y,
        r: 4,
        maxR: o.spread * 0.72,
        alpha: 0.65,
        color: o.palette[0],
      });
    }

    for (let i = 0; i < o.count; i++) {
      const angle = (Math.PI * 2 * i) / o.count + (Math.random() - 0.5) * 0.45;
      const speed = 1.8 + Math.random() * (o.spread / 14);
      const shape = i % 3 === 0 ? 'diamond' : (i % 2 === 0 ? 'streak' : 'circle');
      particles.push({
        x, y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed - 0.6,
        size: o.size * (0.65 + Math.random() * 0.65),
        alpha: 1,
        decay: 0.028 + Math.random() * 0.018,
        color: o.palette[i % o.palette.length],
        shape,
        rot: angle,
      });
    }

    if (!rafId) loop();
  }

  function loop() {
    if (!ctx) return;
    ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

    for (let i = rings.length - 1; i >= 0; i--) {
      const rg = rings[i];
      rg.r += (rg.maxR - rg.r) * 0.22;
      rg.alpha -= 0.045;
      if (rg.alpha <= 0.02) {
        rings.splice(i, 1);
        continue;
      }
      ctx.save();
      ctx.globalAlpha = rg.alpha;
      ctx.strokeStyle = rg.color;
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.arc(rg.x, rg.y, rg.r, 0, Math.PI * 2);
      ctx.stroke();
      ctx.restore();
    }

    for (let i = particles.length - 1; i >= 0; i--) {
      const p = particles[i];
      p.x += p.vx;
      p.y += p.vy;
      p.vx *= 0.93;
      p.vy = p.vy * 0.93 + 0.08;
      p.alpha -= p.decay;
      if (p.alpha <= 0.02) {
        particles.splice(i, 1);
        continue;
      }

      ctx.save();
      ctx.globalAlpha = Math.max(0, p.alpha);
      ctx.fillStyle = p.color;
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);

      if (p.shape === 'diamond') {
        const s = p.size * 1.1;
        ctx.beginPath();
        ctx.moveTo(0, -s);
        ctx.lineTo(s * 0.7, 0);
        ctx.lineTo(0, s);
        ctx.lineTo(-s * 0.7, 0);
        ctx.closePath();
        ctx.fill();
      } else if (p.shape === 'streak') {
        ctx.fillRect(-p.size * 1.3, -p.size * 0.32, p.size * 2.6, p.size * 0.64);
      } else {
        ctx.beginPath();
        ctx.arc(0, 0, p.size * 0.65, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.restore();
    }

    if (particles.length > 0 || rings.length > 0) {
      rafId = requestAnimationFrame(loop);
    } else {
      rafId = null;
      ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
    }
  }

  function applySpring(el) {
    if (!el) return;
    el.classList.remove('sakuga-spring');
    void el.offsetWidth;
    el.classList.add('sakuga-spring');
  }

  function centerOf(el) {
    const r = el.getBoundingClientRect();
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
  }

  // Global delegated micro-interactions
  document.addEventListener('click', function (e) {
    const target = e.target.closest('button[type="submit"], #theme-toggle-btn, #drawer-theme-light, #drawer-theme-dark, .dashed-lavender-box, .sakuga-btn');
    if (target) {
      applySpring(target);
      const isTheme = target.id && target.id.includes('theme');
      const isDraft = target.value === 'draft';
      const pos = e.clientX ? { x: e.clientX, y: e.clientY } : centerOf(target);
      burst(pos.x, pos.y, {
        count: isTheme ? 10 : 14,
        spread: isTheme ? 42 : 56,
        palette: isDraft ? PALETTE_WARN : (isTheme ? PALETTE_SUCCESS : PALETTE_BRAND),
      });
    }
  }, { passive: true });

  // Crisp spark when checking checkboxes
  document.addEventListener('change', function (e) {
    const cb = e.target;
    if (cb && cb.type === 'checkbox' && cb.checked) {
      const pos = centerOf(cb);
      burst(pos.x, pos.y, { count: 8, spread: 30, size: 3.4, ring: false, palette: PALETTE_SUCCESS });
    }
  }, { passive: true });

  // Toast entrance spring + celebratory burst on success toast
  document.addEventListener('DOMContentLoaded', function () {
    const toasts = document.querySelectorAll('#toast-container > div');
    toasts.forEach(function (toast, idx) {
      toast.classList.add('sakuga-toast-pop');
      if (idx === 0 && toast.innerHTML.includes('emerald')) {
        setTimeout(function () {
          const r = toast.getBoundingClientRect();
          if (r.width > 0) {
            burst(r.left + 26, r.top + r.height / 2, {
              count: 14,
              spread: 48,
              palette: PALETTE_SUCCESS,
            });
          }
        }, 120);
      }
    });
  });

  window.SakugaVFX = { burst, applySpring };
})();
