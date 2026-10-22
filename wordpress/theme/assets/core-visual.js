/* A small native WebGL sculpture. No library, remote requests or tracking. */
(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = matchMedia('(pointer: fine)');
  const normalise = v => { const length = Math.hypot(...v) || 1; return v.map(x => x / length); };
  const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
  const centre = u => [(1.42 + .43 * Math.cos(3 * u)) * Math.cos(2 * u), (1.42 + .43 * Math.cos(3 * u)) * Math.sin(2 * u), .58 * Math.sin(3 * u)];
  const vertexSource = `
    attribute vec3 a_position;
    attribute vec3 a_normal;
    uniform mat3 u_rotation;
    uniform vec2 u_viewport;
    varying vec3 v_normal;
    varying vec3 v_position;
    void main() {
      vec3 p = u_rotation * a_position;
      float depth = 7.4 - p.z;
      vec2 scale = 2.92 * min(u_viewport.x, u_viewport.y) / u_viewport;
      gl_Position = vec4(p.x * scale.x, -p.y * scale.y + .07 * depth, 1.01005 * depth - .201005, depth);
      v_normal = u_rotation * a_normal;
      v_position = p;
    }
  `;
  const fragmentSource = `
    precision mediump float;
    varying vec3 v_normal;
    varying vec3 v_position;
    void main() {
      vec3 n = normalize(v_normal);
      vec3 view = normalize(vec3(0., 0., 7.4) - v_position);
      vec3 light = normalize(vec3(-.4, -.65, 1.));
      float diffuse = max(0., dot(n, light));
      float highlight = pow(max(0., dot(n, normalize(light + view))), 72.);
      float softbox = pow(max(0., dot(n, normalize(vec3(.8, -.2, .6) + view))), 18.);
      float edge = pow(1. - abs(dot(n, view)), 3.);
      float band = smoothstep(.72, .83, dot(n, normalize(vec3(-.3, -.5, .8)))) * (1. - smoothstep(.89, .96, dot(n, normalize(vec3(-.3, -.5, .8)))));
      vec3 colour = vec3(.045, .105, .16) + diffuse * vec3(.15, .32, .42);
      colour += highlight * vec3(.8, .94, 1.) + softbox * vec3(.07, .14, .19);
      colour += edge * vec3(.08, .2, .28) + band * vec3(.12, .21, .27);
      gl_FragColor = vec4(colour, 1.);
    }
  `;

  document.querySelectorAll('.system-visual').forEach(stage => {
    const canvas = stage.querySelector('canvas');
    const gl = canvas?.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: false, preserveDrawingBuffer: true, powerPreference: 'low-power' });
    if (!gl) return;
    function compile(type, source) {
      const shader = gl.createShader(type);
      gl.shaderSource(shader, source); gl.compileShader(shader);
      if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) { gl.deleteShader(shader); return null; }
      return shader;
    }
    const vertex = compile(gl.VERTEX_SHADER, vertexSource);
    const fragment = compile(gl.FRAGMENT_SHADER, fragmentSource);
    if (!vertex || !fragment) return;
    const program = gl.createProgram();
    gl.attachShader(program, vertex); gl.attachShader(program, fragment); gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return;
    gl.useProgram(program); gl.enable(gl.DEPTH_TEST); gl.clearColor(0, 0, 0, 0);
    const uCount = 180, vCount = 40, points = [], normals = [];
    for (let i = 0; i < uCount; i++) {
      const u = i / uCount * Math.PI * 2, c = centre(u), next = centre(u + .001);
      const tangent = normalise(next.map((x, j) => x - c[j]));
      const binormal = normalise(cross(tangent, [Math.cos(2 * u), Math.sin(2 * u), 0]));
      const normal = normalise(cross(binormal, tangent));
      for (let j = 0; j < vCount; j++) {
        const v = j / vCount * Math.PI * 2;
        const n = normal.map((x, k) => Math.cos(v) * x + Math.sin(v) * binormal[k]);
        points.push(c.map((x, k) => x + .265 * n[k])); normals.push(n);
      }
    }
    const positions = [], surfaceNormals = [];
    for (let i = 0; i < uCount; i++) for (let j = 0; j < vCount; j++) {
      const a = i * vCount + j, b = ((i + 1) % uCount) * vCount + j;
      const c = ((i + 1) % uCount) * vCount + (j + 1) % vCount, d = i * vCount + (j + 1) % vCount;
      for (const index of [a, b, c, a, c, d]) { positions.push(...points[index]); surfaceNormals.push(...normals[index]); }
    }
    function buffer(name, data) {
      const value = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, value);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(data), gl.STATIC_DRAW);
      const attribute = gl.getAttribLocation(program, name);
      gl.enableVertexAttribArray(attribute); gl.vertexAttribPointer(attribute, 3, gl.FLOAT, false, 0, 0);
    }
    buffer('a_position', positions); buffer('a_normal', surfaceNormals);
    const rotationUniform = gl.getUniformLocation(program, 'u_rotation');
    const viewportUniform = gl.getUniformLocation(program, 'u_viewport');
    const button = stage.querySelector('.visual-pause');
    let width = 1, height = 1, raf = 0, lastFrame = 0, phase = 0;
    let paused = false, visible = true, lost = false, mouseX = 0, mouseY = 0;

    function render() {
      if (lost) return;
      const x = 1.02 + Math.sin(phase * .4) * .06 + mouseY * .12;
      const y = -.25 + phase * .09 + mouseX * .15, z = -.42;
      const sx = Math.sin(x), cx = Math.cos(x), sy = Math.sin(y), cy = Math.cos(y), sz = Math.sin(z), cz = Math.cos(z);
      function rotate([a, b, c]) {
        const b1 = b * cx - c * sx, c1 = b * sx + c * cx;
        const a2 = a * cy + c1 * sy, c2 = -a * sy + c1 * cy;
        return [a2 * cz - b1 * sz, a2 * sz + b1 * cz, c2];
      }
      gl.uniformMatrix3fv(rotationUniform, false, new Float32Array([...rotate([1, 0, 0]), ...rotate([0, 1, 0]), ...rotate([0, 0, 1])]));
      gl.uniform2f(viewportUniform, width, height);
      gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
      gl.drawArrays(gl.TRIANGLES, 0, positions.length / 3);
    }
    function tick(now) {
      if (now - lastFrame >= 40) { if (lastFrame) phase += Math.min((now - lastFrame) / 1000, .08); lastFrame = now; render(); }
      raf = requestAnimationFrame(tick);
    }
    function schedule() {
      cancelAnimationFrame(raf); raf = 0; lastFrame = 0;
      if (visible && !paused && !lost && !reduce.matches && !document.hidden) raf = requestAnimationFrame(tick); else render();
    }
    function resize() {
      const box = stage.getBoundingClientRect(); width = Math.max(1, box.width); height = Math.max(1, box.height);
      const dpr = Math.min(devicePixelRatio || 1, 2);
      canvas.width = Math.round(width * dpr); canvas.height = Math.round(height * dpr);
      gl.viewport(0, 0, canvas.width, canvas.height); render();
    }
    button?.addEventListener('click', () => {
      paused = !paused; button.setAttribute('aria-pressed', String(paused));
      button.setAttribute('aria-label', paused ? 'Продолжить анимацию' : 'Остановить анимацию');
      button.innerHTML = paused ? 'Движение <span aria-hidden="true">▷</span>' : 'Пауза <span aria-hidden="true">Ⅱ</span>';
      schedule();
    });
    stage.addEventListener('pointermove', event => {
      if (!finePointer.matches || reduce.matches) return;
      const r = stage.getBoundingClientRect(); mouseX = (event.clientX - r.left) / r.width - .5; mouseY = (event.clientY - r.top) / r.height - .5;
      if (paused) render();
    }, { passive: true });
    stage.addEventListener('pointerleave', () => { mouseX = 0; mouseY = 0; });
    const motionChange = () => { if (button) button.hidden = reduce.matches; schedule(); };
    reduce.addEventListener('change', motionChange);
    document.addEventListener('visibilitychange', schedule);
    new ResizeObserver(resize).observe(stage);
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; schedule(); }, { rootMargin: '60px' }).observe(stage);
    canvas.addEventListener('webglcontextlost', event => { event.preventDefault(); lost = true; cancelAnimationFrame(raf); stage.classList.remove('has-canvas'); });
    resize(); stage.classList.add('has-canvas'); motionChange();
    addEventListener('pagehide', () => cancelAnimationFrame(raf), { once: true });
  });
})();
