export function normalizeName(s) {
  return String(s ?? "")
    .trim()
    .replace(/\s+/g, " ");
}

export function isDuplicateName(name, items, nameKey, idKey, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return items.some((item) => {
    const same = normalizeName(String(item[nameKey] ?? "")).toLowerCase() === n;
    const notSelf =
      excludeId == null ? true : Number(item[idKey]) !== Number(excludeId);
    return same && notSelf;
  });
}

export function stripHTML(html) {
  return html.replace(/<[^>]*>/g, "");
}
