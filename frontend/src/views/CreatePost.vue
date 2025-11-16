<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter, onBeforeRouteLeave } from "vue-router";
import Editor from "primevue/editor";
import { createPost, uploadImage } from "@/api/auth";
const API = import.meta.env.VITE_API_BASE || "http://localhost:8080";

const MAX = 120;
const router = useRouter();

// Form fields
const title = ref("");
const category = ref("");
const content = ref("");
const tags = ref([]);

// Helper function to check if HTML content is empty (ignores formatting tags)
function isEmptyHtml(html) {
  if (!html || html.trim() === '') return true;
  // Remove HTML tags and whitespace, check if anything remains
  // PrimeVue Editor often returns <p><br></p> or <p></p> when empty
  const text = html
    .replace(/<[^>]*>/g, '') // Remove all HTML tags
    .replace(/&nbsp;/g, ' ') // Replace &nbsp; with space
    .replace(/\s+/g, ' ') // Normalize whitespace
    .trim();
  return text.length === 0;
}

// Unsaved changes tracking
const hasUnsavedChanges = computed(() => {
  const hasTitle = title.value.trim().length > 0;
  const hasContent = !isEmptyHtml(content.value);
  const hasCategory = category.value.trim().length > 0;
  const hasTags = tags.value && tags.value.length > 0;
  
  return hasTitle || hasContent || hasCategory || hasTags;
});

// Warning dialog state
const showWarningDialog = ref(false);
const pendingNavigation = ref(null);

// User state
const currentUser = ref(null);
const loadingUser = ref(false);
const userError = ref(null);

// Simulated user load
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

// Validation
const len = computed(() => title.value.trim().length);
const valid = computed(() => len.value > 0 && len.value <= MAX);
const canPublish = computed(
  () => !!currentUser.value && valid.value && content.value.trim().length > 0
);

// Utility
function initials(name = "") {
  const p = name.trim().split(/\s+/);
  return p.slice(0, 2).map(s => s[0]?.toUpperCase() || "").join("");
}

// Actions
function onCancel() {
  title.value = "";
  category.value = "";
  content.value = "";
  tags.value = [];
  // Also clear any pending navigation since we're intentionally clearing
  showWarningDialog.value = false;
  pendingNavigation.value = null;
}

// Handle browser beforeunload event (closing tab/window)
function handleBeforeUnload(e) {
  if (hasUnsavedChanges.value) {
    e.preventDefault();
    e.returnValue = ""; // Chrome requires returnValue to be set
    return "";
  }
}

// Confirm leaving
function confirmLeave() {
  if (pendingNavigation.value) {
    pendingNavigation.value();
    pendingNavigation.value = null;
  }
  showWarningDialog.value = false;
  // Clear form
  onCancel();
}

// Cancel leaving
function cancelLeave() {
  showWarningDialog.value = false;
  pendingNavigation.value = null;
}

// Navigation guard - warn before leaving with unsaved changes
onBeforeRouteLeave((to, from, next) => {
  // Re-check unsaved changes when navigation is attempted
  const hasChanges = hasUnsavedChanges.value;
  
  if (!hasChanges) {
    next();
    return;
  }
  
  // Prevent navigation and show warning dialog
  showWarningDialog.value = true;
  pendingNavigation.value = next;
  // Don't call next() here - we'll call it after user confirms
});

// Initialize beforeunload listener and load user
onMounted(() => {
  loadMe();
  window.addEventListener("beforeunload", handleBeforeUnload);
});

onBeforeUnmount(() => {
  window.removeEventListener("beforeunload", handleBeforeUnload);
});

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


// Publish action
async function onPublish() {
  if (!canPublish.value) return;
  try {
    await createPost({
      title: title.value.trim(),
      category: category.value || null,
      tags: tags.value,
      content: content.value,
    });
    onCancel();
    alert("Post published!");
    // Navigate away after successful publish
    router.push("/");
  } catch (err) {
    console.error(err);
  }
}
</script>

<template>
  <section class="frame">
    <!-- Warning Dialog -->
    <div v-if="showWarningDialog" class="warning-overlay" @click.self="cancelLeave">
      <div class="warning-dialog">
        <div class="warning-header">
          <span class="warning-icon">⚠️</span>
          <h3>Unsaved Changes</h3>
        </div>
        <p class="warning-message">
          You have unsaved changes. Are you sure you want to leave? Your changes will be lost.
        </p>
        <div class="warning-actions">
          <button class="btn ghost" @click="cancelLeave">Stay on Page</button>
          <button class="btn danger" @click="confirmLeave">Leave Without Saving</button>
        </div>
      </div>
    </div>

    <div class="post-card">
      <!-- Unsaved changes indicator -->
      <div v-if="hasUnsavedChanges" class="unsaved-indicator">
        <span class="star-icon">★</span>
        <span class="unsaved-text">Unsaved changes</span>
      </div>
      
      <div class="title-group" :class="{ bad: !valid && len > 0 }">
        <div class="title-field">
          <input
            id="post-title"
            v-model.trim="title"
            type="text"
            :maxlength="MAX + 20"
            :aria-invalid="!valid && len > 0"
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
              <div class="role pill">{{ currentUser.role ?? 'Student' }}</div>
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
          <button class="btn ghost" @click="onCancel">Cancel</button>
          <button
            class="btn primary"
            :disabled="loadingUser || !canPublish"
            @click="onPublish"
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
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
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
  box-shadow: inset 0 1px 2px rgba(0,0,0,.06);
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
  justify-content: center;
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
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #2f3a46;
  cursor: pointer;
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
  opacity: .55;
  cursor: not-allowed; 
}
.btn.ghost { color: #111; }
.btn.danger {
  background: #e11d48;
  color: #fff;
  border-color: #be123c;
}

/* Unsaved changes indicator */
.unsaved-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 8px;
  margin-bottom: 12px;
  font-size: 0.9rem;
  color: #92400e;
}
.star-icon {
  color: #f59e0b;
  font-size: 1.1rem;
}
.unsaved-text {
  font-weight: 600;
}

/* Warning Dialog */
.warning-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.warning-dialog {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  max-width: 400px;
  width: 90%;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}
.warning-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.warning-icon {
  font-size: 1.5rem;
}
.warning-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #111827;
}
.warning-message {
  margin: 0 0 20px 0;
  color: #4b5563;
  line-height: 1.5;
}
.warning-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}
</style>