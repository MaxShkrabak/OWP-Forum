<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'

const q = ref('')
const tags = ref([])
const loading = ref(false)
const error = ref('')

const form = ref({
  open: false,
  mode: 'add', // add | edit
  id: null,
  name: '',
  usableByRoleId: 1,
})

const modal = ref({
  open: false,
  type: 'info', // info | confirm
  title: '',
  message: '',
  onConfirm: null,
})

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

function normalizeName(s) {
  return String(s ?? '').trim().replace(/\s+/g, ' ')
}
function isDuplicateName(name, excludeId = null) {
  const n = normalizeName(name).toLowerCase()
  if (!n) return false
  return tags.value.some(t => {
    const same = normalizeName(t.Name).toLowerCase() === n
    const notSelf = excludeId == null ? true : Number(t.TagID) !== Number(excludeId)
    return same && notSelf
  })
}

const filteredTags = computed(() => {
  const needle = q.value.trim().toLowerCase()
  if (!needle) return tags.value
  return tags.value.filter(t => String(t.Name ?? '').toLowerCase().includes(needle))
})

async function loadTags() {
  loading.value = true
  error.value = ''
  try {
    const res = await client.get('/admin/tags')
    tags.value = res.data.items || []
  } catch (e) {
    error.value = e?.response?.data?.error || e.message || 'Failed to load tags'
    tags.value = []
  } finally {
    loading.value = false
  }
}

function openAdd() {
  form.value = { open: true, mode: 'add', id: null, name: '', usableByRoleId: 1 }
}
function openEdit(t) {
  form.value = {
    open: true,
    mode: 'edit',
    id: Number(t.TagID),
    name: String(t.Name ?? ''),
    usableByRoleId: Number(t.UsableByRoleID ?? 1),
  }
}
function closeForm() {
  form.value.open = false
}

function requestSave() {
  const name = normalizeName(form.value.name)
  if (!name) return showInfo('Missing name', 'Please enter a tag name.')

  const excludeId = form.value.mode === 'edit' ? form.value.id : null
  if (isDuplicateName(name, excludeId)) {
    return showInfo('Duplicate tag', 'That tag already exists. Please choose a different name.')
  }

  const minRole = Number(form.value.usableByRoleId || 1)

  if (form.value.mode === 'add') {
    showConfirm('Confirm add tag?', `Add tag "${name}"?`, async () => {
      closeModal()
      try {
        await client.post('/admin/tags', { name, usableByRoleId: minRole })
        closeForm()
        await loadTags()
      } catch (e) {
        showInfo('Failed to add tag', e?.response?.data?.error || e.message || 'Server error')
      }
    })
    return
  }

  const original = tags.value.find(t => Number(t.TagID) === Number(form.value.id))
  const from = normalizeName(original?.Name)

  showConfirm('Confirm edit tag?', `Change "${from}" to "${name}"?`, async () => {
    closeModal()
    try {
      await client.patch(`/admin/tags/${form.value.id}`, { name, usableByRoleId: minRole })
      closeForm()
      await loadTags()
    } catch (e) {
      showInfo('Failed to update tag', e?.response?.data?.error || e.message || 'Server error')
    }
  })
}


function requestDelete(t) {
  const id = Number(t.TagID)
  const name = normalizeName(t.Name)

  showConfirm(
    'Confirm delete tag?',
    `Delete "${name}"? This cannot be undone.`,
    async () => {
      closeModal()
      try {
        await client.delete(`/admin/tags/${id}`)
        await loadTags()
        showInfo('Tag deleted', `"${name}" was deleted successfully.`)
      } catch (e) {
        showInfo('Failed to delete tag', e?.response?.data?.error || e.message || 'Server error')
      }
    }
  )
}

onMounted(loadTags)
</script>

