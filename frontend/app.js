// --- app.js ---
const API_BASE_URL = 'http://localhost:8000/api';

// DOM helpers
const el = (s, r = document) => r.querySelector(s);
const els = (s, r = document) => Array.from(r.querySelectorAll(s));

// Messages
function showMessage(message, type = 'info') {
  const box = el('#message');
  if (!box) return;
  box.innerHTML = `<div class="${type}">${message}</div>`;
  setTimeout(() => (box.innerHTML = ''), 8000);
}

// Date helpers
function toDDMMYYYY(iso /* yyyy-mm-dd */) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-').map(Number);
  if (!y || !m || !d) return '';
  return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${y}`;
}

function openPicker(id) {
  const input = document.getElementById(id);
  if (!input) return;
  if (typeof input.showPicker === 'function') input.showPicker();
  else input.click();
}

function setMinDepartureFromArrival() {
  const aISO = document.getElementById('arrivalISO');
  const dISO = document.getElementById('departureISO');
  if (!aISO || !dISO || !aISO.value) return;
  const base = new Date(aISO.value);
  if (Number.isNaN(base.getTime())) return;
  const next = new Date(base.getTime() + 24 * 60 * 60 * 1000);
  const minIso = next.toISOString().slice(0, 10);
  dISO.min = minIso;
  if (dISO.value && dISO.value < minIso) {
    dISO.value = minIso;
    el('#departureDisplay').value = toDDMMYYYY(minIso);
  }
}

// Ages UI
function ageRow(defaultValue = '') {
  const row = document.createElement('div');
  row.className = 'age-row';
  row.innerHTML = `
    <input type="number" class="age-input" placeholder="Age" min="0" max="120" value="${defaultValue}" style="width:90px;">
    <button type="button" class="remove-age-btn" title="Remove age">×</button>
  `;
  return row;
}

function syncAgesToOccupants() {
  const occ = parseInt(el('#occupants').value || '0', 10);
  const container = el('#agesContainer');
  const rows = els('.age-row', container);
  const diff = occ - rows.length;
  if (diff > 0) for (let i = 0; i < diff; i++) container.appendChild(ageRow());
  if (diff < 0) for (let i = 0; i < -diff; i++) container.lastElementChild?.remove();
}

function collectAges() {
  return els('.age-input').map(x => {
    const n = parseInt(x.value || 'NaN', 10);
    return Number.isFinite(n) && n >= 0 ? n : null;
  }).filter(n => n !== null);
}

// Results helpers
function formatCurrency(amount, currency = 'NAD') {
  const val = (typeof amount === 'number' && isFinite(amount)) ? amount : 0;
  return new Intl.NumberFormat('en-NA', { style: 'currency', currency }).format(val);
}

function parseDDMMYYYY(s) {
  const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(s || '');
  if (!m) return null;
  const d = parseInt(m[1], 10);
  const mo = parseInt(m[2], 10) - 1;
  const y = parseInt(m[3], 10);
  const dt = new Date(y, mo, d);
  if (dt.getFullYear() !== y || dt.getMonth() !== mo || dt.getDate() !== d) return null;
  return dt;
}

function diffNights(arrivalStr, departureStr) {
  const a = parseDDMMYYYY(arrivalStr);
  const d = parseDDMMYYYY(departureStr);
  if (!a || !d) return 1;
  const ms = d - a;
  const nights = Math.max(1, Math.round(ms / (1000 * 60 * 60 * 24)));
  return nights;
}

// Strict renderer (only green when availability=true AND rate>0)
function displayResults(data) {
  const container = document.getElementById('resultsContainer');

  if (!Array.isArray(data) || data.length === 0) {
    container.innerHTML = `<div class="error">No rates available for the selected criteria.</div>`;
    return;
  }

  const cards = data.map((r) => {
    const unit = r.unit_name || 'Unit';
    const currency = r.currency || 'NAD';

    const arrival = r?.date_range?.arrival || '';
    const departure = r?.date_range?.departure || '';
    const nights = diffNights(arrival, departure);

    const total = (typeof r.rate === 'number') ? r.rate : null;

    // STRICT availability: must be true AND have a positive rate
    const available = !!r.availability && total !== null && total > 0;
    const perNight = (available && nights) ? (total / nights) : null;

    const badgeClass = available ? 'available' : 'unavailable';
    const badgeText = available ? 'Available' : 'Not Available';

    const totalLine = available
      ? `${formatCurrency(total, currency)} <span class="rate-sub">total</span>`
      : `<span class="muted">Rate not available</span>`;

    const perNightLine = (available && perNight && perNight > 0)
      ? `${formatCurrency(perNight, currency)} <span class="rate-sub">per night × ${nights}</span>`
      : `<span class="muted">–</span>`;

    return `
      <div class="rate-card">
        <div class="rate-card-head">
          <h3>${unit}</h3>
          <span class="availability ${badgeClass}">${badgeText}</span>
        </div>

        <div class="rate-price">${totalLine}</div>
        <div class="rate-price secondary">${perNightLine}</div>

        <div class="rate-details">
          <p><strong>Dates:</strong> ${arrival} – ${departure}</p>
          <p><strong>Nights:</strong> ${nights}</p>
        </div>

        ${(!available)
          ? `<div class="hint">No priced availability for the selected inputs. Try other dates/occupants or unit type.</div>`
          : ``}
      </div>
    `;
  }).join('');

  container.innerHTML = `
    <h3 style="margin-bottom: 16px; color: #2c5530;">Available Rates</h3>
    <div class="results-grid">${cards}</div>
  `;
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM loaded, initializing app...');

  // Hook calendar open buttons + clicking display fields
  document.querySelectorAll('[data-open]').forEach(btn => {
    btn.addEventListener('click', () => openPicker(btn.getAttribute('data-open')));
  });
  el('#arrivalDisplay')?.addEventListener('click', () => openPicker('arrivalISO'));
  el('#departureDisplay')?.addEventListener('click', () => openPicker('departureISO'));

  // Mirror ISO -> dd/mm/yyyy on change
  const arrivalISO = el('#arrivalISO');
  const departureISO = el('#departureISO');

  // Set defaults if empty: today/tomorrow
  const today = new Date();
  const tomorrow = new Date(today.getTime() + 24*60*60*1000);
  const toIso = d => d.toISOString().slice(0,10);
  if (!arrivalISO.value) {
    arrivalISO.value = toIso(today);
    el('#arrivalDisplay').value = toDDMMYYYY(arrivalISO.value);
  }
  if (!departureISO.value) {
    departureISO.value = toIso(tomorrow);
    el('#departureDisplay').value = toDDMMYYYY(departureISO.value);
  }
  setMinDepartureFromArrival();

  arrivalISO?.addEventListener('change', () => {
    el('#arrivalDisplay').value = toDDMMYYYY(arrivalISO.value);
    setMinDepartureFromArrival();
  });
  departureISO?.addEventListener('change', () => {
    el('#departureDisplay').value = toDDMMYYYY(departureISO.value);
  });

  // Seed ages from occupants
  syncAgesToOccupants();

  // Occupant controls
  el('[data-inc-occupant]')?.addEventListener('click', () => {
    const inp = el('#occupants');
    inp.value = String((parseInt(inp.value || '0', 10) || 0) + 1);
    syncAgesToOccupants();
  });
  el('[data-dec-occupant]')?.addEventListener('click', () => {
    const inp = el('#occupants');
    const v = Math.max(1, (parseInt(inp.value || '1', 10) || 1) - 1);
    inp.value = String(v);
    syncAgesToOccupants();
  });
  el('#occupants')?.addEventListener('input', syncAgesToOccupants);

  // Add/Clear ages
  const agesContainer = el('#agesContainer');
  el('[data-add-age]')?.addEventListener('click', () => {
    agesContainer.appendChild(ageRow());
    el('#occupants').value = String(els('.age-row', agesContainer).length);
  });
  el('[data-clear-ages]')?.addEventListener('click', () => {
    agesContainer.innerHTML = '';
    el('#occupants').value = '1';
    syncAgesToOccupants();
  });
  // Remove age via delegation
  agesContainer.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-age-btn')) {
      e.target.parentElement.remove();
      el('#occupants').value = String(els('.age-row', agesContainer).length || 1);
      syncAgesToOccupants();
    }
  });

  // Submit
  const form = el('#rateForm');
  const submitBtn = el('#submitBtn');
  const results = el('#resultsContainer');

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const unitName = (el('#unitName')?.value || '').trim();
    const arrivalIso = arrivalISO?.value || '';
    const departureIso = departureISO?.value || '';
    const occupants = parseInt(el('#occupants')?.value || '0', 10);
    const ages = collectAges();

    if (!unitName || !arrivalIso || !departureIso || !occupants || ages.length !== occupants) {
      showMessage(`Please fill all fields. Ages (${ages.length}) must equal occupants (${occupants}).`, 'error');
      return;
    }

    const payload = {
      "Unit Name": unitName,
      "Arrival": toDDMMYYYY(arrivalIso),
      "Departure": toDDMMYYYY(departureIso),
      "Occupants": occupants,
      "Ages": ages
    };

    submitBtn.disabled = true;
    submitBtn.textContent = 'Searching...';
    results.innerHTML = `
      <div class="loading">
        <div class="spinner"></div>
        <p>Searching for available rates...</p>
      </div>`;

    try {
      const resp = await fetch(`${API_BASE_URL}/rates`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      });
      const text = await resp.text();
      const json = JSON.parse(text);

      if (!resp.ok || !json.success) {
        throw new Error(json.error?.message || json.error || `HTTP ${resp.status}`);
      }

      // ✅ Strict renderer only
      displayResults(json.data);
      showMessage('Rates loaded successfully!', 'success');
    } catch (err) {
      console.error(err);
      showMessage(`Error: ${err.message}`, 'error');
      results.innerHTML = '';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Search Rates';
    }
  });

  // Test JSON button
  el('#testBtn')?.addEventListener('click', async () => {
    const testPayload = {
      "Unit Name": "Standard Unit",
      "Arrival": "25/01/2024",
      "Departure": "28/01/2024",
      "Occupants": 2,
      "Ages": [25, 30]
    };
    try {
      const r = await fetch(`${API_BASE_URL}/test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(testPayload)
      });
      const data = await r.json();
      console.log('Test endpoint:', data);
      showMessage('Test completed — check console for details', 'success');
    } catch (e) {
      console.error(e);
      showMessage(`Test failed: ${e.message}`, 'error');
    }
  });

  // Quick connection check
  (async () => {
    try {
      const r = await fetch(`${API_BASE_URL}`, { headers: { 'Accept': 'application/json' } });
      if (!r.ok) throw new Error(`API ${r.status}`);
      showMessage('Connected to API successfully!', 'success');
    } catch {
      showMessage('API connection failed. Ensure backend is on localhost:8000', 'error');
    }
  })();
});
