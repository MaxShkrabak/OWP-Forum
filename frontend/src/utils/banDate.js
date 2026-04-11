/**
 * Parse bannedUntil (ISO or "YYYY-MM-DD HH:mm:ss") to a Date, or null.
 */
function parseBannedUntil(bannedUntil) {
  if (!bannedUntil) return null;
  const str = String(bannedUntil).trim();
  const match = str.match(
    /^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{1,2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(?:\s*Z)?)?/i,
  );
  if (match) {
    const [, y, m, day, h, min, sec] = match;
    const year = Number(y);
    const month = Number(m) - 1;
    const d = Number(day);
    if (h !== undefined && min !== undefined) {
      return new Date(
        Date.UTC(year, month, d, Number(h), Number(min), Number(sec || 0), 0),
      );
    }
    return new Date(year, month, d);
  }
  try {
    const d = new Date(str);
    return Number.isNaN(d.getTime()) ? null : d;
  } catch {
    return null;
  }
}

/**
 * Format bannedUntil as calendar date (same day everywhere).
 */
export function formatBannedUntilDate(bannedUntil, dateStyle = "long") {
  const d = parseBannedUntil(bannedUntil);
  if (!d) return "";
  const y = d.getUTCFullYear();
  const mo = d.getUTCMonth();
  const day = d.getUTCDate();
  const local = new Date(y, mo, day);
  return local.toLocaleDateString(undefined, { dateStyle });
}

/**
 * Format bannedUntil as date and time in UTC so banner and admin always match.
 */
export function formatBannedUntilDateTime(bannedUntil, options = {}) {
  const { dateStyle = "long", timeStyle = "short" } = options;
  const d = parseBannedUntil(bannedUntil);
  if (!d) return "";
  return (
    d.toLocaleString(undefined, {
      dateStyle,
      timeStyle,
      timeZone: "UTC",
    }) + " UTC"
  );
}
