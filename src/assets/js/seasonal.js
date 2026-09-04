(function () {
  'use strict';

  var currentScript = document.currentScript;
  var effect = (currentScript && currentScript.getAttribute('data-effect')) || 'snow';
  if (effect === 'none') return;

  // Respect users who've asked for less motion.
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var canvas = document.createElement('canvas');
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;';
  document.body.appendChild(canvas);
  var ctx = canvas.getContext('2d');

  var W = 0, H = 0;
  function resize() {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }
  window.addEventListener('resize', resize);
  resize();

  var COUNT = W < 640 ? 26 : 55; // keep it subtle, not a blizzard
  var particles = [];

  function rand(min, max) { return Math.random() * (max - min) + min; }

  function makeParticle() {
    if (effect === 'rain') {
      return {
        x: rand(0, W), y: rand(-H, 0),
        len: rand(10, 22), speed: rand(6, 11),
        drift: rand(-0.5, 0.5), opacity: rand(0.15, 0.35),
      };
    }
    if (effect === 'leaves') {
      return {
        x: rand(0, W), y: rand(-H, 0),
        size: rand(6, 11), speed: rand(0.5, 1.4),
        drift: rand(-0.8, 0.8), angle: rand(0, Math.PI * 2), spin: rand(-0.02, 0.02),
        hue: rand(20, 42), opacity: rand(0.5, 0.85),
      };
    }
    // snow (default)
    return {
      x: rand(0, W), y: rand(-H, 0),
      size: rand(1.5, 4), speed: rand(0.4, 1.2),
      drift: rand(-0.4, 0.4), opacity: rand(0.35, 0.8),
    };
  }

  for (var i = 0; i < COUNT; i++) particles.push(makeParticle());

  function drawSnow(p) {
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(255,255,255,' + p.opacity + ')';
    ctx.fill();
    p.y += p.speed;
    p.x += p.drift;
  }

  function drawLeaf(p) {
    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(p.angle);
    ctx.fillStyle = 'hsla(' + p.hue + ', 55%, 42%, ' + p.opacity + ')';
    ctx.beginPath();
    ctx.ellipse(0, 0, p.size, p.size * 0.6, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
    p.y += p.speed;
    p.x += p.drift + Math.sin(p.y / 40) * 0.6;
    p.angle += p.spin;
  }

  function drawRain(p) {
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    ctx.lineTo(p.x + p.drift * 2, p.y + p.len);
    ctx.strokeStyle = 'rgba(180,200,220,' + p.opacity + ')';
    ctx.lineWidth = 1;
    ctx.stroke();
    p.y += p.speed * 4;
    p.x += p.drift;
  }

  var draw = effect === 'rain' ? drawRain : (effect === 'leaves' ? drawLeaf : drawSnow);

  function tick() {
    ctx.clearRect(0, 0, W, H);
    for (var i = 0; i < particles.length; i++) {
      var p = particles[i];
      draw(p);
      if (p.y > H + 20) {
        particles[i] = makeParticle();
        particles[i].y = -20;
      }
    }
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
})();
