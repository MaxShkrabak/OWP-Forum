<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { verifyOtp } from '@/api/auth';
import { syncProfileOnLoad } from '@/stores/userStore';
import '/src/assets/forumAuth.css'

const route = useRoute();
const router = useRouter();
const email = ref(String(route.query.email || ''));
const otp = ref('');

const loading = ref(false);
const message = ref('');
const errorMsg = ref('');
const showErrorModal = ref(false);
const errorMessage = ref('');

const canSubmit = computed(() => otp.value.trim().length === 6 && !!email.value);

function openErrorModal(msg) {
  errorMessage.value = msg;
  showErrorModal.value = true;
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
    const res = await verifyOtp(email.value.trim(), otp.value.trim());
    
    if (res?.ok) {
      await syncProfileOnLoad();
      router.push({ name: "ForumHome" });
      message.value = 'Verified! You are now signed in.';
    } else {
      const msg = res?.message || 'Incorrect or expired code.';
      errorMsg.value = msg;
      openErrorModal(msg);
    }
  } catch {
    const msg = 'Network error while verifying.';
    errorMsg.value = msg;
    openErrorModal(msg);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <section class="auth-wrap" aria-label="Verify Passcode">
    <div class="auth-card">
      <div class="form-inner">
        <h1 class="card-title">Verify Passcode</h1>

        <form class="form" @submit.prevent="onSubmit">
          <label class="label text-black" for="code">Passcode</label>
          <input
            id="otp"
            class="form-control"
            type="text"
            maxlength="6"
            placeholder="123456"
            inputmode="numeric"
            autocomplete="one-time-code"
            v-model.trim="otp"
          />

          <button class="btn" type="submit" :disabled="!canSubmit || loading">
            <span class="fs-6 fw-normal">Submit</span>
          </button>
        </form>

        <div class="help-stack">
          <p class="help text-black">
            Please enter the six-digit passcode we emailed to you and click
            <em>Submit</em>. If you copy and paste the passcode, be sure not to include any spaces around or within the code.
          </p>

          <p class="help small">
            Haven’t received the email yet? Please make sure that you’ve entered the correct email address and that it is registered with out site. The 
            email you have entered is
            <strong>{{ email || '—' }}</strong>. Be advised that the passcode can take up to a minute to be received.
          </p>
          <br>

          <p class="help small">
            The passcode email is sent from the
            <em>noreply@owp.csus.edu</em> email address, please make sure that emails from this address are not being sent to your spam or junk folder.
          </p>
        </div>

        <div class="resend">
          <span>First time here? Please
            <RouterLink to="/register" class="link-btn">Register</RouterLink>
          </span>
        </div>

        <p v-if="message" class="notice success">{{ message }}</p>
        <p v-if="errorMsg" class="notice error">{{ errorMsg }}</p>
      </div>
    </div>

    <div v-if="showErrorModal" class="modal-mask" @click.self="showErrorModal = false">
      <div class="modal-container">
        <h3 class="modal-title">Verification Failed</h3>
        <p class="modal-message">{{ errorMessage }}</p>
        <div class="modal-actions">
          <button class="modal-btn" @click="showErrorModal = false">OK</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>

.label {
  display: block;
  font-size: 1.2rem;
  font-weight: 400;
  color: #000000;
  margin-bottom: 0.65rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 1rem;
  padding: 0.55rem 1.1rem;
  border-radius: 6px;
  border: 1px solid #14532d;
  background: #007a4c;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  transition: filter 120ms ease, opacity 120ms ease;
}
.btn:hover { filter: brightness(1.05); }
.btn:disabled { opacity: 0.65; cursor: not-allowed; }

/* Resend section */
.resend {
  margin-top: 8em;
}

.link-btn {
  background: none;
  border: none;
  color: #007a4c;
  font-weight: 700;
  text-decoration: underline;
  cursor: pointer;
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

</style>
