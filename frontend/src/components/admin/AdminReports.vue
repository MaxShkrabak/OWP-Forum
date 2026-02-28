<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";

// Reports API (already exists in your project)
import { fetchReports, resolveReport } from "@/api/reports";

// Use your existing API client (same approach as AdminTags/AdminTagsRoutes)
import client from "@/api/client";

/* =========================================================
   SECTION A — Manage Report Tags (top panel)
   Uses endpoints you already added:
   GET    /admin/report-tags
   POST   /admin/report-tags
   PATCH  /admin/report-tags/:id
   DELETE /admin/report-tags/:id
========================================================= */

const tagSearch = ref("");
const reportTags = ref([]);
const tagLoading = ref(false);
const tagError = ref("");

// modal-ish state (simple inline editor)
const showAdd = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);

const addName = ref("");
const editId = ref(null);
const editName = ref("");

const deleteId = ref(null);
const deleteName = ref("");

const toast = ref({ show: false, type: "success", text: "" });
let toastTimer = null;

function showToast(type, text) {
  toast.value = { show: true, type, text };
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toast.value.show = false), 1800);
}

function normalizeName(s) {
  return String(s ?? "").trim().replace(/\s+/g, " ");
}
function isDuplicateReportTagName(name, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return reportTags.value.some((t) => {
    const same = normalizeName(t.TagName).toLowerCase() === n;
    const notSelf =
      excludeId == null ? true : Number(t.ReportTagID) !== Number(excludeId);
    return same && notSelf;
  });
}

async function loadReportTags() {
  tagLoading.value = true;
  tagError.value = "";
  try {
    const { data } = await client.get("/admin/report-tags");
    if (data?.ok && Array.isArray(data.items)) {
      reportTags.value = data.items;
    } else {
      reportTags.value = [];
    }
  } catch (e) {
    tagError.value = e?.message || "Failed to load report tags";
    reportTags.value = [];
  } finally {
    tagLoading.value = false;
  }
}

const filteredReportTags = computed(() => {
  const q = tagSearch.value.trim().toLowerCase();
  const list = [...reportTags.value];

  // Always alphabetical (requested earlier)
  list.sort((a, b) => String(a.TagName).localeCompare(String(b.TagName)));

  if (!q) return list;

  return list.filter((t) => String(t.TagName ?? "").toLowerCase().includes(q));
});

// Display # column (not DB ID)
function rowNumber(index) {
  return index + 1;
}

function openAdd() {
  addName.value = "";
  showAdd.value = true;
}
async function submitAdd() {
  const name = normalizeName(addName.value);
  if (!name) return showToast("error", "Tag name is required");
  if (isDuplicateReportTagName(name)) return showToast("error", "Duplicate tag name");

  try {
    const { data } = await client.post("/admin/report-tags", { tagName: name });
    if (data?.ok) {
      showAdd.value = false;
      await loadReportTags();
      showToast("success", "Report tag added");
    }
  } catch (e) {
    showToast("error", e?.response?.data?.error || e?.message || "Add failed");
  }
}

function openEdit(t) {
  editId.value = t.ReportTagID;
  editName.value = t.TagName;
  showEdit.value = true;
}
async function submitEdit() {
  const id = editId.value;
  const name = normalizeName(editName.value);
  if (!id) return;
  if (!name) return showToast("error", "Tag name is required");
  if (isDuplicateReportTagName(name, id)) return showToast("error", "Duplicate tag name");

  try {
    const { data } = await client.patch(`/admin/report-tags/${id}`, { tagName: name });
    if (data?.ok) {
      showEdit.value = false;
      await loadReportTags();
      showToast("success", "Report tag updated");
    }
  } catch (e) {
    showToast("error", e?.response?.data?.error || e?.message || "Update failed");
  }
}

function openDelete(t) {
  deleteId.value = t.ReportTagID;
  deleteName.value = t.TagName;
  showDelete.value = true;
}
async function submitDelete() {
  const id = deleteId.value;
  if (!id) return;

  try {
    const { data } = await client.delete(`/admin/report-tags/${id}`);
    if (data?.ok) {
      showDelete.value = false;
      await loadReportTags();
      showToast("success", "Report tag deleted");
    }
  } catch (e) {
    showToast("error", e?.response?.data?.error || e?.message || "Delete failed");
  }
}

