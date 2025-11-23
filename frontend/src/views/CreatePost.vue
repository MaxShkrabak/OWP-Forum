<script setup>
import { ref, computed, onMounted } from "vue";
import { onBeforeUnmount, nextTick } from "vue";
import Editor from "primevue/editor";
import { createPost, uploadImage } from "@/api/auth";
const API = import.meta.env.VITE_API_BASE || "http://localhost:8080";
const MAX = 120;

// Form fields
const title = ref("");
const category = ref("");
const content = ref("");
const tags = ref([]);

// User state
const currentUser = ref(null);
const loadingUser = ref(false);
const userError = ref(null);

const showDiscardConfirm = ref(false);
const showPublishConfirm = ref(false);
const showError = ref(false);
const errorMessage = ref("");

async function loadMe() {
  loadingUser.value = true;
  userError.value = null;
  try {
    const res = await fetch(`${API}/api/me`, {
      credentials: "include",
    });
    if (!res.ok) throw new Error(`Failed /api/me: ${res.status}`);
    currentUser.value = await res.json();
  } catch (e) {
    userError.value = e.message || "Unable to load user";
    currentUser.value = null;
  } finally {
    loadingUser.value = false;
  }
}
onMounted(loadMe);

// Validation
const len = computed(() => title.value.trim().length);
const validTitle = computed(() => len.value > 0 && len.value <= MAX);
const hasContent = computed(() => content.value.trim().length > 0);
const canPublish = computed(() => validTitle.value && hasContent.value);

// Utility
function initials(name = "") {
  const p = name.trim().split(/\s+/);
  return p
    .slice(0, 2)
    .map((s) => s[0]?.toUpperCase() || "")
    .join("");
}

// Resets the form
function onCancel() {
  title.value = "";
  category.value = "";
  content.value = "";
  tags.value = [];
}

// Attach custom image upload to the editor's built-in image button
function onEditorLoad(quill) {
  const toolbar = quill.getModule("toolbar");

  toolbar.addHandler("image", () => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";

    input.onchange = async () => {
      const file = input.files?.[0];
      if (!file) return;

      try {
        const data = await uploadImage(file);
        console.log("uploadImage response:", data);

        if (!data || data.ok === false || !data.url) {
          alert(data?.error || "Image upload failed");
          return;
        }

        const imgUrl = data.url.startsWith("http")
          ? data.url
          : `${API}${data.url}`;

        const range = quill.getSelection(true);
        quill.insertEmbed(range.index, "image", imgUrl, "user");
        quill.setSelection(range.index + 1);
      } catch (err) {
        console.error("Upload handler error:", err);
        alert("Image upload failed");
      }
    };

    input.click();
  });
}

// Button handlers
function handleCancelClick() {
  const dirty =
    !!title.value ||
    !!category.value ||
    !!content.value ||
    (tags.value && tags.value.length > 0);

  if (dirty) {
    showDiscardConfirm.value = true;
  } else {
    onCancel();
  }
}

function handlePublishClick() {
  if (!canPublish.value) {
    errorMessage.value =
      "Please enter a title and some content before publishing your post.";
    showError.value = true;
    return;
  }
  showPublishConfirm.value = true;
}

// Popup actions
function closeError() {
  showError.value = false;
}

function confirmDiscard() {
  onCancel();
  showDiscardConfirm.value = false;
}

function cancelDiscard() {
  showDiscardConfirm.value = false;
}

function cancelPublishConfirm() {
  showPublishConfirm.value = false;
}

async function doPublish() {
  if (!canPublish.value) return;

  const payload = {
    title: title.value.trim(),
    content: content.value,
    tags: tags.value,
    category: category.value || null,
  };

  try {
    await createPost(payload);
    onCancel();
    // TODO: Change this later
    alert("Post published!"); // Confirm post was published
  } catch (err) {
    const serverMsg = "An error occurred while trying to publish the post.";
    errorMessage.value = typeof serverMsg === "string" ? serverMsg : JSON.stringify(serverMsg);
    showError.value = true;
  } finally {
    showPublishConfirm.value = false;
  }
}

// popup/tag state
const MAX_TAGS = 5;
const showTagPopup = ref(false);      
const allTags = ref([]);          
const tagSearch = ref("");   
const loadingTags = ref(false);        
const tagError = ref(null);            
const tagAnchorEl = ref(null);         
const popupStyle = ref({});            

// lookups/filters
function tagNameById(id) {            
  return allTags.value.find(t => t.tagId === id)?.name || `#${id}`;
}
const filteredTags = computed(() => {  
  const q = tagSearch.value.trim().toLowerCase();
  const selected = new Set(tags.value);
  return allTags.value
    .filter(t => !selected.has(t.tagId))
    .filter(t => (q ? t.name.toLowerCase().includes(q) : true))
    .slice(0, 40);
});

