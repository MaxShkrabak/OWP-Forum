export function getPostId(p) {
  return p?.postId ?? p?.PostID ?? p?.postID ?? p?.id;
}

/**
 * Always fetch fresh vote data for the given posts
 * and attach myVote + score onto each post object.
 */
export async function loadVotesForPosts(posts) {
  const postIds = posts.map(getPostId).filter(Boolean);
  if (postIds.length === 0) return;

  try {
    const res = await fetch("/api/posts/votes/bulk", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ postIds })
    });

    const bulk = await res.json().catch(() => ({}));
    if (!(res.ok && bulk.ok)) return;

    const votes = bulk.votes || {};

    for (const p of posts) {
      const id = getPostId(p);
      const v = votes[id] || {};
      p.myVote = Number(v.myVote ?? 0);
      p.score  = Number(v.score  ?? 0);
    }
  } catch (e) {
    console.error("vote fetch failed", e);
  }
}
