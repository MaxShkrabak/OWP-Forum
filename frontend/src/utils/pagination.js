/**
 * Calculates a sliding window range of pages for navigation.
 * @param {number} current - The current active page.
 * @param {number} total - The total number of pages.
 * @param {number} delta - How many pages to show on either side of the current page.
 */
export function getPaginationRange(current, total, delta = 2) {
  const range = [];
  const rangeWithDots = [];
  let prev;

  for (let i = 1; i <= total; i++) {
    // Includes first and last page
    if (
      i === 1 ||
      i === total ||
      (i >= current - delta && i <= current + delta)
    ) {
      range.push(i);
    }
  }

  for (const i of range) {
    if (prev) {
      if (i - prev === 2) {
        rangeWithDots.push(prev + 1);
      } else if (i - prev !== 1) {
        rangeWithDots.push("...");
      }
    }
    rangeWithDots.push(i);
    prev = i;
  }

  return rangeWithDots;
}