// fetch tags from backend
async function loadTags() {            
  loadingTags.value = true;
  tagError.value = null;
  try {
    const res = await fetch(`${API}/api/tags`, { credentials: "include" });
    if (!res.ok) throw new Error(`Failed /api/tags: ${res.status}`);
    const json = await res.json();
    allTags.value = (json.items || []).map(r => ({
      tagId: Number(r.TagID ?? r.tagId ?? r.id),
      name: r.Name ?? r.name
    }));
  } catch (e) {
    tagError.value = e.message || "Failed to load tags";
  } finally {
    loadingTags.value = false;
  }
}

// popup open/close/position
function positionPopup() {             
  const el = tagAnchorEl.value;
  if (!el) return;

  const parent = el.closest(".title-group") || el.closest(".post-card") || document.body;
  const btnRect = el.getBoundingClientRect();
  const parRect = parent.getBoundingClientRect();
  const isBody = parent === document.body;

  popupStyle.value = {
    position: isBody ? "fixed" : "absolute",
    top:  `${btnRect.bottom - parRect.top + (isBody ? 0 : 8)}px`,
    left: `${btnRect.left   - parRect.left}px`,
    zIndex: 60
  };
}
async function openTagPopup() {       
  showTagPopup.value = true;
  await nextTick();
  positionPopup();
}
function closeTagPopup() {             
  showTagPopup.value = false;
  tagSearch.value = "";
}
function onGlobalClick(ev) {           
  const popup = document.querySelector(".tag-popup");
  if (!popup || !showTagPopup.value) return;
  if (!popup.contains(ev.target) && !tagAnchorEl.value?.contains(ev.target)) {
    closeTagPopup();
  }
}
function onResize() {                 
  if (showTagPopup.value) positionPopup();
}
function onScrollReposition() {        
  if (showTagPopup.value) positionPopup();
}

// select/remove tags
function pickTag(id) {                 
  if (tags.value.length >= MAX_TAGS) return;
  if (!tags.value.includes(id)) tags.value = [...tags.value, id];
}
function removeTag(id) {               
  tags.value = tags.value.filter(t => t !== id);
}

// mount wiring
onMounted(() => {                     
  loadTags();

  const btn = document.querySelector(".control.tags .tag-add");
  if (btn) {
    tagAnchorEl.value = btn;
    btn.addEventListener("click", openTagPopup);
  }
  document.addEventListener("click", onGlobalClick);
  window.addEventListener("resize", onResize);
  window.addEventListener("scroll", onScrollReposition, true);
});
onBeforeUnmount(() => {                
  const btn = tagAnchorEl.value;
  if (btn) btn.removeEventListener("click", openTagPopup);
  document.removeEventListener("click", onGlobalClick);
  window.removeEventListener("resize", onResize);
  window.removeEventListener("scroll", onScrollReposition, true);
});

</script>

