<template>
  <div class="terms-overlay" @click.self.prevent>
    <div class="terms-box" role="dialog" aria-modal="true">
      <h2 class="title">Terms & Privacy</h2>

      <p class="text">
        By using the OWP Forum, you agree to the
        <a href="/terms" target="_blank" rel="noopener noreferrer">
          Terms of Service
        </a>
        and
        <a href="/privacy" target="_blank" rel="noopener noreferrer">
          Privacy Notice
        </a>.
      </p>

      <label class="check">
        <input type="checkbox" v-model="agreed" />
        <span>I agree</span>
      </label>

      <button class="btn" :disabled="!agreed || saving" @click="submit">
        {{ saving ? "Saving..." : "Continue" }}
      </button>

      <p v-if="error" class="error">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import client from "@/api/client";
import { termsAccepted } from "@/stores/userStore";

const emit = defineEmits(["accepted"]);

const agreed = ref(false);
const saving = ref(false);
const error = ref("");

async function submit() {
  error.value = "";
  saving.value = true;

  try {
    const res = await client.post("/accept-terms");
    if (res.data?.ok) {
      termsAccepted.value = true;
      emit("accepted");
      return;
    }
    error.value = res.data?.error || "Failed to save agreement.";
  } catch (e) {
    error.value = "Failed to save agreement.";
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.terms-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.terms-box {
  width: min(560px, calc(100vw - 32px));
  background: #fff;
  border-radius: 10px;
  padding: 22px;
}

.title {
  margin: 0 0 10px;
}

.text {
  margin: 0 0 14px;
  line-height: 1.5;
}

.text a {
  color: #0000EE;
}

.text a:visited {
  color: #551A8B;
}

.check {
  display: flex;
  gap: 10px;
  align-items: center;
  margin: 12px 0 16px;
}

.btn {
  width: 100%;
  padding: 12px 12px;
  border: 0;
  border-radius: 8px;
  cursor: pointer;

  background-color: #043927;
  color: #fff;
  font-weight: 700;

  transition: background-color 0.2s ease, opacity 0.2s ease;
}

.btn:hover {
  background-color: #043927;
}

.btn:active {
  background-color: #032a1c;
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  background-color: #043927;
  color: #fff;
}

.btn:disabled:hover {
  background-color: #043927;
}

.error {
  color: #b00020;
  margin-top: 10px;
}
</style>
