document.addEventListener('DOMContentLoaded', () => {
  // ---------- API base (robust) ----------
  const storedApi = localStorage.getItem('API_BASE') || null;
  const host8000 = `http://${location.hostname}:8000/api`;
  const candidates = [storedApi, host8000, 'http://localhost:8000/api', `${location.origin}/api`].filter(Boolean);
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

  const arrivalISO = document.getElementById('arrivalISO');
  const arrivalDisplay = document.getElementById('arrivalDisplay');
  const departureISO = document.getElementById('departureISO');
  const departureDisplay = document.getElementById('departureDisplay');

  // People group controls
  const adultsEl = document.getElementById('adults');
  const childrenEl = document.getElementById('children');
  const infantsEl = document.getElementById('infants');

  const agesAdults = document.getElementById('agesAdults');
  const agesChildren = document.getElementById('agesChildren');
  const agesInfants = document.getElementById('agesInfants');

  // Counters
  const btnIncAdults = document.querySelector('[data-inc-adults]');
  const btnDecAdults = document.querySelector('[data-dec-adults]');
  const btnIncChildren = document.querySelector('[data-inc-children]');
  const btnDecChildren = document.querySelector('[data-dec-children]');
  const btnIncInfants = document.querySelector('[data-inc-infants]');
  const btnDecInfants = document.querySelector('[data-dec-infants]');

  // Booking modal
  const modal = document.getElementById('resultModal');
  const modalTbody = document.getElementById('modalTbody');
  const modalTotal = document.getElementById('modalTotal');
  const modalClose = document.getElementById('modalClose');
  const modalConfirm = document.getElementById('modalConfirm');
  const modalPrint = document.getElementById('modalPrint');
  const modalRefEl = document.getElementById('modalRef');
  const printMeta = document.getElementById('printMeta');

  // Date-fix modal
  const dateModal = document.getElementById('dateModal');
  const dateModalMsg = document.getElementById('dateModalMsg');
  const dateModalPick = document.getElementById('dateModalPick');
  const dateModalClose = document.getElementById('dateModalClose');

  // New UI bits
  const submitBtn = document.getElementById('submitBtn');
  const guestBadge = document.getElementById('guestBadge');
  const guestValidation = document.getElementById('guestValidation');
  const unitCapHint = document.getElementById('unitCapHint');

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

  // ---------- Regex & date input helpers ----------
  const DATE_RE_DMY = /^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])\/(19|20)\d{2}$/;
  function sanitizeDigits(value) { return String(value || '').replace(/\D+/g, '').slice(0, 8); }
  function autoFormatDmy(value) {
    const d = sanitizeDigits(value);
    if (d.length <= 2) return d;
    if (d.length <= 4) return d.slice(0,2) + '/' + d.slice(2);
    return d.slice(0,2) + '/' + d.slice(2,4) + '/' + d.slice(4);
  }

  const rangeSummary = document.getElementById('rangeSummary');
  const dateValidation = document.getElementById('dateValidation');
  function setFieldError(msg) {
    if (!msg) { dateValidation.textContent = ''; dateValidation.hidden = true; }
    else { dateValidation.textContent = msg; dateValidation.hidden = false; }
  }
  function updateRangeSummaryFromDisplays() {
    const a = arrivalDisplay.value.trim();
    const d = departureDisplay.value.trim();
    if (DATE_RE_DMY.test(a) && DATE_RE_DMY.test(d)) {
      const nights = computeNights(a, d);
      if (nights === '–') { rangeSummary.textContent = 'Choose arrival and departure dates to see the stay summary.'; return; }
      rangeSummary.innerHTML = `
        <div class="range-summary__dates">
          <strong>${a}</strong> → <strong>${d}</strong> (${nights} night${Number(nights)===1?'':'s'})
        </div>
        <div class="range-summary__hint">Change any date to update the summary.</div>
      `;
    } else if (DATE_RE_DMY.test(a)) {
      rangeSummary.textContent = `Arrival set to ${a}. Choose a valid departure date`;
    } else if (DATE_RE_DMY.test(d)) {
      rangeSummary.textContent = `Departure set to ${d}. Choose a valid arrival date`;
    } else {
      rangeSummary.textContent = 'Choose arrival and departure dates to see the stay summary.';
    }
  }

  // ---------- People: age chips ----------
  function makeAgeChip(val, min, max) {
    const clamped = Math.min(max, Math.max(min, Number.isFinite(val) ? val : min));
    const wrap = document.createElement('div');
    wrap.className = 'age-row';
    wrap.innerHTML = `
      <input type="number" min="${min}" max="${max}" value="${clamped}" class="age-input" aria-label="Guest age">
      <button type="button" class="remove-age-btn" aria-label="Remove age">×</button>
    `;
    wrap.querySelector('.remove-age-btn').addEventListener('click', () => wrap.remove());
    return wrap;
  }
  function ensureCount(container, count, defAge, min, max) {
    const current = container.querySelectorAll('.age-input').length;
    if (current < count) for (let i = 0; i < count - current; i++) container.appendChild(makeAgeChip(defAge, min, max));
    else if (current > count) {
      const chips = Array.from(container.querySelectorAll('.age-row'));
      for (let i = 0; i < current - count; i++) { const last = chips.pop(); last && last.remove(); }
    }
  }
  function totalOccupants() {
    const a = Math.max(1, parseInt(adultsEl.value || '1', 10));
    const c = Math.max(0, parseInt(childrenEl.value || '0', 10));
    const i = Math.max(0, parseInt(infantsEl.value || '0', 10));
    return a + c + i;
  }
  function syncAllAges() {
    const a = Math.max(1, parseInt(adultsEl.value || '1', 10));
    const c = Math.max(0, parseInt(childrenEl.value || '0', 10));
    const i = Math.max(0, parseInt(infantsEl.value || '0', 10));
    ensureCount(agesAdults, a, 30, 13, 120);
    ensureCount(agesChildren, c, 8, 2, 12);
    ensureCount(agesInfants, i, 1, 0, 1);
  }

  // ---------- Unit capacity & guest badge ----------
  const UNIT_CAPS = {
    'Standard Unit': 2,
    'Deluxe Unit': 4
  };
  function getUnitCap() {
    const key = unitNameEl.value || '';
    return UNIT_CAPS[key] ?? 4; // default cap if unknown
  }
  function setGuestError(msg) {
    if (!msg) { guestValidation.textContent = ''; guestValidation.hidden = true; }
    else { guestValidation.textContent = msg; guestValidation.hidden = false; }
  }
  function updateGuestBadgeAndCap() {
    const total = totalOccupants();
    const cap = getUnitCap();
    guestBadge.textContent = `${total} guest${total===1?'':'s'} (max ${cap})`;
    const over = total > cap;
    guestBadge.classList.toggle('warn', over);
    unitCapHint.textContent = unitNameEl.value ? `Max occupancy for "${unitNameEl.value}": ${cap}` : '';
    submitBtn.disabled = over;
    setGuestError(over ? `Guest count exceeds the maximum (${cap}) for the selected unit.` : '');
    return !over;
  }

  // ===== Pricing model (frontend-calculated fallback) =====
  // If backend returns 'total' or 'rate_total', we'll use that instead.
  const PRICING = {
    model: 'per_night_per_person', // 'per_night_per_person' | 'per_night' | 'flat'
    childFactor: 0.5,   // each child pays 50% of adult
    infantFactor: 0.0,  // infants free
  };
  function roundCurrency(n) {
    return Math.round((n + Number.EPSILON) * 100) / 100;
  }
  function computeRowTotal(row, payload, item) {
    // 1) Trust backend total if present
    const backendTotal = Number(item?.total ?? item?.rate_total);
    if (Number.isFinite(backendTotal) && backendTotal > 0) return backendTotal;

    // 2) Fallback: compute client-side
    const rate = Number(item?.rate);
    if (!Number.isFinite(rate) || rate <= 0) return NaN;

    const nights = Number(row.nights) || 1;
    const adults = Number(payload?.Adults || 0);
    const children = Number(payload?.Children || 0);
    const infants = Number(payload?.Infants || 0);

    let total;
    switch (PRICING.model) {
      case 'per_night_per_person': {
        const eq = adults + (children * PRICING.childFactor) + (infants * PRICING.infantFactor);
        total = rate * nights * eq;
        break;
      }
      case 'per_night': {
        total = rate * nights;
        break;
      }
      case 'flat':
      default: {
        total = rate;
        break;
      }
    }
    return roundCurrency(total);
  }

  // ---------- Payload & validation ----------
  function collectAges(container, min, max) {
    return Array.from(container.querySelectorAll('.age-input'))
      .map(el => parseInt(el.value, 10))
      .filter(n => Number.isFinite(n) && n >= min && n <= max);
  }
  function buildPayload() {
    const Adults = Math.max(1, parseInt(adultsEl.value || '1', 10));
    const Children = Math.max(0, parseInt(childrenEl.value || '0', 10));
    const Infants = Math.max(0, parseInt(infantsEl.value || '0', 10));

    const adultAges = collectAges(agesAdults, 13, 120);
    const childAges = collectAges(agesChildren, 2, 12);
    const infantAges = collectAges(agesInfants, 0, 1);
    const Ages = [...adultAges, ...childAges, ...infantAges];

    return {
      'Unit Name': unitNameEl.value,
      'Arrival': arrivalDisplay.value.trim(),
      'Departure': departureDisplay.value.trim(),
      'Adults': Adults, 'adults': Adults,
      'Children': Children, 'children': Children,
      'Infants': Infants, 'infants': Infants,
      'Occupants': Adults + Children + Infants,
      'Ages': Ages
    };
  }
  function validatePayload(p) {
    const errs = [];
    if (!p['Unit Name']) errs.push('Unit Name is required');
    if (!DATE_RE_DMY.test(p['Arrival'])) errs.push('Arrival must be dd/mm/yyyy');
    if (!DATE_RE_DMY.test(p['Departure'])) errs.push('Departure must be dd/mm/yyyy');
    if (!(p['Adults'] >= 1)) errs.push('At least 1 adult is required');
    if (!(p['Children'] >= 0)) errs.push('Children cannot be negative');
    if (!(p['Infants'] >= 0)) errs.push('Infants cannot be negative');

    const expected = p['Adults'] + p['Children'] + p['Infants'];
    if (!Array.isArray(p['Ages']) || p['Ages'].length !== expected) errs.push('Total ages must equal the number of guests');

    const adultAges = collectAges(agesAdults, 13, 120);
    const childAges = collectAges(agesChildren, 2, 12);
    const infantAges = collectAges(agesInfants, 0, 1);
    if (adultAges.length !== p['Adults']) errs.push('Each adult must have an age 13–120');
    if (childAges.length !== p['Children']) errs.push('Each child must have an age 2–12');
    if (infantAges.length !== p['Infants']) errs.push('Each infant must have an age 0–1');

    if (p['Occupants'] > getUnitCap()) errs.push(`Guest count exceeds the maximum for ${unitNameEl.value || 'selected unit'}.`);

    return errs;
  }

  // ---------- Date rule popup validation ----------
  function validateDatesForPopup(arrivalDmy, departureDmy) {
    const aIso = dmyToIso(arrivalDmy);
    const dIso = dmyToIso(departureDmy);
    const today = todayISO();
    if (!aIso) return { ok:false, which:'arrival', msg:`Please choose a valid arrival date in dd/mm/yyyy.` };
    if (!dIso) return { ok:false, which:'departure', msg:`Please choose a valid departure date in dd/mm/yyyy.` };
    if (isPastISO(aIso)) return { ok:false, which:'arrival', msg:`Arrival cannot be in the past. Pick a date on or after ${formatDateToDisplay(today)}.` };
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
    const a = arrivalISO.value && !isPastISO(arrivalISO.value) ? arrivalISO.value : today;
    departureISO.min = addDaysISO(a, 1);
  }
  function syncDisplaysFromISO() {
    arrivalDisplay.value = formatDateToDisplay(arrivalISO.value);
    departureDisplay.value = formatDateToDisplay(departureISO.value);
  }

  // Date-fix modal
  function openDateFix(which, msg) {
    dateModalMsg.textContent = msg;
    dateModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    dateModalClose.focus();
  }
  function closeDateFix() {
    dateModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  dateModal.addEventListener('click', (e) => { if (e.target === dateModal) closeDateFix(); });
  dateModalClose.addEventListener('click', closeDateFix);
  dateModalPick.addEventListener('click', () => {
    const target = document.activeElement === departureDisplay ? departureISO : arrivalISO;
    if (target && typeof target.showPicker === 'function') target.showPicker();
    closeDateFix();
  });

  // Initial dates
  function ensureInitialDates() {
    const today = todayISO();
    if (!arrivalISO.value || isPastISO(arrivalISO.value)) arrivalISO.value = today;
    const suggestedDeparture = addDaysISO(arrivalISO.value, 1);
    if (!departureISO.value || departureISO.value <= arrivalISO.value || isPastISO(departureISO.value)) {
      departureISO.value = suggestedDeparture;
    }
    applyMinConstraints();
    syncDisplaysFromISO();
  }
  ensureInitialDates();

  // Visible inputs: auto-format + validate on blur
  arrivalDisplay.addEventListener('input', () => {
    const c = arrivalDisplay.selectionStart, before = arrivalDisplay.value;
    arrivalDisplay.value = autoFormatDmy(before);
    const delta = arrivalDisplay.value.length - before.length;
    arrivalDisplay.setSelectionRange(Math.max(0,(c||0)+delta), Math.max(0,(c||0)+delta));
    setFieldError(''); updateRangeSummaryFromDisplays();
  });
  departureDisplay.addEventListener('input', () => {
    const c = departureDisplay.selectionStart, before = departureDisplay.value;
    departureDisplay.value = autoFormatDmy(before);
    const delta = departureDisplay.value.length - before.length;
    departureDisplay.setSelectionRange(Math.max(0,(c||0)+delta), Math.max(0,(c||0)+delta));
    setFieldError(''); updateRangeSummaryFromDisplays();
  });
  function validateDisplayField(which) {
    const displayEl = which === 'arrival' ? arrivalDisplay : departureDisplay;
    const isoEl = which === 'arrival' ? arrivalISO : departureISO;
    const raw = displayEl.value.trim();
    if (!DATE_RE_DMY.test(raw)) {
      isoEl.value = '';
      setFieldError(`Please use dd/mm/yyyy for ${which}.`);
      updateRangeSummaryFromDisplays(); applyMinConstraints();
      return false;
    }
    const iso = dmyToIso(raw);
    if (!iso) { isoEl.value = ''; setFieldError(`Please choose a valid ${which} date.`); updateRangeSummaryFromDisplays(); return false; }
    if (which === 'arrival' && isPastISO(iso)) {
      isoEl.value=''; setFieldError(`Arrival cannot be in the past. Pick a date on or after ${formatDateToDisplay(todayISO())}.`);
      updateRangeSummaryFromDisplays(); return false;
    }
    isoEl.value = iso; applyMinConstraints();
    const aIso = arrivalISO.value, dIso = departureISO.value;
    if (aIso && dIso && new Date(dIso+'T00:00:00') <= new Date(aIso+'T00:00:00')) {
      if (which === 'arrival') {
        const minDep = addDaysISO(aIso, 1);
        departureISO.value = minDep; departureDisplay.value = formatDateToDisplay(minDep);
      } else {
        setFieldError(`Departure must be after arrival. Choose a date from ${formatDateToDisplay(addDaysISO(aIso,1))}.`);
        updateRangeSummaryFromDisplays(); return false;
      }
    }
    setFieldError(''); updateRangeSummaryFromDisplays(); return true;
  }
  arrivalDisplay.addEventListener('blur', () => validateDisplayField('arrival'));
  departureDisplay.addEventListener('blur', () => validateDisplayField('departure'));

  // Keep display in sync with native
  arrivalISO.addEventListener('change', () => {
    applyMinConstraints();
    const minDep = departureISO.min;
    if (departureISO.value && departureISO.value < minDep) { departureISO.value = ''; departureDisplay.value = ''; }
    arrivalDisplay.value = formatDateToDisplay(arrivalISO.value);
    setFieldError('');
    updateRangeSummaryFromDisplays();
  });
  departureISO.addEventListener('change', () => {
    departureDisplay.value = formatDateToDisplay(departureISO.value);
    setFieldError('');
    updateRangeSummaryFromDisplays();
  });

  // Calendar open proxy
  document.querySelectorAll('[data-open-calendar]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      const which = btn.getAttribute('data-open-calendar');
      const target = which === 'arrival' ? arrivalISO : departureISO;
      if (!target) return;
      target.focus({ preventScroll: true });
      if (typeof target.showPicker === 'function') target.showPicker(); else openDateFix(which, `Select a ${which} date.`);
    });
  });

  // People counters
  function clamp(val, min, max) { return Math.min(max, Math.max(min, val)); }
  btnIncAdults.addEventListener('click', () => {
    adultsEl.value = clamp(parseInt(adultsEl.value||'1',10)+1, 1, 20);
    syncAllAges(); updateGuestBadgeAndCap();
  });
  btnDecAdults.addEventListener('click', () => {
    adultsEl.value = clamp(parseInt(adultsEl.value||'1',10)-1, 1, 20);
    syncAllAges(); updateGuestBadgeAndCap();
  });
  btnIncChildren.addEventListener('click', () => {
    childrenEl.value = clamp(parseInt(childrenEl.value||'0',10)+1, 0, 20);
    syncAllAges(); updateGuestBadgeAndCap();
  });
  btnDecChildren.addEventListener('click', () => {
    childrenEl.value = clamp(parseInt(childrenEl.value||'0',10)-1, 0, 20);
    syncAllAges(); updateGuestBadgeAndCap();
  });
  btnIncInfants.addEventListener('click', () => {
    infantsEl.value = clamp(parseInt(infantsEl.value||'0',10)+1, 0, 10);
    syncAllAges(); updateGuestBadgeAndCap();
  });
  btnDecInfants.addEventListener('click', () => {
    infantsEl.value = clamp(parseInt(infantsEl.value||'0',10)-1, 0, 10);
    syncAllAges(); updateGuestBadgeAndCap();
  });
  adultsEl.addEventListener('input', () => { syncAllAges(); updateGuestBadgeAndCap(); });
  childrenEl.addEventListener('input', () => { syncAllAges(); updateGuestBadgeAndCap(); });
  infantsEl.addEventListener('input', () => { syncAllAges(); updateGuestBadgeAndCap(); });
  unitNameEl.addEventListener('change', updateGuestBadgeAndCap);

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
    const breakdown = `${payload['Adults']} adult${payload['Adults']===1?'':'s'}`
      + (payload['Children'] ? `, ${payload['Children']} child${payload['Children']===1?'':'ren'}` : '')
      + (payload['Infants'] ? `, ${payload['Infants']} infant${payload['Infants']===1?'':'s'}` : '');
    const prepared = new Date().toLocaleString();

    printMeta.innerHTML = `
      <div><strong>Unit</strong><br>${unitSummary}</div>
      <div><strong>Dates</strong><br>${arrival} → ${departure} (${nights} night${Number(nights)===1?'':'s'})</div>
      <div><strong>Guests</strong><br>${breakdown}</div>
      <div><strong>Prepared</strong><br>${prepared}</div>
    `;
    const ref = makeRef();
    modalRefEl.textContent = `Reference: ${ref}`;
  }

  function toRows(data, payload) {
    if (!Array.isArray(data)) return [];
    return data.map((item) => {
      const unit = prettifyUnitName(item?.unit_name ?? payload?.['Unit Name'] ?? 'Unit');
      const dr = item?.date_range || {};
      const arrival = dr.arrival ?? (payload?.['Arrival'] ?? '–');
      const departure = dr.departure ?? (payload?.['Departure'] ?? '–');

      // Nights must be numeric
      let nights = Number(dr.nights);
      if (!Number.isFinite(nights) || nights <= 0) {
        const c = computeNights(arrival, departure);
        nights = Number.isFinite(c) ? c : 1;
      }

      const rateNum = (typeof item?.rate === 'number') ? item.rate : Number(item?.rate);
      const backendUnavailable = item?.availability === false;
      const hasPrice = Number.isFinite(rateNum) && rateNum > 0;
      const availability = backendUnavailable ? false : hasPrice;
      const currency = item?.currency ?? 'NAD';

      return { unit, arrival, departure, nights, availability, rate: rateNum, currency, _raw: item };
    });
  }

  function openModal(rows, payload) {
    modalTbody.innerHTML = '';
    let grandTotal = 0;
    let anyPriced = false;
    const currency = rows[0]?.currency || 'NAD';

    rows.forEach((r) => {
      const hasBaseRate = Number.isFinite(r.rate) && r.rate > 0;
      let rowTotal = NaN;

      if (r.availability && hasBaseRate) {
        rowTotal = computeRowTotal(r, payload, r._raw);
        if (Number.isFinite(rowTotal) && rowTotal > 0) {
          grandTotal += rowTotal;
          anyPriced = true;
        }
      }

      const badgeHtml = r.availability && hasBaseRate
        ? `<span class="badge ok">AVAILABLE</span>`
        : (r.availability ? `<span class="badge no">NO PRICED RATE</span>` : `<span class="badge no">UNAVAILABLE</span>`);

      const calcTitle = `Calc: nights=${r.nights}, adults=${payload.Adults}, children=${payload.Children} (x${PRICING.childFactor}), infants=${payload.Infants} (x${PRICING.infantFactor})`;
      const displayAmount = Number.isFinite(rowTotal) ? formatMoney(rowTotal, r.currency) : '—';
      const baseRate = hasBaseRate ? formatMoney(r.rate, r.currency) : '—';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.unit}</td>
        <td>${r.arrival}</td>
        <td>${r.departure}</td>
        <td>${r.nights}</td>
        <td>${badgeHtml}</td>
        <td style="text-align:right" title="${calcTitle}">
          ${displayAmount}
          <div style="opacity:.75;font-size:.8em;">(base ${baseRate})</div>
        </td>
      `;
      modalTbody.appendChild(tr);
    });

    modalTotal.textContent = anyPriced ? formatMoney(grandTotal, currency) : '—';

    const hasPricedAvailable = anyPriced;
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
    if (!canConfirm) { showMessage('Booking not confirmed — no priced availability for the selected inputs.', 'error'); return; }
    closeModal(); showMessage('Booking confirmed (demo).', 'success');
  });
  modalPrint.addEventListener('click', () => {
    if (modal.getAttribute('aria-hidden') === 'true') return;
    const oldTitle = document.title;
    const ref = (modalRefEl.textContent || '').replace('Reference: ', '').trim();
    if (ref) document.title = `Gondwana-Booking-${ref}`;
    window.print();
    document.title = oldTitle;
  });

  // ---------- Submit ----------
  async function submitForm(e) {
    e.preventDefault(); clearMessage();

    // Sync displays from ISO in case picker used
    arrivalDisplay.value ||= formatDateToDisplay(arrivalISO.value);
    departureDisplay.value ||= formatDateToDisplay(departureISO.value);

    const dateCheck = validateDatesForPopup(arrivalDisplay.value, departureDisplay.value);
    if (!dateCheck.ok) { showMessage(dateCheck.msg, 'error'); return; }

    syncAllAges();
    if (!updateGuestBadgeAndCap()) {
      showMessage('Please reduce guest count to meet the unit maximum capacity.', 'error');
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

  // ---------- Wire up ----------
  form.addEventListener('submit', submitForm);
  testBtn.addEventListener('click', testEndpoint);

  // Defaults: 2 adults, 0 children, 0 infants with sensible ages
  agesAdults.innerHTML = ''; agesChildren.innerHTML = ''; agesInfants.innerHTML = '';
  agesAdults.appendChild(makeAgeChip(30, 13, 120));
  agesAdults.appendChild(makeAgeChip(28, 13, 120));
  syncAllAges();
  applyMinConstraints();
  updateRangeSummaryFromDisplays();
  updateGuestBadgeAndCap(); // initialize badge & capacity state
});
