document.addEventListener('DOMContentLoaded', () => {
  const API_BASE = 'http://localhost:8000/api';

  // Elements
  const form = document.getElementById('rateForm');
  const messageDiv = document.getElementById('message');
  const resultsContainer = document.getElementById('resultsContainer');
  const testBtn = document.getElementById('testBtn');

  const unitNameEl = document.getElementById('unitName');
  const occupantsEl = document.getElementById('occupants');

  // Date inputs
  const arrivalISO = document.getElementById('arrivalISO');
  const arrivalDisplay = document.getElementById('arrivalDisplay');
  const departureISO = document.getElementById('departureISO');
  const departureDisplay = document.getElementById('departureDisplay');

  // Ages
  const agesContainer = document.getElementById('agesContainer');
  const btnAddAge = document.querySelector('[data-add-age]');
  const btnClearAges = document.querySelector('[data-clear-ages]');
  const btnIncOcc = document.querySelector('[data-inc-occupant]');
  const btnDecOcc = document.querySelector('[data-dec-occupant]');

  // Helpers
  function showMessage(msg, type = 'success') {
    messageDiv.innerHTML = `<div class="${type}">${msg}</div>`;
  }
  function clearMessage() { messageDiv.innerHTML = ''; }

  function formatDateToDisplay(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
  }

  function parseDmy(dmy) {
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(dmy || '')) return null;
    const [d, m, y] = dmy.split('/').map((s) => parseInt(s, 10));
    const date = new Date(y, m - 1, d);
    if (date.getFullYear() !== y || date.getMonth() !== (m - 1) || date.getDate() !== d) return null;
    return date;
  }

  function computeNights(arrivalDmy, departureDmy) {
    const a = parseDmy(arrivalDmy);
    const d = parseDmy(departureDmy);
    if (!a || !d) return '–';
    const ms = d - a;
    return Math.max(1, Math.round(ms / (1000 * 60 * 60 * 24)));
  }

  function getAgesArray() {
    return Array.from(agesContainer.querySelectorAll('.age-input'))
      .map((el) => parseInt(el.value, 10))
      .filter((n) => Number.isFinite(n) && n >= 0 && n <= 150);
  }

  function makeAgeChip(val) {
    const wrap = document.createElement('div');
    wrap.className = 'age-row';
    wrap.innerHTML = `
      <input type="number" min="0" max="150" value="${val}" class="age-input" style="width:90px;padding:8px;border:2px solid #d4edda;border-radius:8px;">
      <button type="button" class="remove-age-btn">×</button>
    `;
    wrap.querySelector('.remove-age-btn').addEventListener('click', () => {
      wrap.remove();
      const count = agesContainer.querySelectorAll('.age-input').length;
      occupantsEl.value = Math.max(1, count);
    });
    return wrap;
  }

  function syncAgesToOccupants() {
    const occ = Math.max(1, parseInt(occupantsEl.value || '1', 10));
    const current = agesContainer.querySelectorAll('.age-input').length;

    if (current < occ) {
      for (let i = 0; i < occ - current; i++) {
        agesContainer.appendChild(makeAgeChip(18));
      }
    } else if (current > occ) {
      const chips = Array.from(agesContainer.querySelectorAll('.age-row'));
      for (let i = 0; i < current - occ; i++) {
        const last = chips.pop();
        last && last.remove();
      }
    }
  }

  function buildPayload() {
    return {
      'Unit Name': unitNameEl.value,
      'Arrival': arrivalDisplay.value.trim(),
      'Departure': departureDisplay.value.trim(),
      'Occupants': parseInt(occupantsEl.value, 10),
      'Ages': getAgesArray()
    };
  }

  function validatePayload(p) {
    const errs = [];
    if (!p['Unit Name']) errs.push('Unit Name is required');
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(p['Arrival'])) errs.push('Arrival must be dd/mm/yyyy');
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(p['Departure'])) errs.push('Departure must be dd/mm/yyyy');
    if (!(p['Occupants'] > 0)) errs.push('Occupants must be greater than 0');
    if (!Array.isArray(p['Ages']) || p['Ages'].length !== p['Occupants']) {
      errs.push('Ages must equal number of occupants');
    }
    return errs;
  }

  // Date syncing
  arrivalISO.addEventListener('change', () => { arrivalDisplay.value = formatDateToDisplay(arrivalISO.value); });
  departureISO.addEventListener('change', () => { departureDisplay.value = formatDateToDisplay(departureISO.value); });
  document.querySelectorAll('.date-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.open);
      if (!target) return;
      if (typeof target.showPicker === 'function') target.showPicker(); else target.focus();
    });
  });

  // Occupants controls
  btnIncOcc?.addEventListener('click', () => { occupantsEl.value = Math.max(1, parseInt(occupantsEl.value || '1', 10) + 1); syncAgesToOccupants(); });
  btnDecOcc?.addEventListener('click', () => { occupantsEl.value = Math.max(1, parseInt(occupantsEl.value || '1', 10) - 1); syncAgesToOccupants(); });
  occupantsEl.addEventListener('input', syncAgesToOccupants);

  // Ages controls
  btnAddAge?.addEventListener('click', () => {
    agesContainer.appendChild(makeAgeChip(18));
    const count = agesContainer.querySelectorAll('.age-input').length;
    occupantsEl.value = Math.max(1, count);
  });
  btnClearAges?.addEventListener('click', () => {
    agesContainer.innerHTML = '';
    occupantsEl.value = 1;
    syncAgesToOccupants();
  });

  // Results
  function renderResults(data, payload) {
    resultsContainer.innerHTML = '';

    if (!data || !Array.isArray(data)) {
      resultsContainer.innerHTML = '<div class="error">No valid results returned.</div>';
      return;
    }

    data.forEach((item) => {
      const card = document.createElement('div');
      card.className = 'rate-card';

      const unitTitle = item?.unit_name ?? payload?.['Unit Name'] ?? 'Unit';
      const dr = item?.date_range || {};
      const arrival = dr.arrival ?? (payload?.['Arrival'] ?? '–');
      const departure = dr.departure ?? (payload?.['Departure'] ?? '–');
      const nights = (typeof dr.nights !== 'undefined' && dr.nights !== null && dr.nights !== '')
        ? dr.nights : computeNights(arrival, departure);
      const occupants = item?.occupants ?? payload?.Occupants ?? '';

      const rateNum = typeof item?.rate === 'number' ? item.rate : NaN;
      const availability = item?.availability;

      const isPriced = Number.isFinite(rateNum) && rateNum > 0 && availability !== false;

      const parts = [];
      parts.push(`<h3>${unitTitle}</h3>`);

      if (isPriced) {
        // AVAILABLE badge + price
        parts.push(`<div class="availability available">AVAILABLE</div>`);
        const price = Number(rateNum).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const currency = item?.currency ?? 'NAD';
        parts.push(`<div class="rate-price">${price} <span class="rate-sub">${currency}</span></div>`);
      } else {
        // UNAVAILABLE badge + reasons
        parts.push(`<div class="availability unavailable">UNAVAILABLE</div>`);
        parts.push(`<div>Rate not available</div>`);
      }

      parts.push(`<div class="muted"><strong>Dates:</strong> ${arrival} – ${departure}</div>`);
      parts.push(`<div class="muted"><strong>Occupants:</strong> ${occupants} ,<strong>Nights:</strong> ${nights}</div>`);

      if (isPriced) {
        // CTA line under priced card
        parts.push(`<div class="muted">Book now for your advanture</div>`);
      } else {
        // Guidance under unpriced card
        parts.push(`<div class="muted">No priced availability for the selected inputs. Try other dates/occupants or unit type.</div>`);
      }

      card.innerHTML = parts.join('\n');
      resultsContainer.appendChild(card);
    });
  }

  // Submit
  async function submitForm(e) {
    e.preventDefault();
    clearMessage();

    const payload = buildPayload();
    const errs = validatePayload(payload);
    if (errs.length) { showMessage(errs[0], 'error'); return; }

    try {
      const res = await fetch(`${API_BASE}/rates`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (json.success) {
        showMessage('Rates fetched successfully!', 'success');
        renderResults(json.data, payload);
      } else {
        showMessage(json.error?.message ?? 'Unknown error', 'error');
      }
    } catch (err) {
      console.error(err);
      showMessage(err.message, 'error');
    }
  }

  // Test
  async function testEndpoint() {
    clearMessage();
    try {
      const res = await fetch(`${API_BASE}/test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ping: 'ok' })
      });
      const json = await res.json();
      if (json.success) {
        showMessage('Test endpoint success', 'success');
        resultsContainer.innerHTML = `<pre>${JSON.stringify(json, null, 2)}</pre>`;
      } else {
        showMessage('Test endpoint failed', 'error');
      }
    } catch (err) {
      console.error(err);
      showMessage(err.message, 'error');
    }
  }

  form.addEventListener('submit', submitForm);
  testBtn.addEventListener('click', testEndpoint);

  // Init defaults
  agesContainer.innerHTML = '';
  agesContainer.appendChild(makeAgeChip(30));
  agesContainer.appendChild(makeAgeChip(28));
  syncAgesToOccupants();
});
