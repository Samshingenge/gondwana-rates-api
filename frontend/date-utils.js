(function (globalScope, factory) {
  const exports = factory();
  if (typeof module === "object" && module.exports) {
    module.exports = exports;
  } else if (typeof define === "function" && define.amd) {
    define([], () => exports);
  } else {
    globalScope.DateRangeUtils = exports;
  }
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  const DAY_MS = 86_400_000;
  const ISO_RE = /^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/;
  const DMY_RE = /^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])\/(\d{4})$/;

  function isIsoDate(value) {
    return typeof value === "string" && ISO_RE.test(value);
  }

  function coerceIso(value) {
    return isIsoDate(value) ? value : "";
  }

  function parseIso(value) {
    if (!isIsoDate(value)) return null;
    const [y, m, d] = value.split("-").map((part) => parseInt(part, 10));
    const date = new Date(Date.UTC(y, m - 1, d));
    if (
      Number.isNaN(date.getTime()) ||
      date.getUTCFullYear() !== y ||
      date.getUTCMonth() !== m - 1 ||
      date.getUTCDate() !== d
    ) {
      return null;
    }
    return date;
  }

  function dateToIsoUTC(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return "";
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, "0");
    const day = String(date.getUTCDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function addDays(isoDate, amount) {
    const base = parseIso(isoDate);
    if (!base || !Number.isFinite(amount)) return "";
    const next = new Date(base.getTime() + Math.round(amount) * DAY_MS);
    return dateToIsoUTC(next);
  }

  function diffDays(startIso, endIso) {
    const start = parseIso(startIso);
    const end = parseIso(endIso);
    if (!start || !end) return NaN;
    return Math.round((end.getTime() - start.getTime()) / DAY_MS);
  }

  function formatDateToDisplay(isoDate) {
    const parsed = parseIso(isoDate);
    if (!parsed) return "";
    const day = String(parsed.getUTCDate()).padStart(2, "0");
    const month = String(parsed.getUTCMonth() + 1).padStart(2, "0");
    const year = parsed.getUTCFullYear();
    return `${day}/${month}/${year}`;
  }

  function parseDmy(dmyString) {
    if (typeof dmyString !== "string") return null;
    const match = dmyString.match(DMY_RE);
    if (!match) return null;
    const [, d, m, y] = match;
    const date = new Date(Date.UTC(parseInt(y, 10), parseInt(m, 10) - 1, parseInt(d, 10)));
    if (
      Number.isNaN(date.getTime()) ||
      date.getUTCDate() !== parseInt(d, 10) ||
      date.getUTCMonth() !== parseInt(m, 10) - 1 ||
      date.getUTCFullYear() !== parseInt(y, 10)
    ) {
      return null;
    }
    return date;
  }

  function sanitizeGapDays(value) {
    if (!Number.isFinite(value)) return 0;
    return Math.max(0, Math.floor(value));
  }

  function normalizeRange({ arrivalIso, departureIso, preferredGapDays = 0 }) {
    const arrival = coerceIso(arrivalIso);
    const normalized = {
      arrivalIso: "",
      departureIso: "",
      gapDays: 0,
      nights: 0,
      wasArrivalCoerced: false,
      wasDepartureAdjusted: false,
    };

    if (!arrival) {
      normalized.wasArrivalCoerced = Boolean(arrivalIso);
      normalized.wasDepartureAdjusted = Boolean(departureIso);
      return normalized;
    }

    normalized.arrivalIso = arrival;
    let gap = sanitizeGapDays(preferredGapDays);
    let departure = coerceIso(departureIso);

    if (departure) {
      const diff = diffDays(arrival, departure);
      if (!Number.isFinite(diff) || diff < 0) {
        departure = "";
        normalized.wasDepartureAdjusted = true;
      } else {
        gap = diff;
      }
    } else if (departureIso) {
      normalized.wasDepartureAdjusted = true;
    }

    if (!departure) {
      departure = addDays(arrival, gap);
      if (departureIso && departure !== coerceIso(departureIso)) {
        normalized.wasDepartureAdjusted = true;
      }
    }

    let safeDiff = diffDays(arrival, departure);
    if (!Number.isFinite(safeDiff)) {
      safeDiff = 0;
      departure = arrival;
      normalized.wasDepartureAdjusted = true;
    } else if (safeDiff < 0) {
      safeDiff = 0;
      departure = arrival;
      normalized.wasDepartureAdjusted = true;
    }

    normalized.departureIso = departure;
    normalized.gapDays = safeDiff;
    normalized.nights = Math.max(1, safeDiff === 0 ? 1 : safeDiff);

    return normalized;
  }

  function formatRangeSummary(arrivalIso, departureIso, nights) {
    const arrivalLabel = formatDateToDisplay(arrivalIso);
    const departureLabel = formatDateToDisplay(departureIso);
    if (!arrivalLabel) return "Select arrival date";
    if (!departureLabel) return `${arrivalLabel} → (select departure)`;
    const plural = nights === 1 ? "night" : "nights";
    return `${arrivalLabel} → ${departureLabel} · ${nights} ${plural}`;
  }

  return {
    isIsoDate,
    coerceIso,
    parseIso,
    dateToIsoUTC,
    addDays,
    diffDays,
    formatDateToDisplay,
    parseDmy,
    normalizeRange,
    formatRangeSummary,
  };
});