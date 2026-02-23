<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { createPost, getTags, getCategories } from "@/api/posts";
import { fullName, userAvatar, isLoggedIn, userRole, userRoleId } from "@/stores/userStore";
import UserRole from "@/components/user/UserRole.vue";
import TextEditor from "@/components/forum/TextEditor.vue";
import client from "@/api/client";

const MAX_TITLE_LEN = 125;
const MAX_TAGS = 5;
const isUploading = ref(false);

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  postData: Object,
  isRestricted: Boolean,
});
const emit = defineEmits(["close", "published"]);

const router = useRouter();
const showPublishedConfirmation = ref(false);

// distinguish full edit vs create vs restricted metadata
const isEditMode = computed(() => !!props.postData && !props.isRestricted);
const isCreateMode = computed(() => !props.postData && !props.isRestricted);
const isMetadataMode = computed(() => !!props.isRestricted);

// Form state
const form = ref({
  title: "",
  category: "",
  content: "",
  tags: [],
  disableComments: false,
});

// Original state tracker for edits
const originalForm = ref({
  title: "",
  category: "",
  content: "",
  tags: [],
  disableComments: false,
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
    .filter((t) => !form.value.tags.includes(t.TagID || t.tagId))
    .filter((t) => {
      const n = t.Name || t.name || "";
      return q ? n.toLowerCase().includes(q) : true;
    })
    .slice(0, 20);
});

function tagNameById(id) {
  const found = allTags.value.find((t) => (t.TagID || t.tagId) == id);
  return found ? (found.Name || found.name) : `#${id}`;
}

function isOfficialTag(id) {
  const found = allTags.value.find((t) => (t.TagID || t.tagId) == id);
  return found && (found.Name || found.name) == "Official";
}

const removeTag = (id) => {
  form.value.tags = form.value.tags.filter((tid) => tid !== id);
};

function handleClickOutside(event) {
  if (tagContainerRef.value && !tagContainerRef.value.contains(event.target)) {
    showTagPopup.value = false;
  }
}

function populateForm() {
  if (props.postData) {
    // Extract everything safely accounting for both uppercase/lowercase variations
    const title = props.postData.Title || props.postData.title || "";
    const content = props.postData.Content || props.postData.content || "";
    const cat =
      props.postData.CategoryID ||
      props.postData.categoryId ||
      props.postData.category ||
      "";
    const dc = !!(props.postData.is_comments_disabled || props.postData.disableComments);

    const tgs =
      props.postData.tags && Array.isArray(props.postData.tags)
        ? props.postData.tags.map((t) => Number(t.TagID || t.tagId || t))
        : [];

    // Set current form values
    form.value.title = title;
    form.value.content = content;
    form.value.category = cat;
    form.value.tags = [...tgs];
    form.value.disableComments = dc;

    // Track the original values to prevent false warnings on close
    originalForm.value = {
      title,
      content,
      category: cat,
      tags: [...tgs],
      disableComments: dc,
    };
  }
}

// Validation
const hasUnsavedChanges = computed(() => {
  if (props.postData) {
    // Edit mode: only warn if they actually changed something from the original
    const titleChanged = form.value.title !== originalForm.value.title;
    const contentChanged = form.value.content !== originalForm.value.content;
    const categoryChanged = form.value.category !== originalForm.value.category;
    const commentsChanged = form.value.disableComments !== originalForm.value.disableComments;
    const tagsChanged =
      JSON.stringify([...form.value.tags].sort()) !==
      JSON.stringify([...originalForm.value.tags].sort());

    return titleChanged || contentChanged || categoryChanged || commentsChanged || tagsChanged;
  } else {
    // Create mode: warn if they typed anything into a blank slate
    const textContent = form.value.content.replace(/<[^>]*>/g, "").trim();
    return (
      form.value.title.trim().length > 0 ||
      textContent.length > 0 ||
      form.value.category !== "" ||
      form.value.tags.length > 0
    );
  }
});

const hasMetadataChanges = computed(() => {
  const categoryChanged = form.value.category !== originalForm.value.category;
  const commentsChanged = form.value.disableComments !== originalForm.value.disableComments;
  const tagsChanged =
    JSON.stringify([...form.value.tags].sort()) !==
    JSON.stringify([...originalForm.value.tags].sort());

  return categoryChanged || commentsChanged || tagsChanged;
});

const titleLength = computed(() => form.value.title.length);

