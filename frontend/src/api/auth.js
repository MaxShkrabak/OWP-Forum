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
 * 
 * @param {string} email - The email user is trying to sign-in with
 * @returns the result of the request and if email exists, for example { ok: true, emailExists: true}
 */
export async function verifyEmail(email) {
  const res = await axios.post(
    `${API}/api/verify-email`,
    { email },
    { headers:  { "Content-Type": "application/json" } }
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
    payload ,
    { headers: { "Content-Type": "application/json" } }
    );
    return res.data;
}

export function logout() {
  AuthStatus.value = false;
}

export function checkAuthStatus() {
  return AuthStatus.value;
}

export { AuthStatus };
