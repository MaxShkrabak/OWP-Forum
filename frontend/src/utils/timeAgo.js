// src/utils/timeAgo.js (or .ts)
export function timeAgo(input) {
  if (!input) return '';
  const d = new Date(input);
  if (isNaN(d.getTime())) return '';

  // Past is positive; future is negative
  const diffSec = Math.round((Date.now() - d.getTime()) / 1000);
  const abs = Math.abs(diffSec);

  const MIN = 60;
  const HOUR = 60 * MIN;
  const DAY = 24 * HOUR;
  const MONTH = 30 * DAY;
  const YEAR = 365 * DAY;

  const fmt = (n, unit) => `${n} ${unit}${n === 1 ? '' : 's'} ago`;

  if (abs < 45) return 'just now';
  if (abs < 90) return fmt(1, 'minute');
  if (abs < 45 * MIN) return fmt(Math.round(abs / MIN), 'minute');
  if (abs < 90 * MIN) return fmt(1, 'hour');
  if (abs < 24 * HOUR) return fmt(Math.round(abs / HOUR), 'hour');

  // day / days
  if (abs < 48 * HOUR) return fmt(1, 'day');
  if (abs < 30 * DAY) return fmt(Math.round(abs / DAY), 'day');

  if (abs < 60 * DAY) return fmt(1, 'month');
  if (abs < 365 * DAY) return fmt(Math.round(abs / MONTH), 'month');

  if (abs < 545 * DAY) return fmt(1, 'year');
  return fmt(Math.round(abs / YEAR), 'year');
}
