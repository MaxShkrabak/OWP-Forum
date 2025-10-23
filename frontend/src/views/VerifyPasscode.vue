<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useRouter } from 'vue-router';
import { verifyOtp } from '@/api/auth';

const route = useRoute();
const router = useRouter();
const email = ref(String(route.query.email || ''));
const otp = ref('');

const loading = ref(false);
const message = ref('');
const errorMsg = ref('');

const canSubmit = computed(() => otp.value.trim().length === 6 && !!email.value);

const secondsLeft = ref(0);
let timerId = null;

function startTimer(s = 60) {
  clearInterval(timerId);
  secondsLeft.value = s;
  timerId = setInterval(() => {
    secondsLeft.value = Math.max(0, secondsLeft.value - 1);
    if (secondsLeft.value === 0) clearInterval(timerId);
  }, 1000);
}

/**
 * TODO: Implement or remove this
 * We should implement this if we switch to random generated passcode
 * 
async function onResend() {
  errorMsg.value = '';
  message.value = '';
  if (!/^\S+@\S+\.\S+$/.test(email.value)) {
    errorMsg.value = 'Please enter a valid email.';
    return;
  }
  try {
    loading.value = true;
    const res = await requestOtp(email.value.trim());
    if (res?.ok) {
      message.value = 'A new code was sent to your email.';
      startTimer(60);
    } else {
      errorMsg.value = res?.message || 'Failed to send code.';
    }
  } catch {
    errorMsg.value = 'Network error while sending code.';
  } finally {
    loading.value = false;
  }
}
*/

async function onSubmit() {
  errorMsg.value = '';
  message.value = '';
  if (!canSubmit.value) {
    errorMsg.value = 'Enter your email and the 6-digit passcode.';
    return;
  }
  try {
    loading.value = true;
    const res = await verifyOtp(email.value.trim(), otp.value.trim());
    
    // User entered the correct passcode
    if (res?.ok) {
      router.push({ name: "ForumHome" });
      message.value = 'Verified! You are now signed in.';
    } else {
      errorMsg.value = res?.message || 'Incorrect or expired code.';
    }
  } catch {
    errorMsg.value = 'Network error while verifying.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <!-- Match login layout -->
  <section class="verify-wrap" aria-label="Verify Passcode">
    <div class="verify-card">
      <div class="form-inner">
        <h2 class="card-title">Verify Passcode</h2>

        <form class="form" @submit.prevent="onSubmit">
          <label class="label" for="code">Passcode</label>
          <input
            id="otp"
            class="input"
            type="text"
            maxlength="6"
            placeholder="123456"
            inputmode="numeric"
            autocomplete="one-time-code"
            v-model.trim="otp"
          />

          <button class="btn" type="submit" :disabled="!canSubmit || loading">
            Submit
          </button>
        </form>

        <div class="help-stack">
          <p class="help">
            Please enter the six-digit passcode we emailed to you and click
            <em>Submit</em>. If you copy and paste the passcode, be sure not to include any spaces around or within the code.
          </p>

          <p class="help small">
            Haven’t received the email yet? Please make sure that you’ve entered the correct email address and that it is registered with out site. The 
            email you have entered is:
            <strong>{{ email || '—' }}</strong>.
          </p>

          <p class="help small">
            The email may take up to a minute and comes from
            <em>noreply@owp.csus.edu</em>. Please check spam/junk folders.
          </p>
        </div>

        <div class="resend">
          <button class="link-btn" :disabled="secondsLeft>0 || loading" @click="onResend">
            {{ secondsLeft > 0 ? `Resend in ${secondsLeft}s` : 'Resend passcode' }}
          </button>
        </div>

        <p v-if="message" class="notice success">{{ message }}</p>
        <p v-if="errorMsg" class="notice error">{{ errorMsg }}</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Match login design */
.verify-wrap {
  background: #ffffff;
  width: 100%;
  min-height: 62vh; /* same height as login */
  padding: 0;
  position: relative;
  --section-gutter: clamp(10px, 2vw, 24px);
}

.verify-card {
  background: #EFF1F1;
  width: calc(100% - (var(--section-gutter) * 2));
  height: auto;
  margin: 23px auto 27px;
  display: flex;
  justify-content: flex-start;
  align-items: flex-start;
  text-align: left;
  padding: 0 clamp(32px, 12vw, 320px);
  border: none;
  border-radius: 0;
  box-shadow: none;
  box-sizing: border-box;
}

.form-inner {
  width: auto;
  max-width: clamp(36rem, 48rem, 62rem);
  height: auto;
  margin-top: clamp(0.75rem, 1.6vh, 2rem);
  padding: 2.25rem 2.25rem 2.5rem;
  box-sizing: border-box;
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
}
.input:focus {
  border-color: #14532d;
  box-shadow: 0 0 0 3px rgba(20,83,45,0.18);
}

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
.help-stack { margin-top: 1.5rem; }
.help {
  margin: 0;
  color: #4b5563;
  font-size: 1.1rem;
  line-height: 1.55;
}
.help.small { font-size: 1rem; color: #555; }
.help em { font-style: italic; }
.help + .help { margin-top: 0.5rem; }

/* Resend section */
.resend { margin-top: 1.25rem; }
.link-btn {
  background: none;
  border: none;
  color: #14532d;
  font-weight: 700;
  text-decoration: underline;
  cursor: pointer;
}
.link-btn:disabled {
  color: #8aa293;
  text-decoration: none;
  cursor: default;
}

/* Notices */
.notice {
  margin-top: 1.5rem;
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

/* Mobile */
@media (max-width: 768px) {
  .verify-wrap { --section-gutter: clamp(8px, 4vw, 16px); }
  .verify-card { padding: 0 var(--section-gutter); }
  .form-inner {
    max-width: 100%;
    margin-top: 1rem;
    padding: 1.25rem;
  }
}
</style>
