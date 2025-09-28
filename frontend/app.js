document.addEventListener('DOMContentLoaded', () => {
  // ---------- API base (robust, HTML-safe) ----------
  const bodyApi = (document.body && document.body.dataset && document.body.dataset.api) || null;
  const storedApi = localStorage.getItem('API_BASE') || null;
  const host8000 = `http://${location.hostname}:8000/api`;
  const candidates = [bodyApi, storedApi, host8000, 'http://localhost:8000/api', `${location.origin}/api`].filter(Boolean);

  let API_BASE = candidates[0] || 'http://localhost:8000/api';
  let apiLocked = false;

  async function safeJson(res) {
    const text = await res.text();
    const isHtml = /^\s*</.test(text);
    try { return { json: JSON.parse(text), raw: text, html: isHtml }; }
    catch { return { json: null, raw: text, html: isHtml }; }
  }
  async function probe(base) {
    try {
      const r = await fetch(`${base}/test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ ping: 'probe' }),
      });
      const { json, html } = await safeJson(r);
      return r.ok && !html && json && typeof json === 'object';
    } catch { return false; }
  }
  async function ensureApiBase() {
    if (apiLocked) return API_BASE;
    for (const base of candidates) {
      if (await probe(base)) {
        API_BASE = base;
        localStorage.setItem('API_BASE', base);
        apiLocked = true;
        return API_BASE;
      }
    }
    apiLocked = true;
    return API_BASE;
  }

  // ---------- DOM ----------
  const form = document.getElementById('rateForm');
  const messageDiv = document.getElementById('message');
  const resultsContainer = document.getElementById('resultsContainer');
  const testBtn = document.getElementById('testBtn');

  const unitNameEl = document.getElementById('unitName');
  const occupantsEl = document.getElementById('occupants');

  const arrivalISO = document.getElementById('arrivalISO');
  const arrivalDisplay = document.getElementById('arrivalDisplay');
  const departureISO = document.getElementById('departureISO');
  const departureDisplay = document.getElementById('departureDisplay');

  const agesContainer = document.getElementById('agesContainer');
  const btnAddAge = document.querySelector('[data-add-age]');
  const btnClearAges = document.querySelector('[data-clear-ages]');
  const btnIncOcc = document.querySelector('[data-inc-occupant]');
  const btnDecOcc = document.querySelector('[data-dec-occupant]');

  // Booking modal
  const modal = document.getElementById('resultModal');
  const modalTbody = document.getElementById('modalTbody');
  const modalTotal = document.getElementById('modalTotal');
  const modalClose = document.getElementById('modalClose');
  const modalConfirm = document.getElementById('modalConfirm');
  const modalPrint = document.getElementById('modalPrint');
  const modalRefEl = document.getElementById('modalRef');
  const printMeta = document.getElementById('printMeta');
  const printHeaderRef = document.getElementById('printHeaderRef');

  // Date-fix modal
  const dateModal = document.getElementById('dateModal');
  const dateModalMsg = document.getElementById('dateModalMsg');
  const dateModalPick = document.getElementById('dateModalPick');
  const dateModalClose = document.getElementById('dateModalClose');
  let dateFixTarget = null; // 'arrival' | 'departure'

  // State
  let canConfirm = false;

  // ---------- UI helpers ----------
  function showMessage(msg, type = 'success') {
    messageDiv.innerHTML = `<div class="${type}">${msg}</div>`;
  }
  function clearMessage() { messageDiv.innerHTML = ''; }
  function showToast(msg) {
    const host = document.getElementById('toastHost');
    if (!host) return;
    const el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.innerHTML = `<span class="icon">✅</span><span>${msg}</span>`;
    host.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }

  // ---------- Date utilities ----------
  function todayISO() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
  function addDaysISO(iso, days) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
    const dt = new Date(iso + 'T00:00:00');
    dt.setDate(dt.getDate() + days);
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const d = String(dt.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
  function isPastISO(iso) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return true;
    return new Date(iso + 'T00:00:00') < new Date(todayISO() + 'T00:00:00');
  }
  function formatDateToDisplay(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
    const [y,m,d] = iso.split('-'); return `${d}/${m}/${y}`;
  }
  function dmyToIso(dmy) {
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(dmy||'')) return '';
    const [d,m,y] = dmy.split('/');
    return `${y}-${m}-${d}`;
  }
  function parseDmy(dmy) {
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(dmy || '')) return null;
    const [d, m, y] = dmy.split('/').map((s) => parseInt(s, 10));
    const dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) return null;
    return dt;
  }
  function computeNights(aDmy, dDmy) {
    const a = parseDmy(aDmy), d = parseDmy(dDmy);
    if (!a || !d) return '–';
    return Math.max(1, Math.round((d - a) / (1000 * 60 * 60 * 24)));
  }

  // ---------- Friendly formatting ----------
  function prettifyUnitName(name) {
    const s = String(name || '').trim();
    if (!s || /^not\s*available$/i.test(s) || s.toUpperCase() === 'NOTAVAILABLE') return 'Unit unavailable';
    return s;
  }
  function formatMoney(n, currency = 'NAD') {
    if (!Number.isFinite(n) || n <= 0) return '—';
    return `${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
  }

  // ---------- Ages chips ----------
  function makeAgeChip(val) {
    const wrap = document.createElement('div');
    wrap.className = 'age-row';
    wrap.innerHTML = `
      <input type="number" min="0" max="150" value="${val}" class="age-input"
             style="width:90px;padding:8px;border:2px solid #d4edda;border-radius:8px;">
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
    if (current < occ) for (let i = 0; i < occ - current; i++) agesContainer.appendChild(makeAgeChip(18));
    else if (current > occ) {
      const chips = Array.from(agesContainer.querySelectorAll('.age-row'));
      for (let i = 0; i < current - occ; i++) { const last = chips.pop(); last && last.remove(); }
    }
  }

  // ---------- Payload & validation ----------
  function buildPayload() {
    return {
      'Unit Name': unitNameEl.value,
      'Arrival': arrivalDisplay.value.trim(),
      'Departure': departureDisplay.value.trim(),
      'Occupants': parseInt(occupantsEl.value, 10),
      'Ages': Array.from(agesContainer.querySelectorAll('.age-input'))
        .map((el) => parseInt(el.value, 10))
        .filter((n) => Number.isFinite(n) && n >= 0 && n <= 150)
    };
  }
  function validatePayload(p) {
    const errs = [];
    if (!p['Unit Name']) errs.push('Unit Name is required');
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(p['Arrival'])) errs.push('Arrival must be dd/mm/yyyy');
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(p['Departure'])) errs.push('Departure must be dd/mm/yyyy');
    if (!(p['Occupants'] > 0)) errs.push('Occupants must be greater than 0');
    if (!Array.isArray(p['Ages']) || p['Ages'].length !== p['Occupants']) errs.push('Ages must equal number of occupants');
    return errs;
  }

  // Extra: robust date rule validation for popup
  function validateDatesForPopup(arrivalDmy, departureDmy) {
    const aIso = dmyToIso(arrivalDmy);
    const dIso = dmyToIso(departureDmy);
    const today = todayISO();

    if (!aIso) return { ok:false, which:'arrival', msg:`Please choose a valid arrival date in dd/mm/yyyy.` };
    if (!dIso) return { ok:false, which:'departure', msg:`Please choose a valid departure date in dd/mm/yyyy.` };

    if (isPastISO(aIso)) {
      return { ok:false, which:'arrival', msg:`Arrival cannot be in the past. Pick a date on or after ${formatDateToDisplay(today)}.` };
    }
    // departure must be strictly after arrival
    if (new Date(dIso+'T00:00:00') <= new Date(aIso+'T00:00:00')) {
      const minDep = addDaysISO(aIso, 1);
      return { ok:false, which:'departure', msg:`Departure must be after arrival. Choose a date from ${formatDateToDisplay(minDep)}.` };
    }
    return { ok:true };
  }

  // ---------- Date pickers & min constraints ----------
  function applyMinConstraints() {
    const today = todayISO();
    arrivalISO.min = today;
    // departure min depends on arrival (if set) else today+1
    const a = arrivalISO.value && !isPastISO(arrivalISO.value) ? arrivalISO.value : today;
    departureISO.min = addDaysISO(a, 1);
  }
  function syncDisplaysFromISO() {
    arrivalDisplay.value = formatDateToDisplay(arrivalISO.value);
    departureDisplay.value = formatDateToDisplay(departureISO.value);
  }

  // Init min constraints on load
  applyMinConstraints();

  // Date syncing
  arrivalISO.addEventListener('change', () => {
    // Set min for departure to arrival+1 and clear invalid departure
    applyMinConstraints();
    const minDep = departureISO.min;
    if (departureISO.value && departureISO.value < minDep) {
      departureISO.value = '';
      departureDisplay.value = '';
    }
    arrivalDisplay.value = formatDateToDisplay(arrivalISO.value);
  });
  departureISO.addEventListener('change', () => {
    departureDisplay.value = formatDateToDisplay(departureISO.value);
  });

  // Calendar open buttons
  document.querySelectorAll('.date-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.open);
      if (!target) return;
      if (typeof target.showPicker === 'function') target.showPicker(); else target.focus();
    });
  });

  // Occupants + ages
  btnIncOcc?.addEventListener('click', () => { occupantsEl.value = Math.max(1, parseInt(occupantsEl.value || '1', 10) + 1); syncAgesToOccupants(); });
  btnDecOcc?.addEventListener('click', () => { occupantsEl.value = Math.max(1, parseInt(occupantsEl.value || '1', 10) - 1); syncAgesToOccupants(); });
  occupantsEl.addEventListener('input', syncAgesToOccupants);
  btnAddAge?.addEventListener('click', () => { agesContainer.appendChild(makeAgeChip(18)); occupantsEl.value = Math.max(1, agesContainer.querySelectorAll('.age-input').length); });
  btnClearAges?.addEventListener('click', () => { agesContainer.innerHTML = ''; occupantsEl.value = 1; syncAgesToOccupants(); });

  // ---------- Booking summary modal helpers ----------
  function makeRef() {
    const dt = new Date();
    const y = dt.getFullYear(), m = String(dt.getMonth() + 1).padStart(2, '0'), d = String(dt.getDate()).padStart(2, '0');
    const h = String(dt.getHours()).padStart(2, '0'), i = String(dt.getMinutes()).padStart(2, '0'), s = String(dt.getSeconds()).padStart(2, '0');
    const rand = Math.random().toString(36).slice(2, 6).toUpperCase();
    return `GW-${y}${m}${d}-${h}${i}${s}-${rand}`;
  }
  function fillPrintMeta(rows, payload) {
    const units = [...new Set(rows.map((r) => r.unit))];
    const unitSummary = units.length > 1 ? `${units[0]} +${units.length - 1} more`
      : (units[0] || prettifyUnitName(payload?.['Unit Name']) || 'Unit');
    const arrival = rows[0]?.arrival ?? payload?.['Arrival'] ?? '–';
    const departure = rows[0]?.departure ?? payload?.['Departure'] ?? '–';
    const nights = rows[0]?.nights ?? '–';
    const occupants = payload?.['Occupants'] ?? '–';
    const prepared = new Date().toLocaleString();

    printMeta.innerHTML = `
      <div><strong>Unit</strong><br>${unitSummary}</div>
      <div><strong>Dates</strong><br>${arrival} → ${departure} (${nights} night${Number(nights)===1?'':'s'})</div>
      <div><strong>Occupants</strong><br>${occupants}</div>
      <div><strong>Prepared</strong><br>${prepared}</div>
    `;
    const ref = makeRef();
    modalRefEl.textContent = `Reference: ${ref}`;
    if (printHeaderRef) printHeaderRef.textContent = `Reference: ${ref}`;
  }

  function toRows(data, payload) {
    if (!Array.isArray(data)) return [];
    return data.map((item) => {
      const unit = prettifyUnitName(item?.unit_name ?? payload?.['Unit Name'] ?? 'Unit');
      const dr = item?.date_range || {};
      const arrival = dr.arrival ?? (payload?.['Arrival'] ?? '–');
      const departure = dr.departure ?? (payload?.['Departure'] ?? '–');
      const nights = (dr.nights ?? computeNights(arrival, departure));
      const rateNum = (typeof item?.rate === 'number') ? item.rate : NaN;
      const backendUnavailable = item?.availability === false;
      const hasPrice = Number.isFinite(rateNum) && rateNum > 0;
      const availability = backendUnavailable ? false : hasPrice;
      const currency = item?.currency ?? 'NAD';
      return { unit, arrival, departure, nights, availability, rate: rateNum, currency };
    });
  }

  function openModal(rows, payload) {
    modalTbody.innerHTML = '';
    let total = 0, pricedCount = 0;
    const currency = rows[0]?.currency || 'NAD';

    rows.forEach((r) => {
      const hasPrice = Number.isFinite(r.rate) && r.rate > 0;
      if (hasPrice) { total += r.rate; pricedCount++; }
      const badgeHtml = r.availability && hasPrice
        ? `<span class="badge ok">AVAILABLE</span>`
        : (r.availability ? `<span class="badge no">NO PRICED RATE</span>` : `<span class="badge no">UNAVAILABLE</span>`);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.unit}</td>
        <td>${r.arrival}</td>
        <td>${r.departure}</td>
        <td>${r.nights}</td>
        <td>${badgeHtml}</td>
        <td style="text-align:right">${hasPrice ? formatMoney(r.rate, r.currency) : '—'}</td>
      `;
      modalTbody.appendChild(tr);
    });

    modalTotal.textContent = pricedCount > 0 ? formatMoney(total, currency) : '—';

    const hasPricedAvailable = rows.some(r => r.availability && Number.isFinite(r.rate) && r.rate > 0);
    canConfirm = hasPricedAvailable;
    modalConfirm.classList.toggle('danger', !canConfirm);

    fillPrintMeta(rows, payload);
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    modalClose.focus();
  }
  function closeModal() {
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  // Booking modal events
  modalClose.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
  modalConfirm.addEventListener('click', () => {
    if (!canConfirm) {
      showMessage('Booking not confirmed — no priced availability for the selected inputs.', 'error');
      return;
    }
    closeModal();
    showMessage('Booking confirmed (demo).', 'success');
  });
  modalPrint.addEventListener('click', () => {
    if (modal.getAttribute('aria-hidden') === 'true') return;
    const oldTitle = document.title;
    const ref = (modalRefEl.textContent || '').replace('Reference: ', '').trim();
    if (ref) document.title = `Gondwana-Booking-${ref}`;
    window.print();
    document.title = oldTitle;
  });

  // ---------- Date Fix modal helpers ----------
  function openDateFix(which, msg) {
    dateFixTarget = which; // 'arrival' or 'departure'
    dateModalMsg.textContent = msg;
    dateModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    dateModalClose.focus();
  }
  function closeDateFix() {
    dateModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    dateFixTarget = null;
  }
  dateModal.addEventListener('click', (e) => { if (e.target === dateModal) closeDateFix(); });
  dateModalClose.addEventListener('click', closeDateFix);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && dateModal.getAttribute('aria-hidden') === 'false') closeDateFix(); });
  dateModalPick.addEventListener('click', () => {
    const target = (dateFixTarget === 'arrival') ? arrivalISO : departureISO;
    if (target) {
      if (typeof target.showPicker === 'function') target.showPicker(); else target.focus();
    }
    closeDateFix();
  });

  // ---------- Submit ----------
  async function submitForm(e) {
    e.preventDefault();
    clearMessage();

    // Ensure displayed fields reflect current ISO values (important if user typed via popup only)
    syncDisplaysFromISO();

    // Date rule popup validation before payload checks
    const dateCheck = validateDatesForPopup(arrivalDisplay.value, departureDisplay.value);
    if (!dateCheck.ok) {
      showMessage(dateCheck.msg, 'error');
      openDateFix(dateCheck.which, dateCheck.msg);
      return;
    }

    const payload = buildPayload();
    const errs = validatePayload(payload);
    if (errs.length) { showMessage(errs[0], 'error'); return; }

    try {
      await ensureApiBase();
      const res = await fetch(`${API_BASE}/rates`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const { json, raw, html } = await safeJson(res);

      if (!res.ok) {
        showMessage(`Rates HTTP ${res.status}`, 'error');
        resultsContainer.innerHTML = `<pre>${raw.slice(0, 1000)}</pre>`;
        return;
      }
      if (!json || html) {
        showMessage(`API returned non-JSON (likely HTML). Check API_BASE & backend server.`, 'error');
        resultsContainer.innerHTML = `<pre>${raw.slice(0, 1000)}</pre>`;
        return;
      }

      if (json.success) {
        showMessage('Rates fetched successfully! Review & confirm.', 'success');
        const rows = toRows(json.data, payload);
        if (!rows.length) { showMessage('No valid results returned.', 'error'); return; }
        openModal(rows, payload);
        resultsContainer.innerHTML = '';
      } else {
        const detail = json.error?.message || 'Unknown error';
        showMessage(`Rates error: ${detail}`, 'error');
        resultsContainer.innerHTML = `<pre>${JSON.stringify(json, null, 2)}</pre>`;
      }
    } catch (err) {
      showMessage(`Network error: ${err.message}`, 'error');
    }
  }

  // ---------- Test JSON ----------
  async function testEndpoint() {
    clearMessage();
    try {
      await ensureApiBase();
      const res = await fetch(`${API_BASE}/test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ ping: 'ok' }),
      });
      const { json, raw } = await safeJson(res);

      if (res.ok) {
        showMessage(json?.success ? 'Test endpoint success' : 'Test endpoint responded (details below)', 'success');
        resultsContainer.innerHTML = `<pre>${json ? JSON.stringify(json, null, 2) : raw.slice(0, 1000)}</pre>`;
        showToast('Test JSON successful');
      } else {
        showMessage(`Test endpoint HTTP ${res.status}`, 'error');
        resultsContainer.innerHTML = `<pre>${json ? JSON.stringify(json, null, 2) : raw.slice(0, 1000)}</pre>`;
      }
    } catch (err) {
      showMessage(`Network error: ${err.message}`, 'error');
    }
  }

  // Wire up
  form.addEventListener('submit', submitForm);
  testBtn.addEventListener('click', testEndpoint);

  // Defaults
  agesContainer.innerHTML = '';
  agesContainer.appendChild(makeAgeChip(30));
  agesContainer.appendChild(makeAgeChip(28));
  syncAgesToOccupants();
  // Ensure min constraints on first load
  applyMinConstraints();
});
