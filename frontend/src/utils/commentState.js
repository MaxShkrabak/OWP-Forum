export function normalizeDeletedCommentEvent(payload) {
  if (payload && typeof payload === "object") {
    return {
      id: Number(payload.id ?? 0),
      keepPlaceholder: Boolean(payload.keepPlaceholder),
    };
  }

  return {
    id: Number(payload ?? 0),
    keepPlaceholder: false,
  };
}

export function createDeletedCommentPlaceholder(comment) {
  return {
    ...comment,
    isDeleted: true,
    author: "[deleted]",
    user: null,
    text: null,
    content: null,
    score: 0,
    myVote: 0,
    wasEdited: false,
  };
}
