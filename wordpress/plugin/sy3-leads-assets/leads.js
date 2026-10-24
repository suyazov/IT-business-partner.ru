/* Fresh uncached challenges and a stable request token across network retries. */
document.querySelectorAll('.sy3-lead-form').forEach((form) => {
  if (form.dataset.standalone) return;
  const button = form.querySelector('button[type="submit"]');
  const status = form.querySelector('.sy3-lead-status');
  let challengePromise;
  let complete = false;
  const setStatus = (message, error = false) => {
    status.textContent = message;
    status.classList.toggle('is-error', error);
  };
  const challenge = () => {
    if (!challengePromise) challengePromise = (async () => {
      const url = new URL(form.dataset.challenge);
      url.searchParams.set('page', window.location.pathname);
      const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
      const value = await response.json();
      if (!response.ok || !value.token || !value.nonce) throw new Error('challenge');
      form.elements.token.value = value.token;
      form.elements.nonce.value = value.nonce;
    })().catch((error) => { challengePromise = null; throw error; });
    return challengePromise;
  };
  form.addEventListener('focusin', () => { challenge().catch(() => {}); }, { once: true });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (complete || button.disabled || !form.reportValidity()) return;
    button.disabled = true;
    button.textContent = 'Сохраняем заявку…';
    setStatus('');
    try {
      await challenge();
      const fields = new FormData(form);
      const payload = Object.fromEntries([...fields.entries()].filter(([key]) => key !== 'action'));
      payload.utm = {};
      const parameters = new URLSearchParams(window.location.search);
      ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach((key) => {
        if (parameters.has(key)) payload.utm[key] = parameters.get(key).slice(0, 120);
      });
      const response = await fetch(form.dataset.submit, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        if (result.code === 'token') { challengePromise = null; await challenge(); }
        setStatus(result.message || 'Не удалось сохранить заявку. Попробуйте ещё раз.', true);
      } else {
        complete = true;
        // Honeypot decoys have no reference and must never count as real leads.
        if (typeof result.reference === 'string' && /^SY3-[A-F0-9]{12}$/.test(result.reference)) {
          document.dispatchEvent(new CustomEvent('sy3:lead-accepted', { detail: { reference: result.reference } }));
        }
        setStatus(result.message + (result.reference ? ` Номер: ${result.reference}.` : ''));
        button.textContent = 'Заявка сохранена';
        status.focus({ preventScroll: true });
      }
    } catch {
      // Keep the same token: an interrupted response may follow an already saved request.
      setStatus('Не удалось проверить отправку. Повторите попытку или свяжитесь с Артёмом напрямую.', true);
    } finally {
      if (!complete) { button.disabled = false; button.textContent = 'Отправить заявку'; }
    }
  });
});
