/* Append to site.js. Native, finite motion; content always visible without JS. */
(() => {
  if (!document.body.classList.contains('brand-sy3') || !Element.prototype.animate) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  if (reduced.matches) return;
  const active = new Set();
  const play = (element, frames, options) => {
    const animation = element.animate(frames, options);
    active.add(animation);
    animation.addEventListener('finish', () => active.delete(animation), { once: true });
    animation.addEventListener('cancel', () => active.delete(animation), { once: true });
    return animation;
  };
  const ease = 'cubic-bezier(.16,1,.3,1)';
  const hero = document.querySelector('.hero-editorial');
  if (hero) {
    // No opacity-zero CSS gate: failed/disabled JS leaves the entire page readable.
    const title = hero.querySelector('h1');
    if (title) play(title, [
      { transform: 'translateY(44px)', clipPath: 'inset(0 0 22% 0)' },
      { transform: 'translateY(0)', clipPath: 'inset(0 0 0 0)' }
    ], { duration: 1000, easing: ease });
    hero.querySelectorAll('.hero-kicker,.hero-bottom,.trust-strip').forEach((element, index) => {
      play(element, [{ opacity: .25, transform: 'translateY(20px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 850, delay: 100 + index * 100, easing: ease, fill: 'backwards' });
    });
  }
  if (!('IntersectionObserver' in window)) return;
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting || reduced.matches) return;
      observer.unobserve(entry.target);
      const section = entry.target;
      const heading = section.querySelector('h2');
      if (heading) play(heading, [{ transform: 'translateY(30px)', opacity: .45 }, { transform: 'translateY(0)', opacity: 1 }],
        { duration: 850, easing: ease });
      section.querySelectorAll('.entry-card,.process-grid>.wp-block-column').forEach((element, index) => {
        play(element, [{ transform: 'translateY(36px)', opacity: .45 }, { transform: 'translateY(0)', opacity: 1 }],
          { duration: 850, delay: Math.min(index, 3) * 75, easing: ease, fill: 'backwards' });
      });
    });
  }, { threshold: .07, rootMargin: '0px 0px -36px 0px' });
  document.querySelectorAll('.homepage>.section').forEach(section => observer.observe(section));
  const stop = () => {
    if (!reduced.matches) return;
    active.forEach(animation => animation.cancel());
    active.clear();
    observer.disconnect();
  };
  if (reduced.addEventListener) reduced.addEventListener('change', stop);
})();