<template>
  <section class="frame">
    <div class="post-card">
      <div
        v-if="showDiscardConfirm || showPublishConfirm || showError"
        class="modal-backdrop"
      ></div>

      <!-- Discard Confirmation -->
      <div v-if="showDiscardConfirm" class="modal-shell">
        <div class="modal-card">
          <p class="modal-text">Are you sure you want to discard?</p>
          <div class="modal-actions">
            <button class="btn small primary" type="button" @click="confirmDiscard">
              Yes
            </button>
            <button class="btn small danger" type="button" @click="cancelDiscard">
              No
            </button>
          </div>
        </div>
      </div>

      <!-- Publish Confirmation -->
      <div v-if="showPublishConfirm" class="modal-shell">
        <div class="modal-card">
          <p class="modal-text">Are you sure you want to Publish?</p>
          <div class="modal-actions">
            <button class="btn small primary" type="button" @click="doPublish">
              Yes
            </button>
            <button class="btn small danger" type="button" @click="cancelPublishConfirm">
              No
            </button>
          </div>
        </div>
      </div>

      <!-- Error popup -->
      <div v-if="showError" class="modal-shell">
        <div class="modal-card">
          <p class="modal-text">{{ errorMessage }}</p>
          <div class="modal-actions">
            <button class="btn small primary" type="button" @click="closeError">
              OK
            </button>
          </div>
        </div>
      </div>

      <div class="title-group" :class="{ bad: !validTitle && len > 0 }">
        <div class="title-field">
          <input
            id="post-title"
            v-model.trim="title"
            type="text"
            :maxlength="MAX + 20"
            :aria-invalid="!validTitle && len > 0"
            placeholder=" "
          />
          <label class="inline-label" for="post-title">
            Title<span class="req">*</span>
          </label>
        </div>

        <div class="user-box">
          <template v-if="loadingUser">
            <div class="avatar">…</div>
            <div class="meta">
              <div class="role pill">—</div>
              <div class="name">Loading…</div>
            </div>
          </template>
          <template v-else-if="currentUser">
            <div class="avatar">{{ initials(currentUser.name) }}</div>
            <div class="meta">
              <div class="role pill">{{ currentUser.role ?? "Student" }}</div>
              <div class="name">{{ currentUser.name }}</div>
            </div>
          </template>
          <template v-else>
            <div class="avatar">?</div>
            <div class="meta">
              <div class="role pill guest">Guest</div>
              <div class="name">Not signed in</div>
            </div>
          </template>
        </div>

        <div class="controls-row">
          <div class="control">
            <span class="label">Category:</span>
            <select v-model="category" class="select-compact">
              <option value="">Select</option>
              <option>Announcements & News</option>
              <option>Training Courses</option>
              <option>Research Projects</option>
              <option>Help</option>
            </select>
          </div>

          <div class="control tags">
            <div class="top-row">
              <span class="label">Tags:</span>
              <button type="button" class="tag-add" title="Add tag">+</button>
            </div>
            <span class="tag-hint">1 – 5</span>
          </div>
        </div>
      </div>
      
      <!-- selected tags chips -->
      <div class="row">
        <div class="tag-chips" v-if="tags.length">
          <span v-for="tid in tags" :key="tid" class="tag-chip">
            {{ tagNameById(tid) }}
            <button class="chip-x" type="button" @click="removeTag(tid)" aria-label="Remove tag">×</button>
          </span>
        </div>
        <div class="tag-chips empty" v-else>
          <em>No tags selected</em>
        </div>
      </div>

      <div v-if="showTagPopup" class="tag-popup" :style="popupStyle" @click.stop>
        <div class="tag-popup-inner">
          <div class="tag-popup-head">
            <strong>Select tags</strong>
            <span class="muted">({{ tags.length }}/{{ MAX_TAGS }})</span>
          </div>

          <input
            class="tag-search"
            v-model.trim="tagSearch"
            type="text"
            placeholder="Search tags…"
            :disabled="loadingTags"
          />

          <div class="tag-list">
            <template v-if="tagError"><div class="tag-error">{{ tagError }}</div></template>
            <template v-else-if="loadingTags"><div class="tag-loading">Loading…</div></template>
            <template v-else>
              <button
                v-for="t in filteredTags"
                :key="t.tagId"
                class="tag-item"
                type="button"
                :disabled="tags.length >= MAX_TAGS"
                @click="pickTag(t.tagId)"
              >{{ t.name }}</button>
              <div v-if="!filteredTags.length" class="tag-empty">No results</div>
            </template>
          </div>

          <div class="tag-popup-actions">
            <button class="btn ghost sm" type="button" @click="closeTagPopup">Done</button>
          </div>
        </div>
      </div>

      <!-- Editor -->
      <div class="editor-fixed">
        <Editor
          v-model="content"
          :showHeader="true"
          :editorStyle="{ height: '320px' }"
          @load="onEditorLoad"
        />
      </div>

      <!-- Actions -->
      <div class="row">
        <div class="actions">
          <button class="btn ghost" type="button" @click="handleCancelClick">
            Cancel
          </button>
          <button
            class="btn primary"
            type="button"
            :disabled="loadingUser"
            @click="handlePublishClick"
          >
            Publish
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Page */
.frame {
  background: #ffffff;
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 40px 20px;
}
.post-card {
  width: 700px;
  background: #f3f6f5;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: visible;
  position: relative;
}
.row { width: 100%; }

/* Title Box */
.title-group {
  width: 100%;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-rows: auto auto;
  overflow: visible;
  position: relative;
}
.title-group.bad { border-color: #e11d48; }

.title-field {
  position: relative;
  padding: 10px 14px 12px;
}
.title-field input {
  width: 100%;
  border: 0;
  outline: none;
  font-size: 1rem;
  color: #111827;
  background: transparent;
}
.title-field::after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 12px;
  right: 12px;
  height: 2px;
  background-color: #9ca3af;
  border-radius: 1px;
}
.inline-label {
  position: absolute;
  top: 10px;
  left: 16px;
  color: #9aa1a0;
  font-size: 1rem;
  pointer-events: none;
  transition: opacity .15s ease, transform .15s ease;
}
.inline-label .req {
  color: #e11d48;
  margin-left: 2px;
}
.title-field input:not(:placeholder-shown) ~ .inline-label {
  opacity: 0; transform: translateY(-6px);
}

