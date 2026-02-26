<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import client from '@/api/client'
import { formatBannedUntilDateTime } from '@/utils/banDate'

const q = ref('')
const users = ref([])
const loading = ref(false)
const error = ref('')

const showBanModal = ref(false)
const banTarget = ref(null)
const banKind = ref('permanent') // 'permanent' | 'temporary'
const banUntilDate = ref('')

const showWarning = ref(false)
const warningMessage = ref('')

// Tomorrow in local time (YYYY-MM-DD) so date picker min is correct in user's timezone
const minBanDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
})

let searchTimeout = null
function onSearchInput() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadUsers(), 350)
}

async function loadUsers() {
  loading.value = true
  error.value = ''
  try {
    const params = q.value.trim() ? { q: q.value.trim() } : {}
    const res = await client.get('/admin/users', { params })
    users.value = (res.data.users || []).map(u => ({
      ...u,
      IsBanned: Boolean(Number(u.IsBanned ?? 0)),
      BanType: u.BanType && (u.BanType === 'permanent' || u.BanType === 'temporary') ? u.BanType : null,
      BannedUntil: u.BannedUntil ? String(u.BannedUntil) : null
    }))
  } catch (e) {
    error.value = e?.response?.data?.error || e.message || 'Failed to load users'
    users.value = []
  } finally {
    loading.value = false
  }
}

function openBanModal(user) {
  banTarget.value = user
  banKind.value = 'permanent'
  banUntilDate.value = minBanDate.value
  showBanModal.value = true
}

// Keep date in sync when switching to temporary (ensure not before min)
watch(banKind, (kind) => {
  if (kind === 'temporary' && (!banUntilDate.value || banUntilDate.value < minBanDate.value)) {
    banUntilDate.value = minBanDate.value
  }
})

function closeBanModal() {
  showBanModal.value = false
  banTarget.value = null
}

function showWarningPopup(message) {
  warningMessage.value = message
  showWarning.value = true
}

function closeWarningPopup() {
  showWarning.value = false
  warningMessage.value = ''
}

async function confirmBan() {
  if (!banTarget.value) return
  const payload = { banned: true }
  if (banKind.value === 'temporary') {
    if (!banUntilDate.value) {
      showWarningPopup('Please choose an end date for the temporary ban.')
      return
    }
    payload.banType = 'temporary'
    payload.bannedUntil = banUntilDate.value
  } else {
    payload.banType = 'permanent'
  }
  try {
    await client.patch(`/admin/users/${banTarget.value.User_ID}/ban`, payload)
    banTarget.value.IsBanned = true
    banTarget.value.BanType = payload.banType
    banTarget.value.BannedUntil = payload.bannedUntil || null
    closeBanModal()
  } catch (e) {
    showWarningPopup(e?.response?.data?.error || 'Failed to update ban status')
  }
}

async function unban(user) {
  try {
    await client.patch(`/admin/users/${user.User_ID}/ban`, { banned: false })
    user.IsBanned = false
    user.BanType = null
    user.BannedUntil = null
  } catch (e) {
    showWarningPopup(e?.response?.data?.error || 'Failed to update ban status')
  }
}

function banStatusLabel(u) {
  if (!u.IsBanned) return 'Active'
  if (u.BanType === 'temporary' && u.BannedUntil) {
    const formatted = formatBannedUntilDateTime(u.BannedUntil, { dateStyle: 'short', timeStyle: 'short' })
    return formatted ? 'Until ' + formatted : 'Temporary'
  }
  return 'Permanent'
}

function formatUserDisplay(u) {
  if (!u) return ''
  const first = (u.FirstName || '').trim()
  const last = (u.LastName || '').trim()
  const email = (u.Email || '').trim()
  const hasName = first || last
  if (hasName) {
    const fullName = `${first} ${last}`.trim()
    return email ? `${fullName} (${email})` : fullName
  }
  return email ? email : `ID: ${u.User_ID}`
}

onMounted(() => loadUsers())
</script>

