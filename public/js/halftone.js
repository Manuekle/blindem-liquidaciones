// Halftone art for the login and error pages: diamond dots on a lattice, sized by a slowly drifting fractal noise
// field and faded out toward the form side. Drawn in the accent color, so it follows the theme; dots swell under the
// pointer. Still frame with reduced motion, paused while the tab is hidden.
(() => {
  const canvases = [...document.querySelectorAll('canvas[data-halftone]')];
  if (!canvases.length) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');

  // Value noise with smooth interpolation, summed over octaves (fbm). Deterministic: the same art on every load.
  const hash = (x, y) => {
    const s = Math.sin(x * 127.1 + y * 311.7) * 43758.5453;
    return s - Math.floor(s);
  };
  const smooth = t => t * t * (3 - 2 * t);
  const noise = (x, y) => {
    const xi = Math.floor(x), yi = Math.floor(y);
    const xf = smooth(x - xi), yf = smooth(y - yi);
    const a = hash(xi, yi), b = hash(xi + 1, yi), c = hash(xi, yi + 1), d = hash(xi + 1, yi + 1);
    return a + (b - a) * xf + (c - a) * yf + (a - b - c + d) * xf * yf;
  };
  const fbm = (x, y) => {
    let total = 0, amplitude = .55, frequency = 1;
    for (let octave = 0; octave < 4; octave++) {
      total += noise(x * frequency, y * frequency) * amplitude;
      amplitude *= .5;
      frequency *= 2.03;
    }
    return total;
  };
  const clamp = value => Math.max(0, Math.min(1, value));

  canvases.forEach(canvas => {
    const context = canvas.getContext('2d');
    const side = canvas.dataset.halftone === 'full' ? 'full' : 'right';
    let width = 0, height = 0, dpr = 1, color = '#e4f222', time = 0, frame = 0, last = 0;
    let pointer = null;

    const resize = () => {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = canvas.clientWidth;
      height = canvas.clientHeight;
      canvas.width = Math.round(width * dpr);
      canvas.height = Math.round(height * dpr);
      color = getComputedStyle(canvas).color || color;
      draw();
    };

    const draw = () => {
      context.setTransform(dpr, 0, 0, dpr, 0, 0);
      context.clearRect(0, 0, width, height);
      context.fillStyle = color;
      const cell = width < 480 ? 8 : 11;
      const half = cell / 2;
      context.beginPath();
      for (let row = 0, y = 0; y < height + cell; row++, y = row * half) {
        for (let x = row % 2 ? half : 0; x < width + cell; x += cell) {
          const u = x / width, v = y / height;
          // Organic edge: the field starts around a quarter across, wobbling with its own low-frequency noise.
          const wobble = (fbm(v * 2.4 + 3.1, time * .6 + 7) - .5) * .7;
          const edge = side === 'full' ? -1 : .2 + v * .22 + wobble;
          const mask = side === 'full' ? .85 : clamp((u - edge) * 4);
          // Broad tonal blobs, broken up by a finer grain so dense areas keep the dotted texture of print halftone.
          const tone = clamp((fbm(x * .0042 + time, y * .0042 - time * .7) - .3) * 2.6);
          const grain = fbm(x * .03 - time * 2, y * .03 + 11);
          // Light islands inside the dense areas, like highlights in a photograph.
          const island = clamp((fbm(x * .009 + 5, y * .009 - time) - .58) * 5);
          let value = clamp(tone * (.55 + grain * .75)) * mask * (1 - island);
          if (pointer) {
            const distance = Math.hypot(x - pointer.x, y - pointer.y);
            value += Math.max(0, 1 - distance / 130) * .45 * Math.max(mask, .25);
          }
          const radius = half * Math.min(value, 1) * .8;
          if (radius < .55) continue;
          context.moveTo(x, y - radius);
          context.lineTo(x + radius, y);
          context.lineTo(x, y + radius);
          context.lineTo(x - radius, y);
          context.closePath();
        }
      }
      context.fill();
    };

    const tick = now => {
      frame = 0;
      if (document.hidden) return;
      // ~30 fps is plenty for a drift this slow.
      if (now - last > 33) {
        time += (now - last || 16) * .000035;
        last = now;
        draw();
      }
      schedule();
    };
    const schedule = () => {
      if (!frame && !reduce.matches && !document.hidden) frame = requestAnimationFrame(tick);
    };

    new ResizeObserver(resize).observe(canvas);
    // Theme switches recolor the dots.
    new MutationObserver(() => requestAnimationFrame(resize)).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    const host = canvas.parentElement;
    host.addEventListener('pointermove', event => {
      const rect = canvas.getBoundingClientRect();
      pointer = { x: event.clientX - rect.left, y: event.clientY - rect.top };
      if (reduce.matches) draw();
    });
    host.addEventListener('pointerleave', () => {
      pointer = null;
      if (reduce.matches) draw();
    });
    document.addEventListener('visibilitychange', () => {
      last = performance.now();
      schedule();
    });
    reduce.addEventListener('change', schedule);
    resize();
    schedule();
  });
})();