const canPublish = computed(() => {
  let textContent = form.value.content.replace(/<[^>]*>/g, "");
  textContent = textContent.replace(/&nbsp;/g, " ").trim();
  const hasContent = textContent.length > 0 || form.value.content.includes("<img");

  return (
    form.value.title.trim().length > 0 &&
    form.value.title.trim().length <= MAX_TITLE_LEN &&
    form.value.category !== "" &&
    hasContent
  );
});

// Handle closing create post modal
function handleCloseRequest() {
  if (hasUnsavedChanges.value) {
    showWarningDialog.value = true;
  } else {
    emit("close");
  }
}

function confirmDiscard() {
  form.value = { title: "", category: "", content: "", tags: [] };
  showWarningDialog.value = false;
  emit("close");
}

const isInitialLoading = ref(true);

onMounted(async () => {
  // Wait for both essential lists to load before showing the form
  await Promise.all([loadTags(), loadCategories()]);
  
  document.addEventListener("mousedown", handleClickOutside);
  populateForm();
  
  // Now that data is here, turn off the loader
  isInitialLoading.value = false; 
});

onUnmounted(() => {
  document.removeEventListener("mousedown", handleClickOutside);
});

// Watch the prop to force an update if Vue passes the data a millisecond late
watch(
  () => props.postData,
  () => populateForm(),
  { immediate: true }
);

// Publish / Save
async function doPublish() {
  try {
    if (props.isRestricted) {
      const targetId = router.currentRoute.value.params.id;

      await client.patch(`/admin/posts/${targetId}/metadata`, {
        CategoryID: form.value.category,
        TagIDs: form.value.tags,
      });

    } else if (isEditMode.value) {
      const targetId = props.postData.PostID || props.postData.postId || props.postData.id;
      
      await client.put(`/posts/${targetId}`, {
        title: form.value.title.trim(),
        content: form.value.content,
        tags: form.value.tags,
        category: form.value.category || null,
      });

    } else {
      await createPost({
        title: form.value.title.trim(),
        content: form.value.content,
        tags: form.value.tags,
        category: form.value.category || null,
      });
    }

    showPublishedConfirmation.value = true;
    showPublishConfirm.value = false;

    setTimeout(() => {
      showPublishedConfirmation.value = false;
      form.value = { title: "", category: "", content: "", tags: [] };

      if (!props.isRestricted) {
        router.push("/");
      } else {
        location.reload();
      }

      emit("published");
      emit("close");
    }, 1200);
  } catch (err) {
    alert(props.isRestricted ? "Error updating metadata." : "An error occurred while publishing.");
    showPublishConfirm.value = false;
  }
}

//header/button for edit
const modalTitle = computed(() => {
  if (isMetadataMode.value) return "UPDATE POST";
  if (isEditMode.value) return "EDIT POST ";
  return "CREATE POST";
});

