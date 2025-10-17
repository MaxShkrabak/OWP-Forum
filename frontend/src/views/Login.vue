<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { requestOtp } from '@/api/auth';

const router = useRouter();
const email = ref('');
const loading = ref(false);

async function getPasscode() {
  if (!/^\S+@\S+\.\S+$/.test(email.value)) return;

  router.push({ path: '/verify', query: { email: email.value.trim() } });

  /*  need backend stuff for this to work
  loading.value = true;
  try {
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
  <div class="page">
    <div class="panel">
      <h1 class="title">Login</h1>

      <form class="form" @submit.prevent="getPasscode">
        <label class="label" for="email">Email</label>
        <input
          id="email"
          v-model.trim="email"
          type="email"
          class="input"
          placeholder="joe.hornet@owp.csus.edu"
          autocomplete="email"
        />

        <button
          class="btn"
          type="submit"
          :disabled="loading || !/^\S+@\S+\.\S+$/.test(email)"
        >
          <span v-if="!loading">Get passcode</span>
          <span v-else class="spinner"></span>
        </button>
      </form>

      <p class="help">
        Enter the email address associated with your OWP account and click
        <em>Get passcode</em>. We will email you a passcode for a password-free login.
      </p>

      <p class="help small">
        First time here? Please
        <router-link to="/register">create a new account</router-link>.
      </p>
    </div>
  </div>
</template>

<style scoped>
.page{max-width:1200px;margin:0 auto;padding:24px 16px 48px;}
.panel{background:#eef1f3;border-radius:4px;padding:36px 28px 28px;}
.title{font-size:36px;font-weight:700;margin:0 0 24px;color:#111;}
.form{max-width:460px;margin-bottom:12px}
.label{display:block;font-size:14px;margin-bottom:6px;color:#222}
.input{width:340px;height:36px;padding:6px 10px;border:1px solid #cfd6dc;border-radius:3px;background:#fff;outline:none}
.input:focus{border-color:#2b5d34;box-shadow:0 0 0 2px rgba(43,93,52,.15)}
.btn{margin-top:12px;height:36px;padding:0 16px;border:none;border-radius:4px;background:#2b5d34;color:#fff;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.btn:disabled{opacity:.6;cursor:default}
.spinner{
  width:20px;height:20px;
  border:3px solid #ffffff50;
  border-top-color:#fff;
  border-radius:50%;
  animation:spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.help{margin-top:14px;line-height:1.55;color:#333}
.help.small{font-size:13px;color:#555}
</style>
