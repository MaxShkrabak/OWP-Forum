<script setup>
import { ref, computed, onMounted } from "vue";
import Editor from "primevue/editor";

const API = (import.meta.env.VITE_API_URL ?? "") + "/api";
const MAX = 120;
const MAX_TAGS = 5;

// Form fields
const title = ref("");
const categoryId = ref(0);
const content = ref("");
const tagIds = ref([]);

// User state
const currentUser = ref(null);
const loadingUser = ref(false);
const userError = ref(null);

//Backend Options
const category = ref([]);
const tags = ref([]);
const loading = ref(false);
const loadError = ref("");

// Simulated user load (API not yet connected)
async function loadMe() {
  loadingUser.value = true;
  userError.value = null;
  try {
    const res = await fetch(`${API}/me`, {
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

//load categories and tags from backend
async function loadOptions() {
  loading.value = true;
  loadError.value = "";
  try {
    const [cRes, tRes]= await Promise.all([
      fetch(`${API}/categories`, { credentials: "include" }),
      fetch(`${API}/tags`, { credentials: "include" }),
    ]);

    const cJson = await cRes.json();
    const tJson = await tRes.json();
    
    if (!cRes.ok || !cJson.ok) throw new Error(cJson.error || `Failed /api/categories: ${cRes.status}`);
    if (!tRes.ok || !tJson.ok) throw new Error(tJson.error || `Failed /api/tags: ${tRes.status}`)

    category.value = cJson.items ?? [];
    tags.value = tJson.items ?? [];

  } catch (e) {
    loadError.value = e.message || "Unable to load post options";
    category.value = [];
    tags.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadMe();
  loadOptions();
});

// Validation
const len = computed(() => title.value.trim().length);
const validTitle = computed(() => len.value > 0 && len.value <= MAX);
const validContent = computed(() => content.value.trim().length > 0);
const validCategory = computed(() => Number.isInteger(categoryId.value) && categoryId.value > 0);
const validTags = computed(() => Array.isArray(tagIds.value) && tagIds.value.length <= MAX_TAGS);

const canPublish = computed(
  () => !!currentUser.value && validTitle.value && validContent.value && validCategory.value && validTags.value
);

// Utility
function initials(name = "") {
  const p = name.trim().split(/\s+/);
  return p.slice(0, 2).map(s => s[0]?.toUpperCase() || "").join("");
}

// Actions
function onCancel() {
  title.value = "";
  categoryId.value = 0;
  content.value = "";
  tagIds.value = [];
}
async function onPublish() {
  if (!canPublish.value) return;
  const body = {
    title: title.value.trim(),
    content: content.value,
    categoryId: categoryId.value,
    tagIds: tagIds.value
  };
  const res = await fetch(`${API}/posts`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(body),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.ok) {
   alert(json.error || `Failed to create post: ${res.status}`);
   return;
  }
  onCancel();
  alert("Post published successfully!");
}
</script>

<template>
  <section class="frame">
    <div class="post-card">
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
            <select v-model.number="categoryId" class="select-compact" required :disabled="loading">
              <option :value="0" disabled>Select</option>
              <option v-for="cat in category" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div class="control tags">
            <div class="top-row">
              <span class="label">Tags:</span>
              <span class="tag-hint">{{ tagIds.length }} / {{ MAX_TAGS }}</span>
            </div>

            <select
              v-model="tagIds"
              multiple
              size="5"
              class="select-compact"
              :disabled="loading"
              @change="tagIds = Array.isArray(tagIds) ? tagIds.slice(0, MAX_TAGS) : []"
            >
              <option v-for="tag in tags" :key="tag.id" :value="tag.id">
                {{ tag.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Editor -->
      <Editor v-model="content" editorClass="editor" :showHeader="true" />

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
.inline-label .req
{
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
.user-box .avatar 
{
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
.user-box .meta
{
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.user-box .pill
{
  font-size: .75rem;
  padding: 2px 8px;
  border-radius: 999px;
  background: #0c7a43;
  color: #fff;
  font-weight: 700;
  margin-bottom: 4px;
}
.user-box .pill.guest { background: #007c8a; }
.user-box .name
{
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
.control
{ 
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.label
{
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
.control.tags
{
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
.control.tags .top-row
{
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
.tag-hint
{
  margin-top: -8px;
  margin-left: 2px;
  font-size: 10px;
  color: #6b7280; 
}

.p-editor
{
  overflow: visible;
  position: relative; 
}
.p-editor .p-editor-toolbar { border-radius: 10px 10px 0 0; }
.p-editor .p-editor-content { border-radius: 0 0 10px 10px; }

:deep(.p-editor .ql-editor) {
  min-height: 320px;
  overflow-y: auto;
}
/* Toolbar */
:deep(.p-editor .ql-toolbar.ql-snow) {
  display: flex !important;
  flex-wrap: nowrap !important;
  align-items: center;
  justify-content: flex-start;
  gap: 4px;
  white-space: nowrap;
  overflow: visible !important;
  padding: 6px 8px;
  border-bottom: 1px solid #d1d5db;
  box-sizing: border-box;
}

:deep(.p-editor .ql-formats) {
  display: flex;
  align-items: center;
  gap: 2px;
  flex: 0 0 auto;
}

:deep(.p-editor .ql-formats button) {
  width: 24px;
  height: 24px;
  padding: 1px 2px;
}

:deep(.p-editor .ql-picker) { flex: 0 0 auto; }
:deep(.p-editor .ql-picker.ql-header),
:deep(.p-editor .ql-picker.ql-font) { width: 90px; }
:deep(.p-editor .ql-picker-options) { z-index: 9999; }

.actions { 
  width: 100%;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
.btn 
{ 
  border-radius: 10px;
  padding: 6px 16px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background: #fff; 
}
.btn.primary 
{
  background: #1b5e20;
  color: #fff;
  border-color: #14532d; 
}
.btn.primary:disabled
{
  opacity: .55;
  cursor: not-allowed; 
}
.btn.ghost { color: #111; }
</style>