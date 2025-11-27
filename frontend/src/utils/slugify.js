export function slugifyCategoryName(name) {
  return name
    .toLowerCase()
    .trim()
    .replace(/&/g, 'and')        // Announcements & News → announcements and news
    .replace(/[^a-z0-9]+/g, '-') // non-alphanumerics → -
    .replace(/^-+|-+$/g, '');    // trim leading/trailing dashes
}
