import client from "./client";

const API = import.meta.env.VITE_API_BASE || "http://localhost:8080";

export async function uploadImage(file) {
  const imgForm = new FormData();
  imgForm.append('image', file);

  const { data } = await client.post("/upload-image", imgForm, {
    headers: { 'Content-Type': 'multipart/form-data' }
  });

  if (data?.url && !data.url.startsWith('http')) {
    data.url = `${API}${data.url}`;
  }

  return data;
}