/* =========================================================
   SECTION B — Manage Reports (bottom panel)
   Uses existing endpoints:
   GET   /api/reports
   PATCH /api/reports/:id/resolve
========================================================= */

const router = useRouter();

const reports = ref([]);
const reportsLoading = ref(false);
const reportsError = ref("");

const sortMode = ref("newest"); // newest | oldest

async function loadReports() {
  reportsLoading.value = true;
  reportsError.value = "";
  try {
    const data = await fetchReports();
    if (data?.ok && Array.isArray(data.reports)) {
      // should already be unresolved only; still safe to guard:
      reports.value = data.reports;
    } else {
      reports.value = [];
    }
  } catch (e) {
    reportsError.value = e?.message || "Failed to load reports";
    reports.value = [];
  } finally {
    reportsLoading.value = false;
  }
}

function toTime(v) {
  const t = Date.parse(v);
  return Number.isFinite(t) ? t : 0;
}

const sortedReports = computed(() => {
  const list = [...reports.value];
  list.sort((a, b) => {
    const diff = toTime(a.createdAt) - toTime(b.createdAt);
    return sortMode.value === "oldest" ? diff : -diff;
  });
  return list;
});

// UI helpers (handle both "old API" and "new enriched API" gracefully)
function reportContentLabel(r) {
  // Expecting your enriched /api/reports shape:
  // r.source, r.postId, r.commentId, r.contentTitle, r.contentAuthorName, r.contentAuthorId
  if (r?.source === "Comment") {
    return `Comment${r.commentId ? " #" + r.commentId : ""}`;
  }
  return `Post${r.postId ? " #" + r.postId : ""}`;
}
function reportTitle(r) {
  return r?.contentTitle || r?.title || "(No title)";
}
function authorLine(r) {
  const name = r?.contentAuthorName || r?.authorName || "Unknown";
  const id = r?.contentAuthorId || r?.authorId || null;
  return `${name}${id ? ` (#${id})` : ""}`;
}
function reporterLine(r) {
  const name = r?.reporterName || "Unknown";
  const id = r?.reporterId || null;
  return `${name}${id ? ` (#${id})` : ""}`;
}

function goToReportTarget(r) {
  // Only route confirmed in your app: /posts/:id
  if (r?.postId) {
    router.push(`/posts/${r.postId}`);
  }
}

async function resolve(r) {
  try {
    const data = await resolveReport(r.reportId);
    if (data?.ok) {
      reports.value = reports.value.filter((x) => x.reportId !== r.reportId);
      showToast("success", "Report resolved");
    }
  } catch (e) {
    showToast("error", e?.response?.data?.error || e?.message || "Resolve failed");
  }
}

onMounted(async () => {
  await loadReportTags();
  await loadReports();
});
</script>

<template>
  <div class="admin-reports">

    <!-- Toast -->
    <div v-if="toast.show" class="toast-float" :class="toast.type">
      {{ toast.text }}
    </div>

    <!-- =======================
         TOP: Manage Report Tags
    ======================== -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">Manage Report Tags</h2>
      </div>

      <div class="panel-body">
        <div class="toolbar">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input
              v-model="tagSearch"
              class="search-input"
              placeholder="Search report tags..."
            />
          </div>

          <button class="btn-primary" type="button" @click="openAdd">
            <i class="bi bi-plus-lg"></i>
            Add Report Tag
          </button>
        </div>

        <div v-if="tagError" class="alert error">{{ tagError }}</div>
        <div v-else-if="tagLoading" class="loading">
          <div class="spinner-border"></div>
        </div>

        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th class="col-num">#</th>
                <th>Tag</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>

            <tbody v-if="filteredReportTags.length === 0">
              <tr>
                <td colspan="3" class="empty">No report tags found.</td>
              </tr>
            </tbody>

            <tbody v-else>
              <tr v-for="(t, idx) in filteredReportTags" :key="t.ReportTagID">
                <td class="muted">{{ rowNumber(idx) }}</td>
                <td class="tag-cell">{{ t.TagName }}</td>
                <td class="actions">
                  <button class="btn-action" @click="openEdit(t)">
                    <i class="bi bi-pencil-square"></i>
                    Edit
                  </button>
                  <button class="btn-action danger" @click="openDelete(t)">
                    <i class="bi bi-trash3"></i>
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Add -->
        <div v-if="showAdd" class="dialog-backdrop" @mousedown.self="showAdd=false">
          <div class="dialog">
            <div class="dialog-title">Add report tag</div>
            <input v-model="addName" class="dialog-input" placeholder="Tag name" />
            <div class="dialog-actions">
              <button class="btn-secondary" @click="showAdd=false">Cancel</button>
              <button class="btn-primary" @click="submitAdd">Add</button>
            </div>
          </div>
        </div>

        <!-- Edit -->
        <div v-if="showEdit" class="dialog-backdrop" @mousedown.self="showEdit=false">
          <div class="dialog">
            <div class="dialog-title">Edit report tag</div>
            <input v-model="editName" class="dialog-input" placeholder="Tag name" />
            <div class="dialog-actions">
              <button class="btn-secondary" @click="showEdit=false">Cancel</button>
              <button class="btn-primary" @click="submitEdit">Save</button>
            </div>
          </div>
        </div>

        <!-- Delete -->
        <div v-if="showDelete" class="dialog-backdrop" @mousedown.self="showDelete=false">
          <div class="dialog">
            <div class="dialog-title">Confirm delete report tag?</div>
            <div class="dialog-text">
              Delete <b>{{ deleteName }}</b>? This cannot be undone.
            </div>
            <div class="dialog-actions">
              <button class="btn-secondary" @click="showDelete=false">Cancel</button>
              <button class="btn-danger" @click="submitDelete">Delete</button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- =======================
         BOTTOM: Manage Reports
    ======================== -->
    <div class="panel">
      <div class="panel-header reports-header">
        <h2 class="panel-title">Manage Reports</h2>

        <div class="reports-controls">
          <label class="sort-label">Sort</label>
          <select v-model="sortMode" class="sort-select">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
          </select>

          <button class="btn-secondary" type="button" @click="loadReports" :disabled="reportsLoading">
            {{ reportsLoading ? "Loading..." : "Refresh" }}
          </button>
        </div>
      </div>

      <div class="panel-body">
        <div v-if="reportsError" class="alert error">{{ reportsError }}</div>
        <div v-else-if="reportsLoading" class="loading">
          <div class="spinner-border"></div>
        </div>

        <div v-else-if="sortedReports.length === 0" class="empty big">
          No unresolved reports.
        </div>

        <div v-else class="reports-list">
          <div v-for="r in sortedReports" :key="r.reportId" class="report-card">
            <div class="report-left">
              <div class="report-top">
                <div class="report-kind">{{ reportContentLabel(r) }}</div>
                <div class="report-author">
                  by <b>{{ authorLine(r) }}</b>
                </div>
              </div>

              <div class="report-title">
                {{ reportTitle(r) }}
              </div>

              <div class="report-bottom">
                <div class="reporter">
                  Reported by: <b>{{ reporterLine(r) }}</b>
                  <span class="reason">for: <b>{{ r.reason || r.Reason || "Other" }}</b></span>
                </div>
                <div class="meta muted">
                  Report #{{ r.reportId }} • {{ r.createdAt }}
                </div>
              </div>
            </div>

            <div class="report-right">
              <button class="btn-outline" type="button" @click="goToReportTarget(r)" :disabled="!r.postId">
                Go To
              </button>
              <button class="btn-success" type="button" @click="resolve(r)">
                Resolve
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.admin-reports {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Panels */
.panel {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.panel-header {
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.panel-title {
  margin: 0;
  font-size: 26px;
  font-weight: 900;
  color: #004750;
}

.panel-body {
  padding: 14px 16px;
}

/* Toolbar */
.toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.search-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  padding: 10px 14px;
  background: white;
}

.search-input {
  border: none;
  outline: none;
  width: 100%;
  font-weight: 700;
}

/* Buttons */
.btn-primary,
.btn-secondary,
.btn-danger,
.btn-outline,
.btn-success {
  border-radius: 12px;
  padding: 10px 14px;
  font-weight: 900;
  cursor: pointer;
  border: 1px solid transparent;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: #004750;
  color: white;
}
.btn-secondary {
  background: white;
  border-color: #cbd5e1;
  color: #0f172a;
}
.btn-danger {
  background: #ef4444;
  color: white;
}
.btn-outline {
  background: white;
  border-color: #cbd5e1;
  color: #0f172a;
}
.btn-success {
  background: #16a34a;
  color: white;
}

.btn-primary:disabled,
.btn-secondary:disabled,
.btn-outline:disabled,
.btn-success:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Table */
.table-wrap {
  overflow-x: auto;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
}

.admin-table th,
.admin-table td {
  padding: 12px 10px;
  border-bottom: 1px solid #e2e8f0;
}

.col-num {
  width: 70px;
}
.col-actions {
  width: 220px;
}

.tag-cell {
  font-weight: 900;
  color: #0f172a;
}

.actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-action {
  border-radius: 12px;
  padding: 9px 12px;
  font-weight: 900;
  background: white;
  border: 1px solid #cbd5e1;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn-action.danger {
  border-color: rgba(239, 68, 68, 0.35);
  color: #b91c1c;
}

.empty {
  text-align: center;
  color: #64748b;
  font-weight: 800;
  padding: 18px 10px;
}
.empty.big {
  padding: 20px 10px;
}

/* Dialog */
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 16px;
}
.dialog {
  width: 100%;
  max-width: 440px;
  background: white;
  border-radius: 16px;
  padding: 18px;
  border: 1px solid #e2e8f0;
}
.dialog-title {
  font-weight: 1000;
  font-size: 18px;
  margin-bottom: 10px;
}
.dialog-text {
  color: #64748b;
  font-weight: 700;
  margin-bottom: 12px;
}
.dialog-input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 10px 12px;
  font-weight: 800;
  outline: none;
  margin-bottom: 12px;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Alerts & loading */
.alert {
  padding: 12px;
  border-radius: 12px;
  font-weight: 800;
  margin-top: 8px;
}
.alert.error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.25);
  color: #b91c1c;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 18px;
}

.muted {
  color: #64748b;
}

/* Reports section */
.reports-header {
  flex-wrap: wrap;
}

.reports-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sort-label {
  font-size: 12px;
  font-weight: 1000;
  color: #64748b;
  text-transform: uppercase;
}
.sort-select {
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 9px 10px;
  font-weight: 900;
}

.reports-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.report-card {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #f8fafc;
}

.report-left {
  flex: 1;
  min-width: 0;
}

.report-top {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: baseline;
}

.report-kind {
  font-weight: 1000;
  color: #0f172a;
}

.report-author {
  color: #64748b;
  font-weight: 800;
}

.report-title {
  font-size: 18px;
  font-weight: 1000;
  color: #0f172a;
  margin: 6px 0 10px;
  word-break: break-word;
}

.report-bottom {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.reporter {
  font-weight: 800;
  color: #0f172a;
}
.reason {
  margin-left: 10px;
  color: #0f172a;
}

.meta {
  font-size: 12px;
  font-weight: 800;
}

.report-right {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 110px;
  justify-content: center;
}

/* Toast */
.toast-float {
  position: fixed;
  right: 16px;
  bottom: 16px;
  z-index: 100;
  padding: 12px 14px;
  border-radius: 12px;
  font-weight: 900;
  border: 1px solid #e2e8f0;
  background: white;
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.12);
}
.toast-float.success {
  border-color: rgba(22, 163, 74, 0.35);
}
.toast-float.error {
  border-color: rgba(239, 68, 68, 0.35);
}

/* ======================
   Mobile responsiveness
====================== */
@media (max-width: 768px) {
  .panel-title {
    font-size: 22px;
  }

  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .reports-controls {
    width: 100%;
    flex-wrap: wrap;
  }
  .sort-select,
  .reports-controls .btn-secondary {
    width: 100%;
  }

  .report-card {
    flex-direction: column;
  }

  .report-right {
    flex-direction: row;
    min-width: 0;
    width: 100%;
  }

  .btn-outline,
  .btn-success {
    width: 100%;
    justify-content: center;
  }

  .actions {
    justify-content: flex-start;
    flex-wrap: wrap;
  }
}
</style>