<template>
  <div class="admin-users-wrapper text-start">
    <h2 class="page-title mb-4">Users Management</h2>

    <div class="admin-card">
      <div class="toolbar mb-4">
        <div class="search-wrapper">
          <i class="bi bi-search search-icon"></i>
          <input
            v-model="q"
            placeholder="Search by email, first/last name, or ID..."
            @input="onSearchInput"
          />
        </div>
      </div>

      <div v-if="loading" class="state mt-3 text-center">Loading…</div>
      <div v-if="error" class="err mt-3">{{ error }}</div>

      <table v-if="!loading && users.length" class="admin-table mt-3">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.User_ID" :class="{ 'row-banned': u.IsBanned }">
            <td class="admin-id">{{ u.User_ID }}</td>
            <td>
              <div class="admin-name">
                {{ (u.FirstName || '') + ' ' + (u.LastName || '') }}
              </div>
            </td>
            <td class="admin-email">{{ u.Email || '—' }}</td>
            <td class="admin-role">{{ u.RoleName || '—' }}</td>
            <td>
              <span v-if="u.IsBanned" class="badge badge-banned">{{ banStatusLabel(u) }}</span>
              <span v-else class="badge badge-active">Active</span>
            </td>
            <td>
              <button
                v-if="!u.IsBanned && u.RoleID !== 4"
                type="button"
                class="btn-ban"
                @click="openBanModal(u)"
              >
                Ban
              </button>
              <button
                v-else-if="u.IsBanned"
                type="button"
                class="btn-unban"
                @click="unban(u)"
              >
                Unban
              </button>
              <span v-else class="action-none">—</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!loading && users.length === 0" class="state mt-4 text-center">
        No users found.
      </div>
    </div>

    <!-- Ban type modal -->
    <div v-if="showBanModal && banTarget" class="modal-overlay" @mousedown.self="closeBanModal">
      <div class="modal-card">
        <h3 class="modal-title">Ban user</h3>
        <p class="modal-subtitle">{{ formatUserDisplay(banTarget) }}</p>
        <div class="ban-type-options">
          <label class="ban-option">
            <input type="radio" v-model="banKind" value="permanent" />
            <span>Permanent ban</span>
          </label>
          <label class="ban-option">
            <input type="radio" v-model="banKind" value="temporary" />
            <span>Temporary ban</span>
          </label>
        </div>
        <div v-if="banKind === 'temporary'" class="ban-until-row">
          <label for="ban-until">Banned until</label>
          <input
            id="ban-until"
            type="date"
            v-model="banUntilDate"
            :min="minBanDate"
            class="ban-date-input"
          />
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-modal-cancel" @click="closeBanModal">Cancel</button>
          <button type="button" class="btn-modal-confirm" @click="confirmBan">Confirm ban</button>
        </div>
      </div>
    </div>

    <!-- Warning popup -->
    <div v-if="showWarning" class="modal-overlay" @mousedown.self="closeWarningPopup">
      <div class="modal-card modal-warning">
        <div class="warning-icon">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h3 class="modal-title">Warning</h3>
        <p class="warning-message">{{ warningMessage }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-modal-confirm" @click="closeWarningPopup">OK</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #004750;
}

.toolbar {
  display: flex;
  align-items: center;
  width: 100%;
}

.search-wrapper {
  position: relative;
  width: 100%;
  max-width: 100%;
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

.toolbar input::placeholder {
  color: #9ca3af;
}

.toolbar input:focus {
  border-color: #004750;
  box-shadow: 0 4px 12px rgba(0, 71, 80, 0.15);
}

.err {
  color: #ff6b6b;
  font-weight: bold;
}

.admin-card {
  width: 100%;
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
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

.admin-table tbody tr.row-banned {
  background: #fef2f2;
}

.admin-table tbody td {
  padding: 12px 10px;
  vertical-align: middle;
}

.admin-table tbody tr:hover {
  background: #f5f9f8;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

.admin-table tbody tr.row-banned:hover {
  background: #fee2e2;
}

.admin-id {
  color: #888;
  font-size: 0.85rem;
}

.admin-name {
  font-weight: 600;
  color: #1f3d3a;
}

.admin-email {
  font-size: 0.85rem;
  color: #5a6f6c;
}

.admin-role {
  font-size: 0.9rem;
  text-transform: capitalize;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge-banned {
  background: #fecaca;
  color: #b91c1c;
}

.badge-active {
  background: #d1fae5;
  color: #065f46;
}

.btn-ban {
  padding: 6px 14px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 13px;
  border: 1px solid #dc2626;
  background: #fef2f2;
  color: #dc2626;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-ban:hover {
  background: #dc2626;
  color: #fff;
}

.btn-unban {
  padding: 6px 14px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 13px;
  border: 1px solid #059669;
  background: #ecfdf5;
  color: #059669;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-unban:hover {
  background: #059669;
  color: #fff;
}

.action-none {
  color: #9ca3af;
  font-size: 0.9rem;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-card {
  background: #fff;
  color: #1f2937;
  width: min(420px, 90vw);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
}

.modal-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  color: #004750;
}

.modal-subtitle {
  margin: 0 0 20px;
  font-size: 14px;
  color: #6b7280;
}

.ban-type-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

.ban-option {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-weight: 500;
}

.ban-option input {
  width: 18px;
  height: 18px;
}

.ban-until-row {
  margin-bottom: 20px;
}

.ban-until-row label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.ban-date-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 15px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-modal-cancel {
  padding: 10px 20px;
  border-radius: 10px;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
  font-weight: 600;
  cursor: pointer;
}

.btn-modal-cancel:hover {
  background: #f3f4f6;
}

.btn-modal-confirm {
  padding: 10px 20px;
  border-radius: 10px;
  border: none;
  background: #dc2626;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.btn-modal-confirm:hover {
  background: #b91c1c;
}

.modal-warning .modal-title {
  color: #b45309;
}

.warning-icon {
  margin-bottom: 12px;
  font-size: 2.5rem;
  color: #d97706;
}

.warning-message {
  margin: 0 0 20px;
  font-size: 15px;
  line-height: 1.5;
  color: #374151;
}

.modal-warning .btn-modal-confirm {
  background: #d97706;
}

.modal-warning .btn-modal-confirm:hover {
  background: #b45309;
}
</style>
