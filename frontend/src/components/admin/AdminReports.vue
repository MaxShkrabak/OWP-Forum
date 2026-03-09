<!-- AdminReports.vue (FULLY UPDATED: Report Tags + Manage Reports via /api/admin/reports + Post Enrichment) -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";

import client from "@/api/client";
import { resolveReport } from "@/api/reports";

/* =========================
   REPORT TAGS (OLD CODE)
   ========================= */
const q = ref("");
const items = ref([]); // report tags
const loading = ref(false);
const error = ref("");

/** Info + confirm modal */
const modal = ref({ open: false, type: "info", title: "", message: "", onConfirm: null });
function showInfo(title, message) {
  modal.value = { open: true, type: "info", title, message, onConfirm: null };
}
function showConfirm(title, message, onConfirm) {
  modal.value = { open: true, type: "confirm", title, message, onConfirm };
}
function closeModal() {
  modal.value.open = false;
  modal.value.onConfirm = null;
}

/** Add/Edit modal */
const form = ref({ open: false, mode: "add", id: null, tagName: "" });
function openAdd() {
  form.value = { open: true, mode: "add", id: null, tagName: "" };
}
function openEdit(t) {
  form.value = {
    open: true,
    mode: "edit",
    id: Number(t.ReportTagID),
    tagName: String(t.TagName ?? ""),
  };
}
function closeForm() {
  form.value.open = false;
}

function normalizeName(s) {
  return String(s ?? "")
    .trim()
    .replace(/\s+/g, " ");
}
function isDuplicate(name, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return items.value.some((t) => {
    const same = normalizeName(t.TagName).toLowerCase() === n;
    const notSelf = excludeId == null ? true : Number(t.ReportTagID) !== Number(excludeId);
    return same && notSelf;
  });
}

/** Filter + alphabetical sort (case-insensitive) */
const filtered = computed(() => {
  const needle = q.value.trim().toLowerCase();
  const base = needle
    ? items.value.filter((t) => String(t.TagName ?? "").toLowerCase().includes(needle))
    : items.value;

  return [...base].sort((a, b) =>
    String(a.TagName ?? "").localeCompare(String(b.TagName ?? ""), undefined, { sensitivity: "base" })
  );
});

async function loadReportTags() {
  loading.value = true;
  error.value = "";
  try {
    // client baseURL already includes /api
    const res = await client.get("/admin/report-tags");
    const rows = res.data.items || [];
    items.value = rows.map((r) => ({
      ReportTagID: Number(r.ReportTagID),
      TagName: String(r.TagName ?? ""),
    }));
  } catch (e) {
    error.value = e?.response?.data?.error || e.message || "Failed to load report tags";
    items.value = [];
  } finally {
    loading.value = false;
  }
}

function requestSave() {
  const name = normalizeName(form.value.tagName);
  if (!name) return showInfo("Missing name", "Please enter a report tag name.");

  const excludeId = form.value.mode === "edit" ? form.value.id : null;
  if (isDuplicate(name, excludeId)) {
    return showInfo("Duplicate report tag", "That report tag already exists. Please choose a different name.");
  }

  if (form.value.mode === "add") {
    showConfirm("Confirm add report tag?", `Add report tag "${name}"?`, async () => {
      closeModal();
      try {
        await client.post("/admin/report-tags", { tagName: name });
        closeForm();
        await loadReportTags();
        showInfo("Report tag added", `"${name}" was added successfully.`);
      } catch (e) {
        showInfo("Failed to add", e?.response?.data?.error || e.message || "Server error");
      }
    });
    return;
  }

  const original = items.value.find((t) => Number(t.ReportTagID) === Number(form.value.id));
  const from = normalizeName(original?.TagName);

  showConfirm("Confirm edit report tag?", `Change "${from}" to "${name}"?`, async () => {
    closeModal();
    try {
      await client.patch(`/admin/report-tags/${form.value.id}`, { tagName: name });
      closeForm();
      await loadReportTags();
      showInfo("Report tag updated", `"${from}" was updated to "${name}".`);
    } catch (e) {
      showInfo("Failed to update", e?.response?.data?.error || e.message || "Server error");
    }
  });
}

