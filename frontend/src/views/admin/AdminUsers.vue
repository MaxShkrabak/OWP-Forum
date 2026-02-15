<script setup>
import { ref, onMounted } from 'vue'
import client from '@/api/client'

const q = ref('')
const users = ref([])
const loading = ref(false)
const error = ref('')

const showRoleConfirm = ref(false)
const pending = ref({ user: null, newRoleId: null })

// Keep dropdown values stable and revert cleanly on cancel
const roleDraft = ref({}) // { [userId]: number }


const roles = [
  { id: 1, label: 'User' },
  { id: 2, label: 'Student' },
  { id: 3, label: 'Moderator' },
  { id: 4, label: 'Admin' },
]

function roleLabel(id) {
  return id === 1 ? 'User'
    : id === 2 ? 'Student'
    : id === 3 ? 'Moderator'
    : 'Admin'
}

const modal = ref({
  open: false,
  type: 'confirm', // 'confirm' | 'info'
  title: '',
  message: '',
})

function showInfo(title, message) {
  modal.value = {
    open: true,
    title,
    message,
    type: 'info'
  }
}

function closeModal() {
  modal.value.open = false
}

let t = null

function onSearchInput() {
  clearTimeout(t)

  t = setTimeout(() => {
    loadUsers()
  }, 350)
}
async function loadUsers() {
  loading.value = true
  error.value = ''

  try {
    const params = q.value.trim() ? { q: q.value.trim() } : {}
    const res = await client.get('/admin/users', { params })

    users.value = res.data.users || []

    // init roleDraft from users
    const map = {}
    for (const u of users.value) map[u.User_ID] = Number(u.RoleID)
    roleDraft.value = map

  } catch (e) {
    error.value = e?.response?.data?.error || e.message || 'Failed to load users'
    users.value = []
  } finally {
    loading.value = false
  }
}

function onRoleSelected(user) {
  const newRole = Number(roleDraft.value[user.User_ID])
  const oldRole = Number(user.RoleID)

  if (newRole === oldRole) return

    // if you have your own id available as currentUserId
    if (Number(user.User_ID) === Number(currentUserId.value)) {
    roleDraft.value[user.User_ID] = oldRole // revert dropdown

    showInfo(
        "Action not allowed",
        "You cannot change your own role. Ask another admin to update your permissions."
    )

    return
    }

  const involvesElevated = (oldRole >= 3 || newRole >= 3)
  if (involvesElevated) {
    pending.value = { user, oldRoleId: oldRole, newRoleId: newRole }
    showRoleConfirm.value = true
    return
  }

  applyRoleChange(user, newRole)
}

async function applyRoleChange(user, newRole) {
  try {
    await client.patch(`/admin/users/${user.User_ID}/role`, { roleId: newRole })
    user.RoleID = String(newRole)
    user.RoleName = newRole === 1 ? 'user' : newRole === 2 ? 'student' : newRole === 3 ? 'moderator' : 'admin'
    roleDraft.value[user.User_ID] = newRole
  } catch (e) {
    // revert dropdown if request fails
    roleDraft.value[user.User_ID] = Number(user.RoleID)
    alert(e?.response?.data?.error || e.message || 'Failed to update role')
  }
}

function formatUserDisplay(u) {
  if (!u) return ''

  const first = (u.FirstName || '').trim()
  const last  = (u.LastName || '').trim()
  const email = (u.Email || '').trim()
  const id    = u.User_ID

  const hasName = first || last

  // If we have a name, show "First Last (email)" when email exists
  if (hasName) {
    const fullName = `${first} ${last}`.trim()
    return email ? `${fullName} (${email})` : fullName
  }

  // No name: show email if available
  if (email) return email

  // No name or email: show id
  return `ID: ${id}`
}

function cancelRoleConfirm() {
  const u = pending.value.user
  if (u) roleDraft.value[u.User_ID] = Number(u.RoleID) // revert
  pending.value = { user: null, newRoleId: null }
  showRoleConfirm.value = false
}

async function confirmRoleChange() {
  const u = pending.value.user
  const newRole = pending.value.newRoleId
  showRoleConfirm.value = false

  if (u && newRole != null) {
    await applyRoleChange(u, newRole)
  }

  pending.value = { user: null, newRoleId: null }
}

const currentUserId = ref(null)

onMounted(async () => {
  const me = await client.get('/admin/me')
  currentUserId.value = Number(me.data.user.User_ID)
  await loadUsers()
})
</script>

