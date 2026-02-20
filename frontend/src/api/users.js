import client from "./client";

export async function fetchUser(id) {
    const { data } = await client.get(`/profile/${id}`);
    return data;
}