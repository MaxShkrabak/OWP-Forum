import client from "./client";

export async function fetchUser(id) {
    const { data } = await client.get(`/profile/${id}`);
    return data;
}

export async function getNotificationSettings() {
    const { data } = await client.get('/user/notification-settings');
    return data;
}

export async function saveNotificationSettings(payload) {
    const { data } = await client.post('/user/notification-settings', payload);
export async function fetchUserStats(id) {
    const { data } = await client.get(`/profile/${id}/stats`);
    return data;
}