const primaryButtonText = computed(() => {
  if (props.loading) return "Processing...";
  if (isMetadataMode.value) return "Save Changes";
  if (isEditMode.value) return "Save Changes";
  return "Publish Post";
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal" appear>
      <div v-if="show" class="modal-mask" @mousedown.self="handleCloseRequest">
        <div class="modal-container">
          <header class="modal-header">
            <h3>{{ modalTitle }}</h3>
            <button class="close-x" @click="handleCloseRequest">&times;</button>
          </header>

          <main class="modal-body">
            <div v-if="isInitialLoading" class="text-center py-5">
              <div class="spinner-border text-success" role="status"></div>
              <p class="mt-2 text-muted">Loading post data...</p>
            </div>

            <template v-else>
              <div class="title-row">
                <div class="input-group flex-grow-1 position-relative">
                  <label v-if="!form.title" class="title-placeholder">
                    Title<span class="star-red">*</span>
                  </label>

                  <input
                    v-model="form.title"
                    class="title-input"
                    :class="{ 'restricted-input': isRestricted }"
                    :maxlength="MAX_TITLE_LEN"
                    :disabled="isRestricted"
                  />
                  <span class="char-counter" :class="{ 'text-danger': titleLength >= MAX_TITLE_LEN }">
                    {{ titleLength }}/{{ MAX_TITLE_LEN }}
                  </span>
                </div>

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
                <div class="category-side">
                  <label class="form-label-small">
                    Category<span v-if="!form.category" class="star-red">*</span>
                  </label>

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
                    <div class="tag-trigger-group">
                      <button
                        type="button"
                        class="tag-circle-add"
                        @click="showTagPopup = !showTagPopup"
                        :disabled="form.tags.length >= MAX_TAGS"
                      >
                        +
                      </button>

                      <div v-if="showTagPopup" class="tag-floating-box shadow-lg">
                        <input
                          v-model="tagSearch"
                          class="tag-search-mini"
                          placeholder="Search..."
                          @click.stop
                        />
                        <div class="tag-options-list">
                          <button
                            v-for="t in filteredTags"
                            :key="t.TagID || t.tagId"
                            class="tag-opt"
                            @click="
                              () => {
                                form.tags.push(t.TagID || t.tagId);
                                tagSearch = '';
                                showTagPopup = false;
                              }
                            "
                          >
                            {{ t.Name || t.name }}
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="tag-chips-flow">
                      <span
                        v-for="tid in form.tags"
                        :key="tid"
                        :class="isOfficialTag(tid) ? 'tag-chip-pill-mod-admin' : 'tag-chip-pill'"
                      >
                        {{ tagNameById(tid) }}
                        <button class="chip-remove" @click="removeTag(tid)">&times;</button>
                      </span>
                      <span v-if="form.tags.length === 0" class="muted-hint">No tags added yet</span>
                    </div>
                  </div>
                </div>

                <div class="comment-ctrl comm-checkbox-style" v-if="userRoleId >= 3">
                  <span class="me-3">Disable Comments?</span>
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="checkComment"
                    v-model="form.disableComments"
                  />
                  <label class="form-check-label" for="checkComment"></label>
                </div>
              </div>

              <div :class="{ 'restricted-input': isRestricted }">
                <TextEditor
                  v-model="form.content"
                  v-model:isUploading="isUploading"
                  class="custom-editor"
                  ref="editor"
                />
              </div>
            </template>
          </main>

          <footer class="modal-footer">
            <div class="footer-hint"></div>
            <div class="footer-actions">
              <button class="cancel-btn" @click="handleCloseRequest">Cancel</button>
              <button
                class="publish-btn"
                :disabled="(isRestricted ? (!form.category || !hasMetadataChanges) : !canPublish) || loading"
                @click="showPublishConfirm = true"
              >
                {{ primaryButtonText }}
              </button>
            </div>
          </footer>

          <div v-if="showPublishedConfirmation" class="inner-warning-overlay">
            <div class="warning-card shadow-lg">
              <p class="fs-5 fw-bold">
                {{ isMetadataMode ? "Changes Saved" : isEditMode ? "Changes Saved" : "Post Published" }}
              </p>
              <p>
                {{ isMetadataMode ? "Refreshing details..." : "Redirecting to home..." }}
              </p>
            </div>
          </div>

          <div v-if="isUploading" class="inner-warning-overlay">
            <div class="warning-card shadow-lg upload-card">
              <div class="spinner"></div>
              <p class="fs-5 fw-bold" style="margin-top: 12px;">Uploading image…</p>
              <p>Please wait.</p>
            </div>
          </div>

          <div
            v-if="showPublishConfirm"
            class="inner-warning-overlay"
            @mousedown.self="showPublishConfirm = false"
          >
            <div class="warning-card shadow-lg">
              <p class="fs-5 fw-bold">
                {{ isMetadataMode ? "Save Changes?" : isEditMode ? "Save Changes?" : "Ready to Publish?" }}
              </p>

              <p>
                {{
                  isMetadataMode
                    ? "This will update the post metadata immediately."
                    : isEditMode
                      ? "This will save your edits immediately."
                      : "Your post will be visible to everyone."
                }}
              </p>

              <div class="modal-actions justify-content-center">
                <button class="cancel-btn" @click="showPublishConfirm = false">Back</button>
                <button class="publish-btn" @click="doPublish">
                  {{ isMetadataMode ? "Confirm & Save" : isEditMode ? "Confirm & Save" : "Confirm & Publish" }}
                </button>
              </div>
            </div>
          </div>

          <div
            v-if="showWarningDialog"
            class="inner-warning-overlay"
            @mousedown.self="showWarningDialog = false"
          >
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
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
p {
  color: #737373;
}
.comm-checkbox-style input[type="checkbox"]:focus {
  box-shadow: 0 0 0 0.15rem rgba(6, 233, 157, 0.25);
}
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

.upload-card {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.spinner {
  width: 44px;
  height: 44px;
  border: 4px solid #e2e8f0;
  border-top: 4px solid #2E6C44;
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
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
  padding: 4px 5px 4px 10px;
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
  transform: translateY(-1px);
}

.muted-hint {
  color: #94a3b8;
  font-size: 0.8rem;
  font-style: italic;
}

.publish-btn,
.cancel-btn,
.discard-btn {
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

.publish-btn,
.discard-btn {
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

.restricted-input {
  opacity: 0.6;
  pointer-events: none; /* Prevents clicking/typing completely */
  background-color: #f1f5f9;
  border-radius: 8px;
}
</style>