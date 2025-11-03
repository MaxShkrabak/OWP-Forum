<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { verifyEmail } from "@/api/auth";

const router = useRouter();

const email = ref("");
const sending = ref(false);
const status = ref("idle");
const errorMsg = ref("");

const isValidEmail = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val ?? "");

async function onSubmit() {
  errorMsg.value = "";
  status.value = "idle";

  if (!isValidEmail(email.value)) {
    errorMsg.value = "Please enter a valid email address.";
    status.value = "error";
    return;
  }

  sending.value = true;

  try {
    const data = await verifyEmail(email.value); // calls backend to verify if email exists
    const exists = data?.emailExists ?? false;  // true if email exists, false otherwise

    // Handles the response from the backend
    if (data.ok && exists) {
      status.value = "sent";
      router.push({ name: "VerifyPasscode", query: { email: email.value } });
    } else if (data.ok && !exists) {
      status.value = "error";
      errorMsg.value = "This email has not been registered. Please register first.";
    } else {
      status.value = "error";
      errorMsg.value = "Something went wrong. Please contact the OWP support team or try again"
    }

  } catch (e) {
    status.value = "error";
    errorMsg.value = e?.message || "Something went wrong. Please try again.";
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <!-- ====================== HEADER (placeholder) ====================== -->
  <header class="site-header" role="banner" aria-label="Header">
    <h1>LOG IN PAGE</h1>
    <nav class="inline-nav">
      <router-link to="/">Home</router-link> | | |
      <router-link to="/register">register</router-link> | | |
      <router-link to="/profile">My Profile</router-link>
    </nav>
  </header>

  <router-view></router-view>

  <!-- ====================== BODY ====================== -->
  <section class="login-wrap" aria-label="Login">
    <div class="login-card" role="region" aria-labelledby="login-title">
      <div class="form-inner">
        <h2 id="login-title" class="card-title">Login</h2>

        <form @submit.prevent="onSubmit" novalidate>
          <label class="label" for="email">Email</label>
          <input
            id="email"
            type="email"
            v-model.trim="email"
            class="input"
            placeholder="joe.hornet@owp.csus.edu"
            :aria-invalid="status === 'error'"
            aria-describedby="email-help email-error"
          />

          <button class="btn" type="submit" :disabled="sending">
            <span v-if="!sending">Get passcode</span>
            <span v-else>Sending…</span>
          </button>

          <div id="email-help" class="help-stack">
            <p class="help">Enter the email address associated with your account and click</p>
            <p class="help"><em>Get passcode.</em> We’ll email you a passcode for a password-free login.</p>
            <p class="help">It may take up to three minutes to receive the passcode.</p>
          </div>

          <p class="tiny">
            First time here? Please
            <router-link to="/register">create a new account</router-link>.
          </p>

          <p v-if="status === 'sent'" class="notice success" role="status">
            Check your email for a six-digit passcode. If you don’t see it, check spam/junk.
          </p>

          <p v-if="status === 'error'" id="email-error" class="notice error" role="alert">
            {{ errorMsg || "Please enter a valid email address." }}
          </p>
        </form>
      </div>
    </div>
  </section>

  <!-- ====================== FOOTER (placeholder) ====================== -->
  <footer class="site-footer" role="contentinfo" aria-label="Footer"></footer>
</template>

<style scoped>
/* NAV */
.inline-nav {
  display: inline-block;
  margin-bottom: 1rem;
  font-size: 0.95rem;
}

/* BODY */
.login-wrap {
  background: #ffffff;
  width: 100%;
  min-height: 62vh; /* was 70vh — slightly shorter */
  padding: 0;
  position: relative;
  --section-gutter: clamp(10px, 2vw, 24px);
}

.login-card {
  background: #EFF1F1;
  width: calc(100% - (var(--section-gutter) * 2));
  height: auto;
  min-height: 0;
  margin: 23px auto 27px;
  max-width: none;

  /* flex layout for left alignment */
  display: flex;
  justify-content: flex-start;
  align-items: flex-start;
  text-align: left;

  /* ✅ symmetric left and right padding */
  padding: 0 clamp(32px, 12vw, 320px);

  border: none;
  border-radius: 0;
  box-shadow: none;
  box-sizing: border-box;
}

/* Form column */
.form-inner {
  width: auto;
  max-width: clamp(36rem, 48rem, 62rem);
  height: auto;
  margin-top: clamp(0.75rem, 1.6vh, 2rem);
  padding: 2.25rem 2.25rem 2.5rem;
  box-sizing: border-box;
}

/* Extra spacing before "First time here?" */
.login-card .tiny {
  margin-top: 8rem;
}

/* Mobile tweaks */
@media (max-width: 768px) {
  .login-wrap { --section-gutter: clamp(8px, 4vw, 16px); }

  .login-card {
    padding: 0 var(--section-gutter);
  }

  .form-inner {
    max-width: 100%;
    margin-top: 1rem;
    padding: 1.25rem;
  }
}

/* Typography */
.card-title {
  margin: 0 0 3.75rem 0;
  font-size: 2.5rem;
  line-height: 1.2;
  font-weight: 800;
  color: #111827;
}
.label {
  display: block;
  font-size: 1.2rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.65rem;
}

/* Inputs */
.input {
  display: block;
  width: 100%;
  max-width: clamp(22rem, 55%, 35rem);
  font-size: 1.2rem;
  line-height: 1.35;
  padding: 0.75rem 0.9rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  outline: none;
  transition: box-shadow 120ms ease, border-color 120ms ease;
  background-color: #ffffff;
  color: #111827;
}
@media (max-width: 480px) {
  .input { max-width: 100%; }
}
.input:focus {
  border-color: #14532d;
  box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.18);
}

/* Button */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 1rem;
  padding: 0.75rem 1.1rem;
  border-radius: 6px;
  border: 1px solid #14532d;
  background: #1b5e20;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  transition: filter 120ms ease, opacity 120ms ease;
}
.btn:hover { filter: brightness(1.05); }
.btn:disabled { opacity: 0.65; cursor: not-allowed; }

/* Helper text */
.help-stack { margin-top: 1.1rem; }
.help-stack .help {
  margin: 0;
  color: #4b5563;
  font-size: 1.15rem;
}
.help-stack .help + .help { margin-top: 0.5rem; }
.help em { font-style: italic; }

/* Tiny note & link emphasis */
.tiny {
  margin-top: 2.5rem;
  font-size: 1.1rem;
  color: #374151;
}
.tiny a {
  color: #14532d;
  text-decoration: underline;
  font-weight: 700;
}
.tiny a:hover { text-decoration-thickness: 2px; }

/* Notices */
.notice {
  margin-top: 1rem;
  padding: 0.8rem 0.95rem;
  border-radius: 6px;
  font-size: 1.05rem;
}
.notice.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}
.notice.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Page heading */
h1 {
  margin: 0 0 0.25rem 0;
  font-size: 1rem;
  font-weight: 700;
  color: #374151;
}
</style>
