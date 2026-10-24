/* Only fixed conversion names leave the page; never form values or identities. */
(() => {
  'use strict';
  if (location.protocol !== 'https:' || location.hostname !== 'sy3.ru' || location.port) return;
  const counter = document.getElementById('sy3-yandex-metrica')?.dataset.counter;
  if (!/^[1-9][0-9]{0,14}$/.test(counter || '')) return;
  const accepted = new Set();
  const reach = (name) => {
    try {
      if (typeof window.ym === 'function') window.ym(Number(counter), 'reachGoal', name);
    } catch { /* Analytics must never interrupt a contact action. */ }
  };
  document.addEventListener('click', (event) => {
    if (!event.isTrusted || event.button !== 0 || event.defaultPrevented) return;
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link) return;
    let url;
    try { url = new URL(link.href); } catch { return; }
    if (url.protocol !== 'https:' || url.username || url.password || url.port) return;
    if (url.hostname === 't.me' && /^\/sy3_support_bot\/?$/.test(url.pathname)) reach('sy3_telegram_click');
    if (url.hostname === 'max.ru' && /^\/id263514840335_1_bot\/?$/.test(url.pathname)) reach('sy3_max_click');
  });
  document.addEventListener('sy3:lead-accepted', (event) => {
    const reference = event.detail?.reference;
    if (typeof reference !== 'string' || !/^SY3-[A-F0-9]{12}$/.test(reference) || accepted.has(reference)) return;
    accepted.add(reference);
    reach('sy3_lead_accepted');
  });
})();
