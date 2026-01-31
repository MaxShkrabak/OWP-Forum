<script setup>
import { ref } from 'vue';
import CreatePost from '@/views/CreatePost.vue';

const isModalOpen = ref(false);
const emit = defineEmits(['post-refresh']);

async function handlePublish() {
  isModalOpen.value = false;
  emit('post-refresh');  // Notify homepage to refresh
}
</script>

<template>
    <!-- Will show only if logged in -->
    <button @click="isModalOpen = true" class="btn">
        <div class="row text-center fs-5 text-nowrap text-white">
      <div class="col-12">
        <span class="roboto-bold">CREATE<br /> </span>
      </div>
      <div class="col-12">
        <span class="roboto-bold">POST<br /> </span>
      </div>
    </div>
  </button>

  <CreatePost
    :show="isModalOpen"
    @close="isModalOpen = false"
    @published="handlePublish"
  />
</template>

<style scoped>
.btn {
  background-color: #007C8A;
  height: 84px;
  width: 100%;
}
</style>