/* User */
.user-box {
  grid-column: 2 / 3;
  grid-row: 1 / 3;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
}
.user-box .avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #e5e7eb;
  color: #374151;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
}
.user-box .meta {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.user-box .pill {
  font-size: .75rem;
  padding: 2px 8px;
  border-radius: 999px;
  background: #0c7a43;
  color: #fff;
  font-weight: 700;
  margin-bottom: 4px;
}
.user-box .pill.guest { background: #007c8a; }
.user-box .name {
  font-size: .95rem;
  color: #0f172a;
}

.controls-row {
  grid-column: 1 / 2;
  grid-row: 2 / 3;
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 8px 14px 12px;
}
.control {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.label {
  font-weight: 700;
  color: #0f172a;
  font-size: 0.95rem;
}
.select-compact {
  height: 28px;
  padding: 0 24px 0 10px;
  border: 1px solid #8a96a3;
  border-radius: 4px;
  background: #fff;
  font-size: 0.9rem;
  cursor: pointer;
}
.control.tags {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
.control.tags .top-row {
  display: flex;
  align-items: center;
  gap: 6px;
}
.tag-add {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1px solid #b8bec6;
  background: #efefef;
  font-weight: 700;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tag-add:hover { background: #e7e7e7; }
.tag-hint {
  margin-top: -8px;
  margin-left: 2px;
  font-size: 10px;
  color: #6b7280;
}

/* Editor sizing */
.editor-fixed {
  width: 100%;
  box-sizing: border-box;
}

:deep(.editor-fixed .ql-container) {
  height: 320px;
}
:deep(.editor-fixed .ql-editor) {
  min-height: 280px;
  overflow-y: auto;
}

:deep(.editor-fixed .ql-toolbar.ql-snow) {
  border-bottom: 1px solid #d1d5db;
  border-radius: 10px 10px 0 0;
}
:deep(.editor-fixed .ql-container.ql-snow) {
  border-radius: 0 0 10px 10px;
}

/* Actions */
.actions {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
.btn {
  border-radius: 10px;
  padding: 6px 16px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background: #fff;
}
.btn.primary {
  background: #1b5e20;
  color: #fff;
  border-color: #14532d;
}
.btn.primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn.ghost { color: #111; }

/* chips under controls-row */
.tag-chips {
  display: flex; flex-wrap: wrap; gap: 6px; padding: 6px 2px 0;
}
.tag-chips.empty { color: #6b7280; font-size: 0.9rem; }

.tag-chip {
  display: inline-flex; align-items: center; gap: 6px;
  border: 1px solid #b8bec6; background: #fff;
  padding: 4px 8px; border-radius: 999px; font-size: 0.85rem; color: #0f172a;
}
.tag-chip .chip-x {
  border: 0; background: transparent; cursor: pointer; font-size: 14px; line-height: 1; color: #334155;
}

/* popup */
.tag-popup {
  position: absolute; width: 320px; background: #fff; border: 1px solid #cbd5e1;
  border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.15);
}
.tag-popup-inner { padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.tag-popup-head { display: flex; justify-content: space-between; align-items: center; }
.tag-popup-head .muted { color: #6b7280; font-size: 0.85rem; }

.tag-search {
  width: 100%; border: 1px solid #b8bec6; border-radius: 8px; padding: 6px 10px; font-size: 0.95rem;
}

.tag-list {
  max-height: 240px; overflow: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px; display: grid; gap: 6px;
}
.tag-item {
  text-align: left; padding: 6px 8px; border-radius: 6px; border: 1px solid #cbd5e1; background: #f9fafb;
  cursor: pointer; font-size: 0.95rem;
}
.tag-item:hover { background: #f3f4f6; }
.tag-item:disabled { opacity: .5; cursor: not-allowed; }

.tag-empty, .tag-error, .tag-loading { padding: 8px; color: #6b7280; font-size: 0.9rem; }
.tag-popup-actions { display: flex; justify-content: flex-end; }
.btn.sm { padding: 4px 10px; border-radius: 8px; }

.btn.danger {
  background: #fecaca;
  color: #7f1d1d;
  border-color: #fda4af;
}

.btn.small {
  padding: 4px 14px;
  font-size: 0.85rem;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  z-index: 40;
}

.modal-shell {
  position: fixed;
  inset: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 50;
}

.modal-card {
  min-width: 260px;
  max-width: 320px;
  background: #ffffff;
  border-radius: 12px;
  padding: 16px 18px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

.modal-text {
  font-size: 0.95rem;
  color: #111827;
  margin-bottom: 16px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>