<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { registerUser } from "@/api/auth";
import "/src/assets/style.css";

const router = useRouter();
const first = ref("");
const last = ref("");
const email = ref("");
const loading = ref(false);

async function createAccount() {
  if (!first.value || !last.value || !/^\S+@\S+\.\S+$/.test(email.value))
    return;

  loading.value = true;

  try {
    // Load users data into payload for backend
    const payload = {
      first: first.value,
      last: last.value,
      email: email.value,
    };

    // Send the data to backend and store repsonse in res
    const res = await registerUser(payload);

    // User was succesfuly stored in database and routes to OTP page
    if (res.ok) {
      router.push({ path: "/verify", query: { email: payload.email } });
    }
  } catch (err) {
    // User email already exists or something else went wrong
    if (err.response && err.response.data) {
      alert(err.response.data.message);
    } else {
      alert("Something went wrong. Please try again later.");
    }
  } finally {
    loading.value = false;
  }

  /*
  loading.value = true;
  try {
    // (Optional) Send registration data first
    // await fetch('/auth/register', { ... });

    const res = await requestOtp(email.value.trim());
    if (res.ok) {
      router.push({ path: '/verify', query: { email: email.value.trim() } });
    } else {
      alert(res.message || 'Failed to send passcode.');
    }
  } catch {
    alert('Network error.');
  } finally {
    loading.value = false;
  }
    */
}
</script>

<template>
  <div class="register-page">
    <div class="page">
      <h1 class="">Create Account</h1>
      If you have an existing OWP account, please use the
      <router-link to="/login" class="login-link"
        ><i class="fa fa-sign-in" aria-hidden="true"></i
        ><span>User Login</span></router-link
      > page.
      <div class="panel">
        <div class="section-head">New User Information</div>

        <form class="form" @submit.prevent="createAccount">
          <label for="first" class="label">First Name</label>
          <input
            id="first"
            v-model.trim="first"
            type="text"
            placeholder="Joe"
            class="form-label form-control"
          />

          <label for="last" class="label">Last Name</label>
          <input
            id="last"
            v-model.trim="last"
            type="text"
            placeholder="Hornet"
            class="form-label form-control"
          />

          <div class="label-row">
            <label for="ssn" class="label">Last 4 digits of your SSN/SIN</label>
            <span class="note-inline">(Used for verification only)</span>
          </div>
          <input
            id="ssn"
            type="text"
            placeholder="1234"
            maxlength="4"
            class="form-label form-control"
          />

          <div class="label-row">
            <label for="email" class="label">Email Address</label>
            <span class="note-inline">(Your email will be your username)</span>
          </div>
          <input
            id="email"
            v-model.trim="email"
            type="email"
            placeholder="joe.hornet@owp.csus.edu"
            class="form-label form-control"
          />

          <button
            class="btn"
            type="submit"
            :disabled="
              loading || !first || !last || !/^\S+@\S+\.\S+$/.test(email)
            "
          >
            <span v-if="!loading">Create Account</span>
            <span v-else class="spinner"></span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* === Page Layout === */
.page {
  max-width: 1300px;
  margin: 0 auto;
  padding: 25px 32px 100px;
  font-family: "Helvetica Neue", Arial, sans-serif;
}

.register-page {
  background: #f2f4f4;
  border: 1px solid #d0d6d5;
}

/* === Login link (below title) === */
.login-link {
  display: inline-block;
  margin-bottom: 5px;
  color: #007a4c;
  font-weight: 600;
  text-decoration: none;
}
.login-link span {
  text-decoration: underline;
  padding-left: 3px;
  font-size: 17px;
}
.login-link i {
  width: 17px;
  height: 17px;
  font-size: 20px;
}
.login-link:hover {
  text-decoration: underline;
}

/* === Panel === */
.panel {
  border-radius: 0;
  padding: 0;
}

/* === Section Head === */
.section-head {
  background-color: rgba(38, 78, 68, 0.75);
  color: #fff;
  padding: 10px 10px;
  font-weight: 200;
  font-size: 18px;
  width: 100%;
  box-sizing: border-box;
}

/* === Form === */
.form {
  display: flex;
  padding-top: 2em;
  flex-direction: column;
  width: 100%;
  box-sizing: border-box;
}

/* Labels */
.label {
  font-size: 15px;
  color: #222;
  margin-top: 10px;
  display: block;
}

.label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  margin-bottom: 6px;
}

/* Inputs (smaller height now) */

.form-control {
  margin: 0.2em 0em;
  padding: 0.8em;
}

.input {
  width: 100%;
  height: 32px; /* reduced from 38px */
  border: 1px solid #cfd6dc;
  border-radius: 2px;
  padding: 4px 8px; /* tighter padding */
  font-size: 15px;
  background: #fff;
}
.input:focus {
  outline: none;
  border-color: #2b5d34;
  box-shadow: 0 0 0 2px rgba(43, 93, 52, 0.15);
}

/* Inline notes */
.note-inline {
  font-size: 17px;
  color: #c6671d;
}

/* Button */
.btn {
  margin-top: 18px;
  height: 40px; /* slightly smaller button to match inputs */
  width: fit-content;
  background: #007a4c;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  padding: 0 16px;
  cursor: pointer;
}
.btn:hover:not(:disabled) {
  background: #007a4c;
}
.btn:disabled {
  opacity: 0.6;
  cursor: default;
}

/* Spinner */
.spinner {
  width: 18px;
  height: 18px;
  border: 3px solid #ffffff50;
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
