<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { requestOtp } from '@/api/auth';

const router = useRouter();
const first = ref('');
const last = ref('');
const email = ref('');
const loading = ref(false);

async function createAccount() {
  if (!first.value || !last.value || !/^\S+@\S+\.\S+$/.test(email.value)) return;

  router.push({ path: '/verify', query: { email: email.value.trim() } });

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
  <div class="page">
    <div class="panel">
      <h1 class="title">Create Account</h1>

      <div class="section-head">User Information</div>

      <form class="form" @submit.prevent="createAccount">
        <label class="label" for="first">First Name</label>
        <input id="first" v-model.trim="first" class="input" type="text" placeholder="First Name" />

        <label class="label" for="last" style="margin-top:10px;">Last Name</label>
        <input id="last" v-model.trim="last" class="input" type="text" placeholder="Last Name" />

        <label class="label" for="email" style="margin-top:10px;">Email Address</label>
        <input id="email" v-model.trim="email" class="input" type="email" placeholder="joe.hornet@owp.csus.edu" />

        <p class="note">(Your email will be your username)</p>

        <button
          class="btn"
          type="submit"
          :disabled="loading || !first || !last || !/^\S+@\S+\.\S+$/.test(email)"
        >
          <span v-if="!loading">Create Account</span>
          <span v-else class="spinner"></span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.page{max-width:1200px;margin:0 auto;padding:24px 16px 48px;}
.panel{background:#eef1f3;border-radius:4px;padding:36px 28px 28px;}
.title{font-size:32px;font-weight:700;margin:0 0 18px;color:#111}
.section-head{background:#5a786e;color:#fff;padding:8px 12px;border-radius:3px;display:inline-block;margin-bottom:16px}
.form{max-width:560px}
.label{display:block;font-size:14px;margin-bottom:6px;color:#222}
.input{width:420px;height:36px;padding:6px 10px;border:1px solid #cfd6dc;border-radius:3px;background:#fff;outline:none}
.input:focus{border-color:#2b5d34;box-shadow:0 0 0 2px rgba(43,93,52,.15)}
.note{font-size:12px;color:#a4683a;margin:6px 0 10px}
.btn{margin-top:8px;height:36px;padding:0 16px;border:none;border-radius:4px;background:#2b5d34;color:#fff;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.btn:disabled{opacity:.6;cursor:default}
.spinner{
  width:20px;height:20px;
  border:3px solid #ffffff50;
  border-top-color:#fff;
  border-radius:50%;
  animation:spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
