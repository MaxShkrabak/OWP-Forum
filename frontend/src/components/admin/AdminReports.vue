<!-- AdminReports.vue (updated: View Reports button in top-right of title row) -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'
import ViewReportsButton from '@/components/admin/ViewReportsButton.vue' // adjust path if needed

const q = ref('')
const items = ref([]) // report tags
const loading = ref(false)
const error = ref('')

/** Info + confirm modal */
const modal = ref({ open: false, type: 'info', title: '', message: '', onConfirm: null })
function showInfo(title, message) {
  modal.value = { open: true, type: 'info', title, message, onConfirm: null }
}
function showConfirm(title, message, onConfirm) {
  modal.value = { open: true, type: 'confirm', title, message, onConfirm }
}
function closeModal() {
  modal.value.open = false
  modal.value.onConfirm = null
}

/** Add/Edit modal */
const form = ref({ open: false, mode: 'add', id: null, tagName: '' })
function openAdd() {
  form.value = { open: true, mode: 'add', id: null, tagName: '' }
}
function openEdit(t) {
  form.value = { open: true, mode: 'edit', id: Number(t.ReportTagID), tagName: String(t.TagName ?? '') }
}
function closeForm() { form.value.open = false }

function normalizeName(s) {
  return String(s ?? '').trim().replace(/\s+/g, ' ')
}
function isDuplicate(name, excludeId = null) {
  const n = normalizeName(name).toLowerCase()
  if (!n) return false
  return items.value.some(t => {
    const same = normalizeName(t.TagName).toLowerCase() === n
    const notSelf = excludeId == null ? true : Number(t.ReportTagID) !== Number(excludeId)
    return same && notSelf
  })
}

/** Filter + alphabetical sort (case-insensitive) */
const filtered = computed(() => {
  const needle = q.value.trim().toLowerCase()
  const base = needle
    ? items.value.filter(t => String(t.TagName ?? '').toLowerCase().includes(needle))
    : items.value

  return [...base].sort((a, b) =>
    String(a.TagName ?? '').localeCompare(String(b.TagName ?? ''), undefined, { sensitivity: 'base' })
  )
})

async function loadReportTags() {
  loading.value = true
  error.value = ''
  try {
    // client baseURL already includes /api
    const res = await client.get('/admin/report-tags')
    const rows = res.data.items || []
    items.value = rows.map(r => ({
      ReportTagID: Number(r.ReportTagID),
      TagName: String(r.TagName ?? '')
    }))
  } catch (e) {
    error.value = e?.response?.data?.error || e.message || 'Failed to load report tags'
    items.value = []
  } finally {
    loading.value = false
  }
}

function requestSave() {
  const name = normalizeName(form.value.tagName)
  if (!name) return showInfo('Missing name', 'Please enter a report tag name.')

  const excludeId = form.value.mode === 'edit' ? form.value.id : null
  if (isDuplicate(name, excludeId)) {
    return showInfo('Duplicate report tag', 'That report tag already exists. Please choose a different name.')
  }

  if (form.value.mode === 'add') {
    showConfirm('Confirm add report tag?', `Add report tag "${name}"?`, async () => {
      closeModal()
      try {
        await client.post('/admin/report-tags', { tagName: name })
        closeForm()
        await loadReportTags()
        showInfo('Report tag added', `"${name}" was added successfully.`)
      } catch (e) {
        showInfo('Failed to add', e?.response?.data?.error || e.message || 'Server error')
      }
    })
    return
  }

  const original = items.value.find(t => Number(t.ReportTagID) === Number(form.value.id))
  const from = normalizeName(original?.TagName)

  showConfirm('Confirm edit report tag?', `Change "${from}" to "${name}"?`, async () => {
    closeModal()
    try {
      await client.patch(`/admin/report-tags/${form.value.id}`, { tagName: name })
      closeForm()
      await loadReportTags()
      showInfo('Report tag updated', `"${from}" was updated to "${name}".`)
    } catch (e) {
      showInfo('Failed to update', e?.response?.data?.error || e.message || 'Server error')
    }
  })
}

function requestDelete(t) {
  const id = Number(t.ReportTagID)
  const name = normalizeName(t.TagName)

  showConfirm('Confirm delete report tag?', `Delete "${name}"? This cannot be undone.`, async () => {
    closeModal()
    try {
      await client.delete(`/admin/report-tags/${id}`)
      await loadReportTags()
      showInfo('Report tag deleted', `"${name}" was deleted successfully.`)
    } catch (e) {
      showInfo('Failed to delete', e?.response?.data?.error || e.message || 'Server error')
    }
  })
}

onMounted(loadReportTags)
</script>

