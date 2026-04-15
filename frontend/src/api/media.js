import client from "./client";

export async function uploadImage(file) {
  const imgForm = new FormData();
  imgForm.append("image", file);

  const { data } = await client.post("/upload-image", imgForm);

  if (!data.ok) throw new Error(data.error || "Image upload failed.");

  return data;
}
