import client from "./client";

export async function requestOtp(email) {
  const { data } = await client.post("../auth/request-otp", { email });
  return data;
}

export async function verifyOtp(email, otp) {
  const { data } = await client.post("/login", { email, otp });
  return data;
}

export async function verifyEmail(email) {
  const { data } = await client.post("/verify-email", { email });
  return data;
}

export async function registerUser(payload) {
  const { data } = await client.post("/register-new-user", payload);
  return data;
}

export async function checkAuth() {
  const { data } = await client.get("/me");
  return data;
}

export async function logout() {
  try {
    await client.post("/logout");
    return { ok: true };
  } catch (e) {
    return { ok: false, error: e.message };
  }
}

export async function getName() {
  const data = await checkAuth();
  if (data?.ok && data?.user) {
    return `${data.user.firstName} ${data.user.lastName}`;
  }
  return null;
}

export async function updateUserAvatar(avatarPath) {
  const { data } = await client.post("/user/avatar", { avatar: avatarPath });
  return data;
}
