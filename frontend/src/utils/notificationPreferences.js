const LEGACY_NOTIFICATION_PREFERENCES_KEY = "notificationPreferences";

function getScopedNotificationPreferencesKey(userId = localStorage.getItem("uid")) {
  return userId ? `${LEGACY_NOTIFICATION_PREFERENCES_KEY}:${userId}` : null;
}

function parseStoredPreferences(raw) {
  if (!raw) return null;

  const parsed = JSON.parse(raw);

  return {
    emailNotifications: parsed.emailNotifications ?? true,
    pushNotifications: parsed.pushNotifications ?? true,
    postReplies: parsed.postReplies ?? true,
    postLikes: parsed.postLikes ?? true,
  };
}

export function saveNotificationPreferences(preferences, userId = localStorage.getItem("uid")) {
  const key = getScopedNotificationPreferencesKey(userId);
  const serialized = JSON.stringify(preferences);

  if (key) {
    localStorage.setItem(key, serialized);
    localStorage.removeItem(LEGACY_NOTIFICATION_PREFERENCES_KEY);
    return;
  }

  localStorage.setItem(LEGACY_NOTIFICATION_PREFERENCES_KEY, serialized);
}

export function clearNotificationPreferences(userId = localStorage.getItem("uid")) {
  const key = getScopedNotificationPreferencesKey(userId);
  if (key) {
    localStorage.removeItem(key);
  }
  localStorage.removeItem(LEGACY_NOTIFICATION_PREFERENCES_KEY);
}

export function getNotificationPreferences() {
  try {
    const scopedKey = getScopedNotificationPreferencesKey();
    const scopedPreferences = scopedKey
      ? parseStoredPreferences(localStorage.getItem(scopedKey))
      : null;

    if (scopedPreferences) {
      return scopedPreferences;
    }

    const legacyPreferences = parseStoredPreferences(
      localStorage.getItem(LEGACY_NOTIFICATION_PREFERENCES_KEY),
    );

    if (legacyPreferences) {
      if (scopedKey) {
        saveNotificationPreferences(legacyPreferences);
      }
      return legacyPreferences;
    }

    return {
      emailNotifications: true,
      pushNotifications: true,
      postReplies: true,
      postLikes: true,
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
