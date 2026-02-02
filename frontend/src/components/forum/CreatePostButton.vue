<script setup>
import { ref } from 'vue';
import CreatePostModal from './CreatePostModal.vue';

const isModalOpen = ref(false);
const emit = defineEmits(['post-refresh']);

async function handlePublish() {
  isModalOpen.value = false;
  emit('post-refresh');
}
</script>

<template>
  <div class="action-container">
    <button @click="isModalOpen = true" class="btn-create-post shadow-sm">
      <div class="btn-content">
        <div class="icon-wrap">
           <i class="pi pi-plus-circle"></i>
        </div>
        <span class="btn-text">Create Post</span>
      </div>
    </button>

    <CreatePostModal
      v-if="isModalOpen"
      :show="isModalOpen"
      @close="isModalOpen = false"
      @published="handlePublish"
    />
  </div>
</template>

<style scoped>
.action-container {
  width: 100%;
}

.btn-create-post {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 80px;
  padding: 10px 15px;

  background: linear-gradient(135deg, #007C8A 0%, #004750 100%);
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
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
  transition: 0.6s;
}
.btn-create-post:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px -6px #00475063 !important;
  background: linear-gradient(135deg, #007C8A 0%, #004750 100%);
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
  flex-direction: column; 
  gap: 5px;
}
.icon-wrap {
  color: #3fbeac;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  padding: 5px;
}

.btn-text {
  font-weight: 700;
  font-family: 'Roboto', sans-serif;
  text-transform: uppercase;
  font-size: 1rem;
  letter-spacing: 1px;
  text-align: center;
  line-height: 1.2;
}

@media (min-width: 423px) {
  .btn-content {
    flex-direction: row;
    gap: 8px;
  }
}
</style>