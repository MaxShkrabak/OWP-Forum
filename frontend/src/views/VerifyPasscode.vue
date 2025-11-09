<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { verifyOtp } from '@/api/auth';
import { syncProfileOnLoad } from '@/stores/userStore';
import '/src/assets/style.css'

const route = useRoute();
const router = useRouter();
const email = ref(String(route.query.email || ''));
const otp = ref('');

const loading = ref(false);
const message = ref('');
const errorMsg = ref('');

const canSubmit = computed(() => otp.value.trim().length === 6 && !!email.value);

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
      await syncProfileOnLoad(); // get user data
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
  <section class="auth-wrap" aria-label="Verify Passcode">
    <div class="auth-card">
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

</style>
