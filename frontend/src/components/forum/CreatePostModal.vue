<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { createPost, getTags, getCategories } from "@/api/posts";
import { uploadImage } from "@/api/media";
import { fullName, userAvatar, isLoggedIn, userRole } from "@/stores/userStore";
import UserRole from "@/components/user/UserRole.vue";
import TextEditor from "@/components/forum/TextEditor.vue";

const MAX_TITLE_LEN = 125;
const MAX_TAGS = 5;

const props = defineProps({
  show: Boolean,
  loading: Boolean
});
const emit = defineEmits(["close", "published"]);

// Form state
const form = ref({
  title: "",
  category: "",
  content: "",
  tags: []
});

const editor = ref(null);

// UI State
const showWarningDialog = ref(false);
const showPublishConfirm = ref(false);
const tagSearch = ref("");
const showTagPopup = ref(false);
const allTags = ref([]);
const tagContainerRef = ref(null);
const allCategories = ref([]);

// Validation
const hasUnsavedChanges = computed(() => {
  const textContent = form.value.content.replace(/<[^>]*>/g, '').trim();
  return form.value.title.trim().length > 0 ||
         textContent.length > 0 ||
         form.value.category !== "" ||
         form.value.tags.length > 0;
});

const titleLength = computed(() => form.value.title.length);

const canPublish = computed(() => {
  let textContent = form.value.content.replace(/<[^>]*>/g, "");
  textContent = textContent.replace(/&nbsp;/g, " ").trim();
  const hasContent = textContent.length > 0 || form.value.content.includes("<img");

  return (
    form.value.title.trim().length > 0 &&
    form.value.title.trim().length <= MAX_TITLE_LEN &&
    form.value.category !== "" && hasContent
  );
});

// Tag Logic
async function loadTags() {
  try {
    allTags.value = await getTags();
  } catch (e) { 
    console.error("Tag load error:", e);
  }
}

// Category Logic
async function loadCategories() {
  try {
    allCategories.value = await getCategories();
  } catch (e) {
    console.error("Category load error:", e);
  }
}

const filteredTags = computed(() => {
  const q = tagSearch.value.trim().toLowerCase();
  return allTags.value
    .filter(t => !form.value.tags.includes(t.tagId))
    .filter(t => (q ? t.name.toLowerCase().includes(q) : true))
    .slice(0, 20);
});

function tagNameById(id) {
  return allTags.value.find(t => t.tagId === id)?.name || `#${id}`;
}

function isOfficialTag(id) {
  return allTags.value.find(t => t.tagId === id)?.name == 'Official' || false
}

const removeTag = (id) => {
  form.value.tags = form.value.tags.filter(tid => tid !== id);
};

function handleClickOutside(event) {
  if (tagContainerRef.value && !tagContainerRef.value.contains(event.target)) {
    showTagPopup.value = false;
  }
}

// Handle closing create post modal
function handleCloseRequest() {
  if (hasUnsavedChanges.value) {
    showWarningDialog.value = true;
  } else {
    emit("close");
  }
}

// Discard post content
function confirmDiscard() {
  form.value = { title: "", category: "", content: "", tags: [] };
  showWarningDialog.value = false;
  emit("close");
}

// Publish post
async function doPublish() {
  try {
    await createPost({
      title: form.value.title.trim(),
      content: form.value.content,
      tags: form.value.tags,
      category: form.value.category || null,
    });
    form.value = { title: "", category: "", content: "", tags: [] };
    emit("published");
  } catch (err) {
    alert("An error occurred while publishing.");
  } finally {
    showPublishConfirm.value = false;
  }
}

