<script setup>
import { ref, computed } from "vue";
import client from "@/api/client";
import { useRoute } from "vue-router";

const route = useRoute();

const props = defineProps({
  post: Object,
  user: Object,
  isAuthor: { type: Boolean, default: false },
});

const emit = defineEmits(["open-modal"]);

const showDeleteConfirm = ref(false);
const isDeleting = ref(false);

const canModerate = computed(() => Number(props.user?.RoleID) >= 3);

//If admin/mod is author: DO NOT show restricted metadata button
const showMetadataButton = computed(() => canModerate.value && !props.isAuthor);

//Delete allowed for author OR mod/admin
const canDelete = computed(() => props.isAuthor || canModerate.value);

const confirmDelete = async () => {
  const targetId = route.params.id;
  if (!targetId || !canDelete.value) return;

  isDeleting.value = true;
  try {
    await client.delete(`posts/${targetId}`); 
    
    window.location.href = "/";
  } catch (err) {
    console.error("Delete failed:", err);
    showDeleteConfirm.value = false;
  } finally {
    isDeleting.value = false;
  }
};
</script>

<template>
  <div class="d-flex flex-column gap-2">
    <button
      v-if="isAuthor"
      @click="emit('open-modal', 'edit')"
      class="btn btn-outline-dark btn-sm text-start"
    >
      <i class="bi bi-pencil-square me-2"></i> Edit Post
    </button>

    <button
      v-if="canDelete"
      @click="showDeleteConfirm = true"
      class="btn btn-outline-danger btn-sm text-start"
    >
      <i class="bi bi-trash3-fill me-2"></i> Delete Post
    </button>

    <button
      v-if="showMetadataButton"
      @click="emit('open-modal', 'metadata')"
      class="btn btn-outline-dark btn-sm text-start"
    >
      <i class="bi bi-tags-fill me-2"></i> Update Category & Tags
    </button>
  </div>

  <Teleport to="body">
    <Transition name="modal" appear>
      <div
        v-if="showDeleteConfirm"
        class="modal-mask"
        @mousedown.self="showDeleteConfirm = false"
      >
        <div class="warning-card shadow-lg">
          <p class="fs-5 fw-bold">Delete this post?</p>
          <p>This post will be soft-deleted and removed from public view.</p>

          <div class="modal-actions justify-content-center">
            <button class="cancel-btn" @click="showDeleteConfirm = false" :disabled="isDeleting">
              Back
            </button>
            <button class="delete-btn px-4" @click="confirmDelete" :disabled="isDeleting">
              {{ isDeleting ? "Deleting..." : "Confirm Delete" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.admin-sidebar {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}

.modal-mask {
  position: fixed;
  z-index: 9998;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.warning-card {
  background: white;
  padding: 2rem;
  border-radius: 20px;
  max-width: 400px;
  width: 90%;
  text-align: center;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 1.5rem;
}

.delete-btn {
  background-color: #9f3323;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  padding: 10px 20px;
  transition: 0.15s;
}

.delete-btn:hover:not(:disabled) {
  background-color: #7d281b;
}

.delete-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.cancel-btn {
  background-color: #ffffff;
  border: 2px solid #cbd5e1;
  border-radius: 12px;
  color: #475569;
  font-weight: 700;
  font-size: 1rem;
  padding: 10px 24px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cancel-btn:hover {
  background-color: #f8fafc;
  border-color: #94a3b8;
}

.warning-card p.fs-5 {
  color: #64748b;
  margin-bottom: 8px;
}

.warning-card p:not(.fs-5) {
  color: #94a3b8;
  font-size: 0.95rem;
}
</style>