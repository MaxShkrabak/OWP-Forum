export function getNotificationPreferences() {
  try {
    const raw = localStorage.getItem("notificationPreferences");
    if (!raw) {
      return {
        emailNotifications: true,
        pushNotifications: true,
        postReplies: true,
        postLikes: true,
      };
    }

    const parsed = JSON.parse(raw);

    return {
      emailNotifications: parsed.emailNotifications ?? true,
      pushNotifications: parsed.pushNotifications ?? true,
      postReplies: parsed.postReplies ?? true,
      postLikes: parsed.postLikes ?? true,
    };
  } catch {
    return {
      emailNotifications: true,
      pushNotifications: true,
      postReplies: true,
      postLikes: true,
    };
  }
}

export function isNotificationEnabled(type) {
  const prefs = getNotificationPreferences();

  if (!prefs.pushNotifications) return false;
  if (type === "postLike") return !!prefs.postLikes;
  if (type === "postReply") return !!prefs.postReplies;

  return false;
}