onMounted(() => {
  loadTags();
  loadCategories();
  document.addEventListener("mousedown", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("mousedown", handleClickOutside);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal" appear>
      <div v-if="show" class="modal-mask" @mousedown.self="handleCloseRequest">

        <!-- Create Post Modal -->
        <div class="modal-container">
          <header class="modal-header">
            <h3>CREATE POST</h3>
            <button class="close-x" @click="handleCloseRequest">&times;</button>
          </header>

          <main class="modal-body">
            <div class="title-row">
              <!-- Title Input -->
              <div class="input-group flex-grow-1 position-relative">
                <label v-if="!form.title" class="title-placeholder">
                  Title<span class="star-red">*</span>
                </label>
                
                <input
                  v-model="form.title"
                  class="title-input"
                  :maxlength="MAX_TITLE_LEN"
                />
                <span class="char-counter" :class="{ 'text-danger': titleLength >= MAX_TITLE_LEN }">
                  {{ titleLength }}/{{ MAX_TITLE_LEN }}
                </span>
              </div>
              
              <!-- User Details -->
              <div class="user-info-section" v-if="isLoggedIn">
                <div class="user-meta text-end">
                  <span class="user-name">{{ fullName }}</span>
                  <UserRole :role="userRole" />
                </div>
                <div class="avatar-circle">
                   <img :src="userAvatar" alt="icon" class="avatar-img" />                   
                </div>
              </div>
            </div>

            <div class="controls-bar">
              <!-- Category Select -->
              <div class="category-side">
                <label class="form-label-small">Category<span v-if="!form.category" class="star-red">*</span></label>
                <select v-model="form.category" class="clean-select-rect">
                  <option value="">Select Category</option>
                  <option v-for="cat in allCategories" :key="cat.categoryId" :value="cat.categoryId">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
              
              <div class="tags-side" ref="tagContainerRef">
                <label class="form-label-small">Tags ({{ form.tags.length }}/{{ MAX_TAGS }})</label>
                <div class="tag-adder-container">
                  <!-- Tags Button-->
                  <div class="tag-trigger-group">
                    <button
                      type="button"
                      class="tag-circle-add"
                      @click="showTagPopup = !showTagPopup"
                      :disabled="form.tags.length >= MAX_TAGS"
                    >
                      +
                    </button>
                    <!-- Tag Dropdown Box -->
                    <div v-if="showTagPopup" class="tag-floating-box shadow-lg">
                      <input v-model="tagSearch" class="tag-search-mini" placeholder="Search..." @click.stop />
                      <div class="tag-options-list">
                        <button
                          v-for="t in filteredTags"
                          :key="t.tagId"
                          class="tag-opt"
                          @click="() => { form.tags.push(t.tagId); tagSearch = ''; showTagPopup = false; }"
                          >
                          {{ t.name }}
                        </button>
                      </div>
                    </div>
                  </div>
                  <!-- Active Tags -->
                  <div class="tag-chips-flow">
                    <span v-for="tid in form.tags" :key="tid" :class="isOfficialTag(tid) ? 'tag-chip-pill-mod-admin' : 'tag-chip-pill'">
                      {{ tagNameById(tid) }}
                      <button class="chip-remove" @click="removeTag(tid)">&times;</button>
                    </span>
                    <span v-if="form.tags.length === 0" class="muted-hint">No tags added yet</span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Text Editor -->
            <TextEditor v-model="form.content" class="custom-editor" ref="editor" />
          </main>

          <!-- Publish or Cancel Options-->
          <footer class="modal-footer">
            <div class="footer-hint"></div>
            <div class="footer-actions">
              <button class="cancel-btn" @click="handleCloseRequest">Cancel</button>
              <button
                class="publish-btn"
                :disabled="!canPublish || loading"
                @click="showPublishConfirm = true"
              >
                {{ loading ? 'Publishing...' : 'Publish Post' }}
              </button>
            </div>
          </footer>
        </div>

        <!-- Publish Confirmation -->
        <div v-if="showPublishConfirm" class="inner-warning-overlay" @mousedown.self="showPublishConfirm = false">
          <div class="warning-card shadow-lg">
            <p class="fs-5 fw-bold">Ready to Publish?</p>
            <p>Your post will be visible to everyone</p>
            <div class="modal-actions justify-content-center">
              <button class="cancel-btn" @click="showPublishConfirm = false">Back</button>
              <button class="publish-btn" @click="doPublish">Confirm & Publish</button>
            </div>
          </div>
        </div>

        <!-- Discard Post Draft -->
        <div v-if="showWarningDialog" class="inner-warning-overlay" @mousedown.self="showWarningDialog = false">
          <div class="warning-card shadow-lg">
            <p class="fs-5 fw-bold">Unsaved Changes</p>
            <p>Are you sure you want to discard your draft? Your changes will be lost.</p>
            <div class="modal-actions justify-content-center">
              <button class="cancel-btn" @click="showWarningDialog = false">Back</button>
              <button class="publish-btn" @click="confirmDiscard">Confirm & Discard</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-mask {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-container {
  background: white;
  width: 90%;
  max-width: 55em;
  max-height: 90%;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

.modal-header {
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #004b33;
}

.modal-body {
  padding: 1.5em;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.5em;
}

.modal-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.inner-warning-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  z-index: 2;
  display: flex;
  align-items: center;
  backdrop-filter: blur(4px);
  justify-content: center;
}

.warning-card {
  position: relative;
  background: white;
  padding: 2rem;
  border-radius: 16px;
  width: 90%;
  max-width: 400px;
  text-align: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #ffffff;
  transform: translateY(0.35em);
}

.form-label-small {
  font-weight: 700;
  font-size: 0.7em;
  color: #64748b;
  text-transform: uppercase;
  margin-bottom: 8px;
  display: block;
}

.star-red {
  color: #ff4d4f;
  margin-left: 2px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 2em;
}

.title-input {
  width: 100%;
  border: none;
  border-bottom: 2px solid #e2e8f0;
  font-size: 2em;
  font-weight: 700;
  padding: 0.5rem 0;
  outline: none;
  color: #0f172a;
  transition: border-color 0.5s;
  background: transparent;
  z-index: 2;
}

.title-input:focus {
  border-bottom-color: #2E6C44;
}

.title-placeholder {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  font-size: 1.8rem;
  font-weight: 700;
  color: #94a3b8;
  pointer-events: none;
  z-index: 1;
}

.char-counter {
  position: absolute;
  right: 0;
  transform: translateY(5.75em);
  font-size: 0.75rem;
  color: #94a3b8;
  font-weight: 600;
}

.clean-select-rect {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.9em;
  outline: none;
  background: white;
  cursor: pointer;
}

.custom-editor {
  height: 300px;
}

.user-info-section {
  display: flex;
  align-items: center;
  gap: 1em;
  min-width: fit-content;
  padding: 8px 18px; 
  border-radius: 50px;
  background: #f8fafc;
  border: 1px solid #aebad4;
}

.user-meta {
  display: flex;
  flex-direction: column;
  align-items: center; 
  line-height: 1.2;
}

.user-name {
  font-weight: 700;
  font-size: 0.95rem;
  color: #1e293b;
  margin-bottom: 2px;
}

.avatar-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  overflow: hidden;
}

.avatar-img {
  width: 100%;
  height: 100%;
}

.controls-bar {
  display: grid;
  grid-template-columns: 1fr 1.5fr;
  gap: 1.5rem;
  background: #f8fafc;
  padding: 1.25rem;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.tag-adder-container {
  display: flex;
  align-items: center;
  gap: 12px;
}

.tag-trigger-group {
  position: relative;
}

.tag-circle-add {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: linear-gradient(170deg, #2e6c44bd 0%, #2e6c44 100%);
  color: white;
  font-size: 20px;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s;
}

.tag-circle-add:hover {
  transform: scale(1.1);
}

.tag-chips-flow {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.tag-chip-pill-mod-admin,
.tag-chip-pill {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #d1e7d8;
}
.tag-chip-pill-mod-admin {
  background: linear-gradient(170deg, #fa9805a4 0%, #f17500b0 100%);
  color: black;
  .chip-remove {
    color: black;
  }
}
.tag-chip-pill {
  background: linear-gradient(170deg, #2e6c44bd 0%, #2e6c44 100%);
  color: white;
}

.tag-floating-box {
  position: absolute;
  width: 13em;
  background: rgb(255, 255, 255);
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  margin-top: 6px;
  z-index: 1;
  padding: 8px;
}

.tag-search-mini {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 6px;
  font-size: 0.85em;
  outline: none;
  margin-bottom: 5px;
}

.tag-options-list {
  max-height: 150px;
  overflow-y: auto;
}

.tag-opt {
  width: 100%;
  text-align: left;
  background: none;
  border: none;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  cursor: pointer;
}

.tag-opt:hover { 
  background: #f1f5f9; 
  color: #2E6C44; 
}

.chip-remove { 
  background: none; 
  border: none; 
  color: white; 
  cursor: pointer; 
  transition: all 0.35s ease;
}
.chip-remove:hover {
  transform: translateY(-2px);
}

.muted-hint { 
  color: #94a3b8; 
  font-size: 0.8rem; 
  font-style: italic; 
}

.publish-btn, .cancel-btn, .discard-btn {
  padding: 0.75em 1.6em;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: inherit;
  font-size: 0.95rem;
  border: 2px solid transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  outline: none;
}

.publish-btn, .discard-btn {
  background: #2E6C44;
  color: white;
  border: none;
}

.publish-btn:disabled {
  background: #94a3b8;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.publish-btn:hover:not(:disabled), 
.discard-btn:hover {
  background: #3d8a59;
  box-shadow: 0 4px 12px rgba(46, 108, 68, 0.25);
  transform: translateY(-1px);
}

.cancel-btn {
  background: white;
  color: #475569;
  border: 2px solid #cbd5e1;
}

.cancel-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #94a3b8;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.close-x { 
  background: none; 
  border: none; 
  font-size: 1.75rem; 
  color: #ffffff; 
  cursor: pointer; 
}

.footer-actions,
.modal-actions { 
  display: flex; 
  gap: 12px; 
}

.modal-actions {
  gap: 20px; 
}

@media (max-width: 822px) {
  .user-info-section {
    display: none;
  }
}

@media (max-width: 653px) {
  .controls-bar {
    grid-template-columns: 1fr;
    gap: 1em;
  }
}

@media (max-width: 400px) {
  .modal-actions {
    flex-direction: column-reverse;
    gap: 2px;
  }
  .modal-actions .publish-btn,
  .modal-actions .cancel-btn,
  .modal-actions .discard-btn {
    width: 100%;
  }
}
</style>