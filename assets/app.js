(() => {
  'use strict';

  // ── Print ──────────────────────────────────────────────────
  document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });

  // ── Mobile navigation ──────────────────────────────────────
  const navToggle = document.getElementById('nav-toggle');
  document.querySelectorAll('.rail a').forEach((link) => {
    link.addEventListener('click', () => {
      if (navToggle) navToggle.checked = false;
    });
  });

  // ── Page progress on submit ────────────────────────────────
  const progress = document.getElementById('page-progress');
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      if (progress) progress.classList.add('is-active');
    });
  });

  // ── Money/roll previews, completion, live totals ───────────
  const inputs = Array.from(document.querySelectorAll('.cell-input'));
  if (inputs.length) {
    const idr = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const idr0 = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const ticks = Array.from(document.querySelectorAll('[data-tick]'));
    const completion = document.querySelector('[data-completion]');

    const format = (value, unit) => {
      if (!Number.isFinite(value)) return '';
      if (unit === 'rolls') return `${idr0.format(value)} rolls`;
      return value < 0 ? `-Rp ${idr.format(Math.abs(value))}` : `Rp ${idr.format(value)}`;
    };

    const updateInput = (input) => {
      const raw = input.value.trim();
      const value = raw === '' ? NaN : Number(raw);
      const apiPresent = input.dataset.apiPresent === '1';
      const apiPreview = input.dataset.apiPreview || '';
      const preview = input.parentElement.querySelector('.cell-preview');
      const previewText = raw !== ''
        ? (apiPreview ? `Override: ${format(value, input.dataset.unit)}` : format(value, input.dataset.unit))
        : apiPreview;
      if (preview) preview.textContent = previewText;

      if (input.dataset.required === '1' && input.dataset.touched === '1') {
        input.classList.toggle('is-missing', raw === '' && !apiPresent);
      }
    };

    const updateCompletion = () => {
      const required = inputs.filter((input) => input.dataset.required === '1');
      const missing = required.filter((input) => input.value.trim() === '' && input.dataset.apiPresent !== '1').length;
      const filled = required.length - missing;

      if (completion) completion.textContent = `${filled}/${required.length}`;

      if (ticks.length) {
        const full = required.length ? Math.round((filled / required.length) * ticks.length) : 0;
        ticks.forEach((tick, index) => tick.classList.toggle('on', index < full));
      }
    };

    const updateTotals = () => {
      const sums = {};
      inputs.forEach((input) => {
        const group = input.dataset.group;
        if (!group) return;
        let value = null;
        if (input.value.trim() !== '') {
          value = Number(input.value);
        } else if (input.dataset.apiPresent === '1') {
          value = Number(input.dataset.apiAmount || 0);
        }
        if (Number.isFinite(value) && value !== null) {
          sums[group] = (sums[group] || 0) + value;
        }
      });

      document.querySelectorAll('[data-total-for]').forEach((el) => {
        const group = el.dataset.totalFor;
        const value = sums[group] || 0;
        el.textContent = value < 0
          ? `-Rp ${idr0.format(Math.abs(value))}`
          : `Rp ${idr0.format(value)}`;
      });
    };

    const updateAll = () => {
      inputs.forEach(updateInput);
      updateCompletion();
      updateTotals();
    };

    inputs.forEach((input, index) => {
      input.addEventListener('input', () => {
        input.dataset.touched = '1';
        updateAll();
      });
      input.addEventListener('blur', () => {
        input.dataset.touched = '1';
        updateAll();
      });
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          const next = inputs[index + 1];
          if (next && !next.disabled) {
            event.preventDefault();
            next.focus();
            next.select();
          }
        }
      });
    });

    const form = document.getElementById('entry-form');
    if (form) {
      document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
          event.preventDefault();
          form.requestSubmit();
        }
      });
    }

    updateAll();
  }

  // ── Mapping table filters ──────────────────────────────────
  document.querySelectorAll('[data-filter]').forEach((input) => {
    const list = document.querySelector(input.dataset.filter);
    if (!list) return;
    const rows = Array.from(list.querySelectorAll('tbody tr')).filter((row) => row.dataset.empty !== '1');
    const count = document.querySelector(input.dataset.filterCount || '');

    const apply = () => {
      const query = input.value.trim().toLowerCase();
      let visible = 0;
      rows.forEach((row) => {
        const match = row.textContent.toLowerCase().includes(query);
        row.hidden = !match;
        if (match) visible++;
      });
      if (count) count.textContent = `${visible} of ${rows.length}`;
    };

    input.addEventListener('input', apply);
    apply();
  });
})();