function requestDelete(t) {
  const id = Number(t.ReportTagID);
  const name = normalizeName(t.TagName);

  showConfirm("Confirm delete report tag?", `Delete "${name}"? This cannot be undone.`, async () => {
    closeModal();
    try {
      await client.delete(`/admin/report-tags/${id}`);
      await loadReportTags();
      showInfo("Report tag deleted", `"${name}" was deleted successfully.`);
    } catch (e) {
      showInfo("Failed to delete", e?.response?.data?.error || e.message || "Server error");
    }
  });
}

/* =========================
   ACTIVE REPORTS (UPDATED)
   Uses NEW backend endpoint:
   GET /api/admin/reports
   ========================= */
const router = useRouter();

const reports = ref([]);
const loadingReports = ref(false);
const reportsError = ref("");

const sortMode = ref("newest"); // newest | oldest

// cache post lookups so we don’t spam the server
const postCache = new Map(); // postId -> { title, authorId, authorName } | null

async function fetchPostSafe(postId) {
  if (!postId) return null;
  if (postCache.has(postId)) return postCache.get(postId);

  try {
    // PostRoutes.php: GET /api/get-post/{id}
    const res = await client.get(`/get-post/${postId}`);
    const post = res?.data?.post;

    if (res?.data?.ok && post) {
      const mapped = {
        title: post.title ?? "",
        authorId: post.authorId ?? null,
        authorName: post.authorName ?? "",
      };
      postCache.set(postId, mapped);
      return mapped;
    }
  } catch (e) {
    console.error("Failed to fetch post:", e);
  }

  postCache.set(postId, null);
  return null;
}

/** Normalize report rows from /api/admin/reports */
function normalizeReport(r) {
  return {
    ...r,
    reportId: r.reportId ?? r.ReportID ?? r.ReportId,
    postId: r.postId ?? r.PostID ?? r.PostId,
    commentId: r.commentId ?? r.CommentID ?? r.CommentId,
    reason: r.reason ?? r.Reason ?? "",
    createdAt: r.createdAt ?? r.CreatedAt ?? "",
    source: r.source ?? r.Source ?? "Post",

    // New endpoint returns these already
    reporterId: r.reporterId ?? r.ReporterId ?? r.ReportUserID ?? null,
    reporterName: r.reporterName ?? r.ReporterName ?? "",

    contentTitle: r.contentTitle ?? r.ContentTitle ?? "",
    contentAuthorId: r.contentAuthorId ?? r.ContentAuthorId ?? null,
    contentAuthorName: r.contentAuthorName ?? r.ContentAuthorName ?? "",
  };
}

async function enrichReports(list) {
  const uniquePostIds = [...new Set(list.filter((r) => r?.postId).map((r) => r.postId))];
  await Promise.all(uniquePostIds.map((pid) => fetchPostSafe(pid)));

  return list.map((r) => {
    const post = r.postId ? postCache.get(r.postId) : null;
    return {
      ...r,
      contentTitle: r.contentTitle || post?.title || "",
      contentAuthorId: r.contentAuthorId ?? post?.authorId ?? null,
      contentAuthorName: r.contentAuthorName || post?.authorName || "",
    };
  });
}

async function loadReports() {
  loadingReports.value = true;
  reportsError.value = "";

  try {
    // ✅ NEW SAFE ADMIN ENDPOINT
    // client baseURL already includes /api, so this hits /api/admin/reports
    const res = await client.get("/admin/reports");
    const data = res?.data;

    if (data?.ok && Array.isArray(data.reports)) {
      const normalized = data.reports.map(normalizeReport);

      // keep existing post enrichment so UI still shows titles/authors
      const withPosts = await enrichReports(normalized);

      reports.value = withPosts;
    } else {
      reports.value = [];
    }
  } catch (e) {
    reportsError.value = e?.response?.data?.error || e?.message || "Failed to load reports";
    reports.value = [];
  } finally {
    loadingReports.value = false;
  }
}

function toTime(v) {
  const t = Date.parse(v);
  return Number.isFinite(t) ? t : 0;
}

const sortedReports = computed(() => {
  const copy = [...reports.value];
  copy.sort((a, b) => {
    const diff = toTime(a.createdAt) - toTime(b.createdAt);
    return sortMode.value === "oldest" ? diff : -diff;
  });
  return copy;
});

function goToContent(r) {
  if (r?.postId) router.push(`/posts/${r.postId}`);
}

