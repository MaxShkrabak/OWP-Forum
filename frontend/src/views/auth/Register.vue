<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { registerUser, requestOtp } from "@/api/auth";
import "/src/assets/forumAuth.css";

const router = useRouter();
const first = ref("");
const last = ref("");
const ssn = ref("");
const email = ref("");
const loading = ref(false);
const showErrorModal = ref(false);
const errorMessage = ref("");

const nameRegex = /^[A-Za-z]+$/;
const ssnRegex = /^\d{4}$/;
const emailRegex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;

function openErrorModal(message) {
  errorMessage.value = message;
  showErrorModal.value = true;
}

async function createAccount() {
  if (
    !nameRegex.test(first.value) ||
    !nameRegex.test(last.value) ||
    !ssnRegex.test(ssn.value) ||
    !emailRegex.test(email.value)
  )
    return;

  loading.value = true;

  try {
    const payload = {
      first: first.value,
      last: last.value,
      email: email.value,
    };

    const res = await registerUser(payload);

    if (res.ok) {
      const resOtp = await requestOtp(payload.email);
      if (resOtp.ok) {
        router.push({ path: "/verify", query: { email: payload.email } });
      } else {
        openErrorModal(resOtp.message || "Failed to send passcode.");
      }
    } else {
      openErrorModal(res.message || "Failed to create account. Please try again.");
    }
  } catch (err) {
    const message =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      "Something went wrong. Please try again later.";

    openErrorModal(message);
  } finally {
    loading.value = false;
  }
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
            v-model.trim="ssn"
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
              loading ||
              !nameRegex.test(first) ||
              !nameRegex.test(last) ||
              !ssnRegex.test(ssn) ||
              !emailRegex.test(email)
            "
          >
            <span v-if="!loading">Create Account</span>
            <span v-else class="spinner"></span>
          </button>
        </form>
      </div>
    </div>

    <div v-if="showErrorModal" class="modal-mask" @click.self="showErrorModal = false">
      <div class="modal-container">
        <h3 class="modal-title">Unable to Create Account</h3>
        <p class="modal-message">{{ errorMessage }}</p>
        <div class="modal-actions">
          <button class="modal-btn" @click="showErrorModal = false">OK</button>
        </div>
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
  height: 32px;
  border: 1px solid #cfd6dc;
  border-radius: 2px;
  padding: 4px 8px;
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
  height: 40px;
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

/* Modal */
.modal-mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-container {
  width: min(460px, calc(100vw - 32px));
  background: #fff;
  border-radius: 10px;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-title {
  margin: 0 0 12px 0;
  font-size: 22px;
  color: #264e44;
}

.modal-message {
  margin: 0;
  font-size: 16px;
  color: #222;
  line-height: 1.5;
}

.modal-actions {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.modal-btn {
  background: #00573f;
  color: #fff;
  border: none;
  border-radius: 999px;
  padding: 10px 24px;
  font-weight: 600;
  cursor: pointer;
}

.modal-btn:hover {
  background: #004832;
}

.modal-btn:hover {
  opacity: 0.95;
}
</style>
