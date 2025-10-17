<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { requestOtp, verifyOtp } from '@/api/auth'; // you already have this

const route = useRoute();
const email = ref(String(route.query.email || '')); // comes from login/register redirect
const code  = ref('');

const loading = ref(false);
const message = ref('');
const errorMsg = ref('');

const canSubmit = computed(() => code.value.trim().length === 6 && !!email.value);

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

async function onSubmit() {
  errorMsg.value = '';
  message.value = '';
  if (!canSubmit.value) {
    errorMsg.value = 'Enter your email and the 6-digit passcode.';
    return;
  }
  try {
    loading.value = true;
    const res = await verifyOtp(email.value.trim(), code.value.trim());
    if (res?.ok) {
      message.value = 'Verified! You are now signed in.';
      if (res.token) localStorage.setItem('token', res.token);
      // TODO: this.$router.push('/') if desired
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
  <div class="page">
    <div class="panel">
      <h1 class="title">Verify Passcode</h1>

      <form class="form" @submit.prevent="onSubmit">
        <label class="label" for="code">Passcode</label>
        <input
          id="code"
          class="input"
          type="text"
          maxlength="6"
          placeholder="123456"
          inputmode="numeric"
          autocomplete="one-time-code"
          v-model.trim="code"
        />

        <button class="btn" type="submit" :disabled="!canSubmit || loading">
          Submit
        </button>
      </form>

      <p class="help">
        Please enter the six digit passcode we emailed to you and click
        <em>Submit</em>. If you copy and paste the passcode, be sure not to
        include any spaces around or within the code.
      </p>

      <p class="help small">
        Haven’t received the email yet? Please make sure that you’ve entered the correct email address and that it is
        registered with our site. The email you have entered is <strong>{{ email || '—' }}</strong>.
        Be advised that the passcode can take up to a minute to be received.
      </p>

      <p class="help small">
        The passcode email is sent from the <em>noreply@owp.csus.edu</em> email address; please make sure that emails
        from this address are not being sent to your spam or junk folder.
      </p>

      <div class="resend">
        <button class="link-btn" :disabled="secondsLeft>0 || loading" @click="onResend">
          {{ secondsLeft > 0 ? `Resend in ${secondsLeft}s` : 'Resend passcode' }}
        </button>
      </div>

      <p v-if="message" class="message ok">{{ message }}</p>
      <p v-if="errorMsg" class="message err">{{ errorMsg }}</p>
    </div>
  </div>
</template>

<style scoped>
.page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 16px 48px;
}
.panel {
  background: #eef1f3;           /* light gray panel */
  border-radius: 4px;
  padding: 36px 28px 28px;
}
.title {
  font-size: 36px;
  font-weight: 700;
  margin: 0 0 28px 0;
  color: #111;
}
.form {
  max-width: 460px;
  margin: 0 0 16px 0;
}
.label {
  display: block;
  font-size: 14px;
  margin-bottom: 6px;
  color: #222;
}
.input {
  width: 320px;
  max-width: 100%;
  height: 36px;
  padding: 6px 10px;
  border: 1px solid #cfd6dc;
  border-radius: 3px;
  background: #fff;
  outline: none;
}
.input:focus {
  border-color: #2b5d34;         /* Sac State-ish green focus */
  box-shadow: 0 0 0 2px rgba(43,93,52,0.15);
}
.btn {
  margin-top: 12px;
  height: 36px;
  padding: 0 16px;
  border: none;
  border-radius: 4px;
  background: #2b5d34;           /* green button */
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
.btn:disabled {
  opacity: 0.6;
  cursor: default;
}
.help {
  margin-top: 14px;
  line-height: 1.55;
  color: #333;
}
.help.small { font-size: 13px; color: #555; }
.resend { margin-top: 8px; }
.link-btn {
  background: none;
  border: none;
  color: #2b5d34;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}
.link-btn:disabled {
  color: #8aa293;
  text-decoration: none;
  cursor: default;
}
.message {
  margin-top: 12px; font-size: 14px;
}
.message.ok { color: #1b7f2a; }
.message.err { color: #b3261e; }
</style>
