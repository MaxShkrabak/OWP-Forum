import axios from "axios";

const API = import.meta.env.VITE_API_BASE || "http://localhost:8080";

export async function requestOtp(email) {
  const res = await fetch(`${API}/auth/request-otp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
  return res.json();
}

/**
 * Sends OTP verification request to the backend for given user email
 * 
 * @param {string} email - The users email address
 * @param {string} otp - The global OTP
 * @returns the result of the request 
 */
export async function verifyOtp(email, otp) {
  const res = await axios.post(
    `${API}/api/login`,
    { email, otp },
    {
      headers: { "Content-Type": "application/json" },
      withCredentials: true  // allow cookies
    }
  );
  return res.data;
}

/**
 * Checks if users email exists in the backend database.
 * The post request is sent to /api/verify-email with the users email
 * 
 * @param {string} email - The email address user is trying to sign-in with
 * @returns the result of the request and if email exists, for example { ok: true, emailExists: true}
 */
export async function verifyEmail(email) {
  const res = await axios.post(
    `${API}/api/verify-email`,
    { email },
    {
      headers: { "Content-Type": "application/json" },
      withCredentials: true,
    }
  );
  return res.data;
}

/**
 * Sends users registration data to the backend
 * If email doesn't already exist in database, stores the new user in database
 * 
 * @param {*} payload is the users data to store in database for registration {first,last,email}
 * @returns the result of the request
 */
export async function registerUser(payload) {
  const res = await axios.post(
    `${API}/api/register-new-user`,
    payload,
    {
      headers: { "Content-Type": "application/json" },
      withCredentials: true,
    }
  );
  return res.data;
}

// Checks if user is authenticated if so sets isLoggedIn to true
export async function checkAuth() {
  const res = await axios.get(`${API}/api/me`, { withCredentials: true });
  return res.data;

}

// Function to log user out
export async function logout() {
  try {
    await axios.post(`${API}/api/logout`, {}, { withCredentials: true });
    return { ok: true };
  } catch (e) {
    console.error("Logout failed");
    return { ok: "false", error: e.message };
  }
}

// Function to get user's full name
export async function getName() {
  const res = await axios.get(`${API}/api/me`, { withCredentials: true });
  const data = res?.data;
  if (data?.ok && data?.user) {
    return data.user.FirstName + ' ' + data.user.LastName;
  }
}

// Function to store users icon type in database
export async function updateUserAvatar(avatarPath) {
  const res = await axios.post(
    `${API}/api/user/avatar`,
    { avatar: avatarPath },
    {
      headers: { "Content-Type": "application/json" },
      withCredentials: true,
    }
  );
  return res.data;
}

export async function createPost({ title, content, tags, category }) {
  const res = await axios.post(
    `${API}/api/create-post`,
    { title, content, tags, category },
    { withCredentials: true, }
  );
  return res.data;
}

export async function uploadImage(file) {
  const imgForm = new FormData();
  imgForm.append('image', file);

  const res = await axios.post(`${API}/api/upload-image`, imgForm, {
    withCredentials: true,
    headers: { 'Content-Type': 'multipart/form-data' }
  });

  if (res.data && res.data.url && !res.data.url.startsWith('http')) {
    res.data.url = `${API}${res.data.url}`;
  }

  return res.data;
}

export async function fetchPosts({ categoryId = null, limit = 5, sort = 'latest', page = 1 } = {}) {
  const url = categoryId 
    ? `${API}/api/categories/${categoryId}/posts`
    : `${API}/api/posts`;

  const res = await axios.get(url, {
    params: { limit, sort, page },
    withCredentials: true
  });

  const data = res.data;

  if (data.posts) {
    data.posts = data.posts.map(post => ({
      ...post,
      likeCount: post.likeCount ?? 0,
      commentCount: post.commentCount ?? 0,
      tags: post.tags || []
    }));
  }

  return data;
}

export async function getTags() {
  const res = await axios.get(`${API}/api/tags`, { withCredentials: true });
  const items = res.data.items || [];
  return items.map(r => ({
    tagId: Number(r.TagID ?? r.tagId ?? r.id),
    name: r.Name ?? r.name
  }));
}