<template>
  <div class="wrap">
    <h2 class="page-title">Admin • Users</h2>

    <div class="admin-card">
        <div class="toolbar">
        <input
            v-model="q"
            placeholder="Search by email, first/last name, or ID..."
            @input="onSearchInput"
        />
        </div>

        <div v-if="loading" class="state">Loading…</div>
        <div v-if="error" class="err">{{ error }}</div>

        <table class="admin-table" v-if="!loading && users.length">
        <!-- rows -->
        </table>

        <div v-if="!loading && users.length === 0" class="state">
        No users found.
        </div>
    </div>
    <table v-if="!loading && users.length" class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="u in users" :key="u.User_ID">
          <td class="admin-id">{{ u.User_ID }}</td>
            <td>
            <div class="admin-name">
                {{ (u.FirstName || '') + ' ' + (u.LastName || '') }}
            </div>
            </td>
            <td class="admin-email">
            {{ u.Email || '—' }}
            </td>
          <td>
            <select
                class="role-select"
                :class="roleLabel(roleDraft[u.User_ID]).toLowerCase()"
                v-model="roleDraft[u.User_ID]"
                @change="onRoleSelected(u)"
                >
            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.label }}</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="!loading && users.length === 0">No users found.</div>
  </div>

    <!-- Role Confirmation  -->
    <div v-if="showRoleConfirm" class="inner-warning-overlay" @mousedown.self="cancelRoleConfirm">
        <div class="confirm-card">
            <h3 class="confirm-title">Confirm role change?</h3>
            <p class="confirm-subtitle">
                Change
                <strong>{{ formatUserDisplay(pending.user) }}</strong>
                from <strong>{{ roleLabel(pending.oldRoleId) }}</strong>
                to <strong>{{ roleLabel(pending.newRoleId) }}</strong>?
            </p>
            <div class="confirm-actions">
            <button class="btn-back" @click="cancelRoleConfirm">Back</button>
            <button class="btn-confirm" @click="confirmRoleChange">
                Confirm
            </button>
            </div>
        </div>
    </div>

    <!-- Popup self demotion  -->
<div v-if="modal.open" class="inner-warning-overlay" @mousedown.self="closeModal">
    <div class="confirm-card">
        <h3 class="confirm-title">{{ modal.title }}</h3>
        <p class="confirm-subtitle">{{ modal.message }}</p>

        <div class="confirm-actions">
        <button class="btn-confirm" @click="closeModal">
            OK
        </button>
        </div>
    </div>
</div>
</template>

<style scoped>

/* Layout */
.wrap {
  min-height: 100vh;
  padding: 32px 20px;
  background: linear-gradient(
    180deg,
    #eefbf5 0%,
    #f6fdf9 100%
  );
}

.page-title {
  margin-bottom: 14px;
  font-size: 22px;
  font-weight: 700;
  color: #064e3b;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.toolbar input {
  flex: 1;
  padding: 10px 14px;
  font-size: 14px;
  border-radius: 14px;
  border: 1px solid #d1fae5;
  background: #f0fdf4;
  color: #064e3b;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.toolbar input::placeholder {
  color: #6b7280;
}

.toolbar input:focus {
  border-color: #34d399;
  box-shadow: 0 0 0 3px rgba(52,211,153,0.35);
  background: #ffffff;
}

input {
  flex: 1;
  padding: 8px;
}

button {
  padding: 8px 12px;
}

.err {
  color: #ff6b6b;
  margin: 8px 0;
}

/* Admin Table */
.admin-card {
  max-width: 1100px;
  margin: 0 auto;
  background: #ffffff;
  border-radius: 22px;
  padding: 20px 22px 24px;
  box-shadow:
    0 18px 40px rgba(0,0,0,0.08),
    inset 0 1px 0 rgba(255,255,255,0.7);
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
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 2px solid #e5e7eb;
}

.admin-table tbody tr {
  background: #fff;
  border-radius: 12px;
  transition: background 0.15s ease, box-shadow 0.15s ease;
}

.admin-table tbody tr:hover {
  background: #f5f9f8;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
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

/* Role Select */
.role-select {
  padding: 4px 8px;
  border-radius: 12px;
  font-weight: 600;
  border: 1px solid #ccc;
}

.role-select.admin {
  background: #f2cece;
  color: #ff0000;
}

.role-select.moderator {
  background: #fdf4d9;
  color: #d29e00;
}

.role-select.user {
  background: #d5f5d7;
  color: #0a3800;
}

.role-select.student {
  background: #b9d0e8;
  color: #0015ff;
}

/* Overlay */
.inner-warning-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

/* Confirmation Modal */
.confirm-card {
  background: #fff;
  color: #1f2937;
  width: min(560px, 92vw);
  border-radius: 20px;
  padding: 28px 28px 22px;
  box-shadow: 0 18px 50px rgba(0,0,0,0.2);
  text-align: center;
}

.confirm-title {
  margin: 0 0 10px;
  font-size: 20px;
  font-weight: 700;
  color: #6b7280;
}

.confirm-subtitle {
  margin: 0 0 22px;
  font-size: 14px;
  color: #6b7280;
}

.confirm-actions {
  display: flex;
  justify-content: center;
  gap: 14px;
}

.btn-back {
  background: #fff;
  border: 1px solid #cbd5e1;
  color: #374151;
  padding: 10px 18px;
  border-radius: 14px;
  cursor: pointer;
  font-weight: 600;
}

.btn-back:hover {
  background: #f8fafc;
}

.btn-confirm {
  background: #2f6f44;
  border: none;
  color: #fff;
  padding: 10px 18px;
  border-radius: 14px;
  cursor: pointer;
  font-weight: 700;
}

.btn-confirm:hover {
  filter: brightness(0.95);
}

/* Global polish */
* {
  transition: background-color 0.15s ease,
              color 0.15s ease,
              box-shadow 0.15s ease;
}


</style>
