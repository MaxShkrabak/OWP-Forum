<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import CreatePostModal from "./CreatePostModal.vue";
import { isBanned, userRoleId } from "@/stores/userStore";
import {
  createPostBlockedUntil,
  blockPostCreationFor,
} from "@/stores/postCreationCooldown";

const isModalOpen = ref(false);
const emit = defineEmits(["post-refresh"]);

const now = ref(Date.now());
let timer;

onMounted(() => {
  timer = setInterval(() => {
    now.value = Date.now();
  }, 1000);
});

onUnmounted(() => {
  clearInterval(timer);
});

const isCooldownExempt = computed(() => Number(userRoleId.value) >= 3);

const secondsRemaining = computed(() =>
  isCooldownExempt.value
    ? 0
    : Math.max(0, Math.ceil((createPostBlockedUntil.value - now.value) / 1000)),
);

const showCooldownNotice = computed(() => secondsRemaining.value > 0);
const isCreateBlocked = computed(
  () => isBanned.value || showCooldownNotice.value,
);

async function handlePublish() {
  isModalOpen.value = false;
  emit("post-refresh");
}
</script>

<template>
  <div class="action-container">
    <button
      @click="isModalOpen = true"
      class="btn-create-post shadow-sm"
      :disabled="isCreateBlocked"
    >
      <div class="btn-content">
        <div class="icon-wrap">
          <i class="pi pi-plus-circle"></i>
        </div>

        <div class="btn-text-wrap">
          <span class="btn-text-primary">Create Post</span>
          <span v-if="showCooldownNotice" class="btn-text-secondary">
            Blocked for {{ secondsRemaining }}s
          </span>
        </div>
      </div>
    </button>

    <CreatePostModal
      v-if="isModalOpen"
      :show="isModalOpen"
      @close="isModalOpen = false"
      @published="handlePublish"
      @cooldown="blockPostCreationFor"
    />
  </div>
</template>

<style scoped>
.action-container {
  width: 100%;
}

.btn-create-post:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-create-post {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 80px;
  padding: 10px 15px;

  background: linear-gradient(135deg, #007c8a 0%, #004750 100%);
  color: white;
  border: none;
  border-radius: 12px;

  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s ease;
}
.btn-create-post i {
  font-size: 1.4rem;
}
.btn-create-post::after {
  content: "";
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.15),
    transparent
  );
  transition: 0.6s;
}
.btn-create-post:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px -6px #00475063 !important;
  background: linear-gradient(135deg, #007c8a 0%, #004750 100%);
}
.btn-create-post:hover::after {
  left: 100%;
}

.btn-content,
.icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-content {
  gap: 10px;
}

.icon-wrap {
  color: #3fbeac;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  padding: 5px;
}

.btn-text-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  line-height: 1.1;
}

.btn-text-primary {
  font-weight: 700;
  font-family: "Roboto", sans-serif;
  text-transform: uppercase;
  font-size: 1rem;
  letter-spacing: 1px;
}

.btn-text-secondary {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  opacity: 0.9;
}
</style>