<template>
  <div class="admin-roles-wrapper text-start">
    <!-- ✅ Title row: button pinned top-right at same height -->
    <div class="header-row mb-4">
      <h2 class="page-title m-0">Manage Report Tags</h2>
      <div class="view-reports-top">
        <ViewReportsButton />
      </div>
    </div>

    <div class="admin-card">
      <div class="toolbar mb-4">
        <div class="search-wrapper">
          <i class="bi bi-search search-icon"></i>
          <input v-model="q" placeholder="Search report tags..." />
        </div>

        <button class="btn-add" @click="openAdd">
          <i class="bi bi-plus-lg"></i>
          Add Report Tag
        </button>
      </div>

      <div v-if="loading" class="state mt-3 text-center">Loading…</div>
      <div v-if="error" class="err mt-3">{{ error }}</div>

      <table v-if="!loading && filtered.length" class="admin-table mt-3">
        <thead>
          <tr>
            <th style="width: 90px;">#</th>
            <th>Tag</th>
            <th style="width: 220px;">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(t, idx) in filtered" :key="t.ReportTagID">
            <td class="admin-id">{{ idx + 1 }}</td>
            <td class="admin-name">{{ t.TagName }}</td>
            <td>
              <div class="actions">
                <button class="btn-action" @click="openEdit(t)">
                  <i class="bi bi-pencil-square"></i> Edit
                </button>
                <button class="btn-action danger" @click="requestDelete(t)">
                  <i class="bi bi-trash"></i> Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!loading && filtered.length === 0" class="state mt-4 text-center">
        No report tags found.
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="form.open" class="inner-warning-overlay" @mousedown.self="closeForm">
      <div class="confirm-card">
        <h3 class="confirm-title">{{ form.mode === 'add' ? 'Add Report Tag' : 'Edit Report Tag' }}</h3>
        <p class="confirm-subtitle">{{ form.mode === 'add' ? 'Create a new report tag.' : 'Update the report tag name.' }}</p>

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
          <button class="btn-confirm" @click="requestSave">{{ form.mode === 'add' ? 'Add' : 'Save' }}</button>
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
            {{ modal.type === 'confirm' ? 'Confirm' : 'OK' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-title { font-size: 24px; font-weight: 700; color: #004750; }

/* ✅ new: title + button row */
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
.toolbar { display: flex; align-items: center; width: 100%; gap: 14px; flex-wrap: wrap; }
.search-wrapper { position: relative; width: 100%; flex: 1; min-width: 240px; }
.search-icon {
  position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
  color: #6b7280; font-size: 1.1rem; pointer-events: none;
}
.toolbar input {
  width: 100%;
  padding: 12px 20px 12px 48px;
  font-size: 15px;
  border-radius: 50px;
  border: 1px solid #a8c1bc;
  background: #ffffff;
  color: #1f2937;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
  outline: none;
  transition: all 0.2s ease;
}
.toolbar input:focus { border-color: #004750; box-shadow: 0 4px 12px rgba(0,71,80,0.15); }

.btn-add {
  display: inline-flex; align-items: center; gap: 8px;
  background: #004750; border: none; color: #fff;
  padding: 10px 16px; border-radius: 14px; cursor: pointer; font-weight: 700;
  white-space: nowrap; box-shadow: 0 6px 16px rgba(0,71,80,0.18);
}
.btn-add:hover { background: #00363d; }

.err { color: #ff6b6b; font-weight: bold; }

.admin-card {
  width: 100%;
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.05);
}

.admin-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
.admin-table thead th {
  background: #f8fafc;
  color: #374151;
  font-size: 13px;
  padding: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 2px solid #e5e7eb;
}
.admin-table tbody tr { background: #fff; border-radius: 12px; transition: background 0.15s ease, box-shadow 0.15s ease; }
.admin-table tbody td { padding: 12px 10px; vertical-align: middle; }
.admin-table tbody tr:hover { background: #f5f9f8; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

.admin-id { color: #888; font-size: 0.85rem; }
.admin-name { font-weight: 600; color: #1f3d3a; }

.actions { display: flex; gap: 10px; justify-content: flex-start; }
.btn-action {
  display: inline-flex; align-items: center; gap: 8px;
  background: #fff; border: 1px solid #cbd5e1; color: #374151;
  padding: 8px 12px; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 0.9rem;
}
.btn-action:hover { background: #f1f5f9; }
.btn-action.danger { border-color: #f3c6c6; color: #b91c1c; }
.btn-action.danger:hover { background: #fff1f1; }

.inner-warning-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.confirm-card {
  background: #fff; color: #1f2937; width: min(520px, 92vw);
  border-radius: 20px; padding: 30px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.25);
  text-align: center;
}
.confirm-title { margin: 0 0 12px; font-size: 22px; font-weight: 700; color: #1f2937; }
.confirm-subtitle { margin: 0 0 18px; font-size: 15px; color: #4b5563; line-height: 1.5; }
.confirm-actions { display: flex; justify-content: center; gap: 16px; margin-top: 18px; }

.btn-back {
  background: #fff; border: 1px solid #cbd5e1; color: #374151;
  padding: 10px 20px; border-radius: 12px; cursor: pointer; font-weight: 700;
}
.btn-back:hover { background: #f1f5f9; }

.btn-confirm {
  background: #004750; border: none; color: #fff;
  padding: 10px 24px; border-radius: 12px; cursor: pointer; font-weight: 800;
}
.btn-confirm:hover { background: #00363d; }

.form-field { text-align: left; margin-top: 6px; }
.field-label { display: block; font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px; }
.field-input {
  width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 14px;
  outline: none; font-size: 15px;
}
.field-input:focus { border-color: #004750; box-shadow: 0 4px 12px rgba(0,71,80,0.12); }

/* responsive for header */
@media (max-width: 640px) {
  .header-row { flex-direction: column; align-items: flex-start; }
  .view-reports-top { width: 100%; min-width: 0; }
}
</style>