const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../plugin/sy3-measurement-assets/goals.js'), 'utf8');

function setup(host = 'sy3.ru') {
  const listeners = {}, calls = [];
  class Element {
    constructor(href) { this.href = href; }
    closest() { return this; }
  }
  const document = {
    getElementById: () => ({ dataset: { counter: '55563139' } }),
    addEventListener: (name, fn) => { listeners[name] = fn; },
  };
  const window = { ym: (...args) => calls.push(args) };
  vm.runInNewContext(source, { document, window, Element, URL, location: { protocol: 'https:', hostname: host, port: '' } });
  return { calls, listeners, window, click: (href, extra = {}) => listeners.click({
    isTrusted: true, button: 0, defaultPrevented: false, target: new Element(href), ...extra,
  }) };
}

test('only exact support links create messenger goals; no identities or URL params leave', () => {
  const s = setup();
  s.click('https://t.me/sy3_support_bot?start=site_sy3');
  s.click('https://max.ru/id263514840335_1_bot?start=site_sy3');
  s.click('https://t.me/another_bot');
  s.click('https://max.ru.evil.example/id263514840335_1_bot');
  s.click('https://t.me:8443/sy3_support_bot');
  s.click('https://user:pass@t.me/sy3_support_bot');
  s.click('http://t.me/sy3_support_bot');
  assert.deepEqual(s.calls, [[55563139, 'reachGoal', 'sy3_telegram_click'], [55563139, 'reachGoal', 'sy3_max_click']]);
});

test('cancelled, synthetic and secondary clicks do not count', () => {
  const s = setup();
  s.click('https://t.me/sy3_support_bot', { isTrusted: false });
  s.click('https://t.me/sy3_support_bot', { defaultPrevented: true });
  s.click('https://t.me/sy3_support_bot', { button: 2 });
  assert.equal(s.calls.length, 0);
});

test('accepted lead is deduplicated and a honeypot decoy/invalid reference never counts', () => {
  const s = setup();
  for (const reference of [undefined, '', 'spam', 'SY3-0123456789AB', 'SY3-0123456789AB']) {
    s.listeners['sy3:lead-accepted']({ detail: { reference } });
  }
  assert.deepEqual(s.calls, [[55563139, 'reachGoal', 'sy3_lead_accepted']]);
});

test('preview domains are excluded and unavailable analytics cannot break contact', () => {
  assert.equal(Object.keys(setup('preview.sy3.ru').listeners).length, 0);
  const s = setup();
  s.window.ym = () => { throw Error('blocked analytics'); };
  assert.doesNotThrow(() => s.click('https://t.me/sy3_support_bot'));
  delete s.window.ym;
  assert.doesNotThrow(() => s.click('https://max.ru/id263514840335_1_bot'));
});
