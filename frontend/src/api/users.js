import client from "./client";

export async function fetchUser(id) {
  const { data } = await client.get(`/profile/${id}`);
  return data;
}

export async function getNotificationSettings() {
  const { data } = await client.get("/user/notification-settings");
  return data;
}

export async function saveNotificationSettings(payload) {
  const { data } = await client.post("/user/notification-settings", payload);
  return data;
}

export async function fetchNotifications() {
  const { data } = await client.get("/user/notifications");
  return data;
}

export async function markNotificationsRead(notificationIds) {
  const { data } = await client.post("/user/notifications/read", {
    notificationIds,
  });
  return data;
}

export async function fetchUserStats(id) {
  const { data } = await client.get(`/profile/${id}/stats`);
  return data;
}
