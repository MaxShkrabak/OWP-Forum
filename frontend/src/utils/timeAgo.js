export function timeAgo(dateString) {
  const now = new Date();
  const past = new Date(dateString);
  const diff = (now - past) / 1000; // seconds

  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)} mins ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
  if (diff < 2592000) return `${Math.floor(diff / 604800)} weeks ago`;
  if (diff < 31536000) return `${Math.floor(diff / 2592000)} mo. ago`;

  return `${Math.floor(diff / 31536000)} yr ago`;
}