<template>
  <div class="admin-roles-wrapper text-start">
    <h2 class="page-title mb-4">Manage Tags</h2>

    <div class="admin-card">
      <div class="toolbar mb-4">
        <div class="search-wrapper">
          <i class="bi bi-search search-icon"></i>
          <input v-model="q" placeholder="Search tags..." />
        </div>

        <button class="btn-add" @click="openAdd">
          <i class="bi bi-plus-lg"></i>
          Add Tag
        </button>
      </div>

      <div v-if="loading" class="state mt-3 text-center">Loading…</div>
      <div v-if="error" class="err mt-3">{{ error }}</div>

      <table v-if="!loading && filteredTags.length" class="admin-table mt-3">
        <thead>
          <tr>
            <th style="width: 90px;">ID</th>
            <th>Name</th>
            <th style="width: 150px;">Min Role</th>
            <th style="width: 200px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in filteredTags" :key="t.TagID">
            <td class="admin-id">{{ t.TagID }}</td>
            <td class="admin-name">{{ t.Name }}</td>
            <td class="admin-email">{{ t.UsableByRoleID }}</td>
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

      <div v-if="!loading && filteredTags.length === 0" class="state mt-4 text-center">
        No tags found.
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="form.open" class="inner-warning-overlay" @mousedown.self="closeForm">
      <div class="confirm-card">
        <h3 class="confirm-title">{{ form.mode === 'add' ? 'Add Tag' : 'Edit Tag' }}</h3>
        <p class="confirm-subtitle">{{ form.mode === 'add' ? 'Create a new tag.' : 'Update the tag.' }}</p>

        <div class="form-field">
          <label class="field-label">Tag name</label>
          <input class="field-input" v-model="form.name" placeholder="e.g. Research" @keydown.enter.prevent="requestSave" />
        </div>

        <div class="form-field" style="margin-top: 14px;">
          <label class="field-label">Usable By Role ID</label>
          <select class="field-input" v-model="form.usableByRoleId">
            <option :value="1">1 - User</option>
            <option :value="2">2 - Student</option>
            <option :value="3">3 - Moderator</option>
            <option :value="4">4 - Admin</option>
          </select>
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

.toolbar { display: flex; align-items: center; width: 100%; gap: 14px; }
.search-wrapper { position: relative; width: 100%; flex: 1; }
.search-icon {
  position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
  color: #6b7280; font-size: 1.1rem; pointer-events: none;
}
.toolbar input {
  width: 100%; padding: 12px 20px 12px 48px; font-size: 15px;
  border-radius: 50px; border: 1px solid #a8c1bc; background: #fff; color: #1f2937;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04); outline: none; transition: all 0.2s ease;
}
.toolbar input:focus { border-color: #004750; box-shadow: 0 4px 12px rgba(0,71,80,0.15); }
.err { color: #ff6b6b; font-weight: bold; }

.admin-card {
  width: 100%; background: #fff; border-radius: 16px; padding: 24px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.05);
}

.admin-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
.admin-table thead th {
  background: #f8fafc; color: #374151; font-size: 13px; padding: 10px;
  text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #e5e7eb;
}
.admin-table tbody tr { background: #fff; border-radius: 12px; transition: background 0.15s ease, box-shadow 0.15s ease; }
.admin-table tbody td { padding: 12px 10px; vertical-align: middle; }
.admin-table tbody tr:hover { background: #f5f9f8; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

.admin-id { color: #888; font-size: 0.85rem; }
.admin-name { font-weight: 600; color: #1f3d3a; }
.admin-email { font-size: 0.85rem; color: #5a6f6c; }

.btn-add {
  display: inline-flex; align-items: center; gap: 8px;
  background: #004750; border: none; color: #fff;
  padding: 10px 16px; border-radius: 14px; cursor: pointer; font-weight: 700;
  white-space: nowrap; box-shadow: 0 6px 16px rgba(0,71,80,0.18);
}
.btn-add:hover { background: #00363d; }

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
  border-radius: 20px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);
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
</style>