async function handleResolve(reportId) {
  try {
    const data = await resolveReport(reportId);
    if (data?.ok) {
      reports.value = reports.value.filter((x) => x.reportId !== reportId);
    }
  } catch (e) {
    console.error("Resolve failed:", e);
  }
}

/* =========================
   MOUNT
   ========================= */
onMounted(() => {
  loadReportTags();
  loadReports();
});
</script>

<template>
  <div class="admin-roles-wrapper text-start">
    <!-- Title row -->
    <div class="header-row mb-4">
      <h2 class="page-title m-0">Manage Report Tags</h2>
      <div class="view-reports-top"></div>
    </div>

    <!-- =========================
         SECTION 1: REPORT TAGS
         ========================= -->
    <div class="admin-card">
      <div class="toolbar mb-4">
        <div class="search-wrapper">
          <i class="bi bi-search search-icon"></i>
          <input v-model="q" placeholder="Search report tags..." />
        </div>

        <button class="btn-add" @click="openAdd">
          <i class="bi bi-plus-lg"></i>
          <span>
            Add
            <span class="desktop-only">Report Tag</span>
          </span>
        </button>
      </div>

      <div v-if="loading" class="state mt-3 text-center">Loading…</div>
      <div v-if="error" class="err mt-3">{{ error }}</div>

      <div class="table-wrapper">
        <div class="table-wrapper">
        <table v-if="!loading && filtered.length" class="admin-table mt-3">
        <thead>
          <tr>
            <th style="width: 90px">#</th>
            <th>Tag</th>
            <th style="width: 220px">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(t, idx) in filtered" :key="t.ReportTagID">
            <td class="admin-id">{{ idx + 1 }}</td>
            <td class="admin-name">{{ t.TagName }}</td>
            <td>
              <div class="actions">
                <button class="btn-action" @click="openEdit(t)">
                  <i class="bi bi-pencil-square"></i> <span class="desktop-only">Edit</span>
                </button>
                <button class="btn-action danger" @click="requestDelete(t)">
                  <i class="bi bi-trash"></i> <span class="desktop-only">Delete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
          </table>
      </div>
      </div>

      <div v-if="!loading && filtered.length === 0" class="state mt-4 text-center">
        No report tags found.
      </div>
    </div>

    <!-- =========================
         SECTION 2: ACTIVE REPORTS
         ========================= -->
    <div class="reports-section">
      <div class="reports-header">
        <h3 class="section-title">Manage Reports</h3>

        <div class="reports-controls">
          <label class="sort-label">Sort</label>
          <select v-model="sortMode" class="sort-select">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
          </select>

          <button class="btn-refresh" type="button" @click="loadReports" :disabled="loadingReports">
            {{ loadingReports ? "Loading..." : "Refresh" }}
          </button>
        </div>
      </div>

      <div class="reports-card">
        <div v-if="reportsError" class="alert-error">
          {{ reportsError }}
        </div>

        <div v-else-if="loadingReports" class="loading-row">
          <div class="spinner-border"></div>
        </div>

        <div v-else-if="sortedReports.length === 0" class="empty-state">
          No active reports (unresolved).
        </div>

        <div v-else class="reports-list">
          <div v-for="r in sortedReports" :key="r.reportId" class="report-row">
            <div class="report-main">
              <div class="report-topline">
                <span class="badge-source" :class="r.source === 'Comment' ? 'comment' : 'post'">
                  {{ r.source }}
                </span>

                <span class="report-title">
                  {{ r.contentTitle || "(No title found)" }}
                </span>
              </div>

              <div class="report-meta">
                <div class="meta-item">
                  <span class="meta-label">Report ID:</span>
                  <span class="meta-val">{{ r.reportId }}</span>
                </div>

                <div class="meta-item">
                  <span class="meta-label">Reason:</span>
                  <span class="meta-val">{{ r.reason }}</span>
                </div>

                <div class="meta-item">
                  <span class="meta-label">Created:</span>
                  <span class="meta-val">{{ r.createdAt }}</span>
                </div>
              </div>

              <div class="report-meta">
                <div class="meta-item">
                  <span class="meta-label">Content ID:</span>
                  <span class="meta-val">
                    <template v-if="r.source === 'Post' || r.source === 'post'">
                      Post #{{ r.postId }}
                    </template>
                    <template v-else>
                      Comment #{{ r.commentId }} (Post #{{ r.postId }})
                    </template>
                  </span>
                </div>

                <div class="meta-item">
                  <span class="meta-label">Posted by:</span>
                  <span class="meta-val">
                    <template v-if="r.contentAuthorId">
                      #{{ r.contentAuthorId }}
                      <template v-if="r.contentAuthorName"> — {{ r.contentAuthorName }}</template>
                    </template>
                    <template v-else>Unknown</template>
                  </span>
                </div>

                <div class="meta-item">
                  <span class="meta-label">Reported by:</span>
                  <span class="meta-val">
                    <template v-if="r.reporterId">
                      #{{ r.reporterId }}
                      <template v-if="r.reporterName"> — {{ r.reporterName }}</template>
                      <template v-else> — (no name)</template>
                    </template>
                    <template v-else>Unknown</template>
                  </span>
                </div>
              </div>
            </div>

            <div class="report-actions">
              <button class="btn-outline" type="button" @click="goToContent(r)" :disabled="!r.postId">
                Go to
              </button>
              <button class="btn-solid" type="button" @click="handleResolve(r.reportId)">
                Resolve
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="form.open" class="inner-warning-overlay" @mousedown.self="closeForm">
      <div class="confirm-card">
        <h3 class="confirm-title">{{ form.mode === "add" ? "Add Report Tag" : "Edit Report Tag" }}</h3>
        <p class="confirm-subtitle">
          {{ form.mode === "add" ? "Create a new report tag." : "Update the report tag name." }}
        </p>

        <div class="form-field">
          <label class="field-label">Report tag name</label>
          <input
            class="field-input"
            v-model="form.tagName"
            placeholder="e.g. Spam"
            @keydown.enter.prevent="requestSave"
          />
        </div>

        <div class="confirm-actions">
          <button class="btn-back" @click="closeForm">Back</button>
          <button class="btn-confirm" @click="requestSave">
            {{ form.mode === "add" ? "Add" : "Save" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Info/Confirm Modal -->
    <div v-if="modal.open" class="inner-warning-overlay" @mousedown.self="closeModal">
      <div class="confirm-card">
        <h3 class="confirm-title">{{ modal.title }}</h3>
        <p class="confirm-subtitle">{{ modal.message }}</p>
        <div class="confirm-actions">
          <button v-if="modal.type === 'confirm'" class="btn-back" @click="closeModal">Back</button>
          <button class="btn-confirm" @click="modal.type === 'confirm' ? modal.onConfirm?.() : closeModal()">
            {{ modal.type === "confirm" ? "Confirm" : "OK" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Layout */
.admin-roles-wrapper {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #004750;
}

/* Title row */
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.view-reports-top {
  width: 220px;
  min-width: 220px;
}

/* Toolbar */
.toolbar {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 14px;
  flex-wrap: wrap;
}
.search-wrapper {
  position: relative;
  width: 100%;
  flex: 1;
  min-width: 100px;
}
.search-icon {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  font-size: 1.1rem;
  pointer-events: none;
}
.toolbar input {
  width: 100%;
  padding: 12px 20px 12px 48px;
  font-size: 15px;
  border-radius: 50px;
  border: 1px solid #a8c1bc;
  background: #ffffff;
  color: #1f2937;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  outline: none;
  transition: all 0.2s ease;
}
.toolbar input:focus {
  border-color: #004750;
  box-shadow: 0 4px 12px rgba(0, 71, 80, 0.15);
}

.btn-add {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #004750;
  border: none;
  color: #fff;
  padding: 10px 16px;
  border-radius: 14px;
  cursor: pointer;
  font-weight: 700;
  white-space: nowrap;
  box-shadow: 0 6px 16px rgba(0, 71, 80, 0.18);
}
.btn-add:hover {
  background: #00363d;
}

.err {
  color: #ff6b6b;
  font-weight: bold;
}

.admin-card {
  width: 100%;
  background: #ffffff;
  border-radius: 16px;
  padding: 12px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
}

.admin-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 6px;
}
.admin-table thead th {
  background: #f8fafc;
  color: #374151;
  font-size: 13px;
  padding: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 2px solid #e5e7eb;
}
.admin-table tbody tr {
  background: #fff;
  border-radius: 12px;
  transition: background 0.15s ease, box-shadow 0.15s ease;
}
.admin-table tbody td {
  padding: 12px 10px;
  vertical-align: middle;
}
.admin-table tbody tr:hover {
  background: #f5f9f8;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

.table-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.admin-id {
  color: #888;
  font-size: 0.85rem;
}
.admin-name {
  font-weight: 600;
  font-size: 1.1;
  color: #1f3d3a;
}

.actions {
  display: flex;
  gap: 10px;
  justify-content: flex-start;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border: 1px solid #cbd5e1;
  color: #374151;
  padding: 8px 12px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.9rem;
}
.btn-action:hover {
  background: #f1f5f9;
}
.btn-action.danger {
  border-color: #f3c6c6;
  color: #b91c1c;
}
.btn-action.danger:hover {
  background: #fff1f1;
}

/* Modals */
.inner-warning-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.confirm-card {
  background: #fff;
  color: #1f2937;
  width: min(520px, 92vw);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  text-align: center;
}
.confirm-title {
  margin: 0 0 12px;
  font-size: 22px;
  font-weight: 700;
  color: #1f2937;
}
.confirm-subtitle {
  margin: 0 0 18px;
  font-size: 15px;
  color: #4b5563;
  line-height: 1.5;
}
.confirm-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 18px;
}

.btn-back {
  background: #fff;
  border: 1px solid #cbd5e1;
  color: #374151;
  padding: 10px 20px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 700;
}
.btn-back:hover {
  background: #f1f5f9;
}

.btn-confirm {
  background: #004750;
  border: none;
  color: #fff;
  padding: 10px 24px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 800;
}
.btn-confirm:hover {
  background: #00363d;
}

.form-field {
  text-align: left;
  margin-top: 6px;
}
.field-label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 8px;
}
.field-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  outline: none;
  font-size: 15px;
}
.field-input:focus {
  border-color: #004750;
  box-shadow: 0 4px 12px rgba(0, 71, 80, 0.12);
}

/* Reports section */
.reports-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.reports-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.section-title {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
}
.reports-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sort-label {
  font-size: 12px;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
}
.sort-select {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 8px 10px;
  background: white;
  font-weight: 700;
}
.btn-refresh {
  border: none;
  border-radius: 10px;
  padding: 9px 14px;
  font-weight: 800;
  cursor: pointer;
  background: #004750;
  color: white;
}
.btn-refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.reports-card {
  background: white;
  border-radius: 16px;
  padding: 14px;
  border: 1px solid #e2e8f0;
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  color: #b91c1c;
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 12px;
  padding: 12px;
  font-weight: 700;
}

.loading-row {
  display: flex;
  justify-content: center;
  padding: 18px;
}

.empty-state {
  text-align: center;
  padding: 18px;
  color: #64748b;
  font-weight: 700;
}

.reports-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.report-row {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px;
  background: #f8fafc;
}

.report-main {
  flex: 1;
  min-width: 0;
}

.report-topline {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.badge-source {
  font-size: 12px;
  font-weight: 900;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(2, 6, 23, 0.12);
  background: white;
}
.badge-source.post {
  color: #065f46;
}
.badge-source.comment {
  color: #9a3412;
}

.report-title {
  font-weight: 900;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin-top: 6px;
}

.meta-item {
  display: flex;
  gap: 6px;
  align-items: baseline;
}
.meta-label {
  font-size: 12px;
  font-weight: 900;
  color: #64748b;
  text-transform: uppercase;
}
.meta-val {
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
}

.report-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 110px;
  justify-content: center;
}

.btn-outline,
.btn-solid {
  border-radius: 12px;
  padding: 10px 12px;
  font-weight: 900;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background: white;
}
.btn-outline:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-solid {
  background: #16a34a;
  border-color: #16a34a;
  color: white;
}
.desktop-only {
  display: inline;
}

/* Mobile */
@media (max-width: 576px) {
  .desktop-only {
    display: none;
  }
  .header-row,
  .reports-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .admin-name {
    font-size: 0.9rem;
  }

  .reports-controls {
    width: 100%;
    flex-wrap: nowrap;
  }

  .sort-select,
  .btn-refresh {
    width: 100%;
  }

  .report-row {
    flex-direction: column;
  }

  .report-actions {
    flex-direction: row;
    min-width: 0;
    width: 100%;
  }

  .btn-outline,
  .btn-solid {
    width: 100%;
  }

  .report-title {
    white-space: normal;
  }
}
</style>