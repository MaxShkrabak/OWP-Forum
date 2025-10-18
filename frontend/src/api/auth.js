import axios from "axios";

const API = import.meta.env.VITE_API_BASE || 'http://localhost:8080'; // Port 8080 for php

const AuthStatus = false

export async function requestOtp(email) {
  const res = await fetch(`${API}/auth/request-otp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
  return res.json();
}

export async function verifyOtp(email, code) {
  const res = await fetch(`${API}/auth/verify-otp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, code })
  });
  return res.json();
}

/**
 * Checks if users email exists in the backend database.
 * The post request is sent to /api/verify-email with the users email
 * @param {string} email - The email user is trying to sign-in with
 * @returns the status of the request and if email exists, for example { ok: true, emailExists: true}
 */
export async function verifyEmail(email) {
  const res = await axios.post(
    `${API}/api/verify-email`,
    { email },
    { headers:  { "Content-Type": "application/json" } }
  );
  return res.data;
}

export function checkAuthStatus() {
  return AuthStatus.value;
}

export { AuthStatus };
