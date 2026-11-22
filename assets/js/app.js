/* Pawfee Shop — front-end behaviour. Plain JavaScript, no dependencies. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const money = n => (window.CURRENCY || '₱') + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  /* ---- Header: mobile nav + search panel ---- */
  $('[data-nav-toggle]')?.addEventListener('click', () => $('[data-nav]').classList.toggle('open'));
  $('[data-search-toggle]')?.addEventListener('click', () => {
    const p = $('[data-search-panel]'); p.hidden = !p.hidden; if (!p.hidden) p.querySelector('input[type=search]').focus();
  });

  /* ---- Toast ---- */
  function toast(msg, isError) {
    const t = Object.assign(document.createElement('div'), { className: 'toast' + (isError ? ' error' : ''), textContent: msg });
    document.body.appendChild(t); setTimeout(() => t.remove(), 2600);
  }

  /* ---- Add to cart without reloading ---- */
  $$('form[data-ajax-cart]').forEach(form => form.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      toast(data.message, !data.ok);
      const badge = $('[data-cart-count]');
      if (badge && data.count !== undefined) { badge.textContent = data.count; badge.hidden = data.count < 1; }
    } catch { form.submit(); }                       // graceful fallback
  }));

  /* ---- Product page: live price, thumbnails ---- */
  const pf = $('[data-product-form]');
  if (pf) {
    const update = () => {
      let price = Number($('[data-price]').dataset.base), labels = [];
      $$('input[type=radio]:checked', pf).forEach(r => { price += Number(r.dataset.delta || 0); labels.push(r.dataset.label); });
      const qty = Math.max(1, Number($('input[name=qty]', pf).value) || 1);
      $('[data-price]').textContent = money(price);
      $('[data-option-label]').textContent = labels.join(' · ');
      $('[data-total]').textContent = money(price * qty);
    };
    pf.addEventListener('change', update); pf.addEventListener('input', update); pf.addEventListener('click', () => setTimeout(update));
    update();
  }
  $$('[data-thumb]').forEach(b => b.addEventListener('click', () => {
    $('#mainImage').src = b.dataset.thumb; $$('[data-thumb]').forEach(x => x.classList.toggle('active', x === b));
  }));

  /* ---- Quantity steppers (product + cart) ---- */
  $$('.qty').forEach(q => $$('[data-qty]', q).forEach(btn => btn.addEventListener('click', () => {
    const input = $('input', q), min = Number(input.min || 1), max = Number(input.max || 99);
    input.value = Math.min(max, Math.max(min, Number(input.value || 1) + Number(btn.dataset.qty)));
    if (q.matches('[data-autosubmit-form]')) q.submit();
  })));
  $$('[data-autosubmit-form] input').forEach(i => i.addEventListener('change', () => i.form.submit()));
  $$('[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));

  /* ---- Confirm dialogs & double-submit guard ---- */
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
  $$('form').forEach(f => f.addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    const b = $('[data-once]', f); if (b) setTimeout(() => { b.disabled = true; b.textContent = 'Placing order…'; }, 0);
  }));

  /* ---- Form validation with inline messages ---- */
  function showError(field, msg) {
    field.classList.add('invalid');
    let m = field.parentElement.querySelector('.field-error');
    if (!m) { m = Object.assign(document.createElement('span'), { className: 'field-error' }); field.parentElement.appendChild(m); }
    m.textContent = msg;
  }
  function clearError(field) { field.classList.remove('invalid'); field.parentElement.querySelector('.field-error')?.remove(); }

  $$('form[data-validate]').forEach(form => {
    form.addEventListener('input', e => clearError(e.target));
    form.addEventListener('submit', e => {
      let firstBad = null;
      $$('input, select, textarea', form).forEach(f => {
        if (f.disabled || f.type === 'hidden' || f.closest('[hidden]') && !f.closest('[data-step]')) return;
        clearError(f);
        let msg = '';
        if (f.required && !f.value.trim() && f.type !== 'checkbox') msg = 'This field is required.';
        else if (f.type === 'checkbox' && f.required && !f.checked) msg = 'Please tick this box to continue.';
        else if (f.value && !f.checkValidity()) msg = f.type === 'email' ? 'Enter a valid email address.' : f.name === 'password' ? 'Use 8+ characters.' : 'Please check this value.';
        else if (f.name === 'password' && f.value && !(/[A-Za-z]/.test(f.value) && /\d/.test(f.value)) && form.querySelector('[name=confirm]')) msg = 'Include a letter and a number.';
        else if (f.name === 'confirm' && f.value !== form.querySelector('[name=password]')?.value) msg = 'Passwords do not match.';
        if (msg) { showError(f, msg); firstBad = firstBad || f; }
      });
      if (firstBad) {
        e.preventDefault();
        const step = firstBad.closest('[data-step]'); if (step) showStep(form, step.dataset.step);
        firstBad.focus();
      }
    });
  });

  /* ---- Two-step sign up ---- */
  function showStep(form, n) {
    $$('[data-step]', form).forEach(s => s.hidden = s.dataset.step !== String(n));
    $$('[data-step-tab]').forEach(t => { t.classList.toggle('active', t.dataset.stepTab === String(n)); t.classList.toggle('done', Number(t.dataset.stepTab) < Number(n)); });
  }
  $$('form[data-steps]').forEach(form => {
    $('[data-next]', form)?.addEventListener('click', () => {
      let ok = true;
      $$('[data-step="1"] input[required]', form).forEach(f => { clearError(f); if (!f.value.trim()) { showError(f, 'This field is required.'); ok = false; } });
      if (ok) showStep(form, 2);
    });
    $('[data-prev]', form)?.addEventListener('click', e => { e.preventDefault(); showStep(form, 1); });
    if (form.querySelector('[data-step="2"] input[value]:not([type=hidden]):not([value=""])') && document.querySelector('.alert-error')) showStep(form, 2);
  });

  /* ---- Checkout: delivery fee, pickup, same-as-billing ---- */
  const co = $('[data-checkout]');
  if (co) {
    const sync = () => {
      const method = $('input[name=delivery_method]:checked', co);
      const fee = Number(method.dataset.fee), sub = Number($('[data-sub]').dataset.sub), disc = Number($('[data-disc]').dataset.disc);
      $('[data-fee-out]').textContent = money(fee); $('[data-grand]').textContent = money(Math.max(0, sub - disc + fee));
      $('[data-shipping]').hidden = method.value === 'pickup';
      $('[data-ship-fields]').hidden = $('[data-same]').checked;
    };
    co.addEventListener('change', sync); sync();
  }
})();
