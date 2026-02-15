<script setup>
import { ref, onMounted } from 'vue';
import OWPLogoSmall from '@/assets/img/svg/owp-symbol-wht.svg'
import { RouterLink } from 'vue-router';
import client from '@/api/client'

// --- Tabs & Layout State ---
const activeTab = ref('Roles'); // Defaulting to Roles so you can see it immediately
const tabs = ref([
    { name: 'Users', icon: 'bi-person-fill-gear' },
    { name: 'Roles', icon: 'bi-diagram-3-fill' },
    { name: 'Categories', icon: 'bi-file-text' },
    { name: 'Tags', icon: 'bi-tags-fill' },
    { name: 'Reports', icon: 'bi-flag-fill' }
])

// --- Roles & Users State ---
const q = ref('')
const users = ref([])
const loading = ref(false)
const error = ref('')
const currentUserId = ref(null)

const showRoleConfirm = ref(false)
const pending = ref({ user: null, newRoleId: null, oldRoleId: null })

// Keep dropdown values stable and revert cleanly on cancel
const roleDraft = ref({}) // { [userId]: number }

const roles = [
  { id: 1, label: 'User' },
  { id: 2, label: 'Student' },
  { id: 3, label: 'Moderator' },
  { id: 4, label: 'Admin' },
]

const modal = ref({
  open: false,
  type: 'confirm', // 'confirm' | 'info'
  title: '',
  message: '',
})

// --- Functions ---
function roleLabel(id) {
  return id === 1 ? 'User'
    : id === 2 ? 'Student'
    : id === 3 ? 'Moderator'
    : 'Admin'
}

function showInfo(title, message) {
  modal.value = { open: true, title, message, type: 'info' }
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

  // Prevent self-demotion/changes
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

  if (hasName) {
    const fullName = `${first} ${last}`.trim()
    return email ? `${fullName} (${email})` : fullName
  }
  if (email) return email
  return `ID: ${id}`
}

function cancelRoleConfirm() {
  const u = pending.value.user
  if (u) roleDraft.value[u.User_ID] = Number(u.RoleID) // revert
  pending.value = { user: null, newRoleId: null, oldRoleId: null }
  showRoleConfirm.value = false
}

async function confirmRoleChange() {
  const u = pending.value.user
  const newRole = pending.value.newRoleId
  showRoleConfirm.value = false

  if (u && newRole != null) {
    await applyRoleChange(u, newRole)
  }
  pending.value = { user: null, newRoleId: null, oldRoleId: null }
}

onMounted(async () => {
  try {
    const me = await client.get('/admin/me')
    currentUserId.value = Number(me.data.user.User_ID)
    await loadUsers()
  } catch (e) {
    console.error("Failed to load initial admin data", e)
  }
})
</script>

<template>
    <div class="page">
        <div class="page-container d-flex">
            <div class="panel-nav w-25">
                <div class="nav-header text-center py-1">
                    <div class="nav-logo-container p-2">
                        <RouterLink to="/">
                            <img :src="OWPLogoSmall" alt="owp logo small" class="nav-logo">
                        </RouterLink>
                        <span class="forum-title d-none d-md-inline">Forum</span>
                    </div>
                </div>

                <div class="nav-divider mb-3 mt-2 mt-md-1"></div>
                <span class="forum-title fs-6 ps-2 d-md-none">Forum:</span>

                <div class="nav-opts" v-for="tab in tabs" :key="tab.name">
                    <div class="btn-container mb-1" :class="{ 'nav-btn-active-container': activeTab === tab.name }">
                        <button class="nav-btns ms-1 ms-sm-3 py-2 px-0 px-md-1 text-start row w-100"
                            :class="{ 'nav-btn-active': activeTab === tab.name }" @click="activeTab = tab.name">
                            <i class="bi col-auto d-none d-sm-block" :class="tab.icon"></i>
                            <span class="nav-name col-auto">{{ tab.name }}</span>
                            <i class="bi bi-arrow-right-short col-auto d-none d-lg-block ms-auto"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="panel-content w-75 pt-4 px-4 overflow-auto">
                <div class="tab-content h-100" v-for="tab in tabs" :key="'content-'+tab.name" v-show="activeTab === tab.name">
                    
                    <template v-if="tab.name === 'Roles'">
                        <h2 class="page-title text-start mb-4">Manage Roles</h2>
                        
                        <div class="admin-card text-start">
                            <div class="toolbar">
                                <input
                                    v-model="q"
                                    placeholder="Search by email, first/last name, or ID..."
                                    @input="onSearchInput"
                                />
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
                                        <td class="admin-email">{{ u.Email || '—' }}</td>
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

                            <div v-if="!loading && users.length === 0" class="state mt-4 text-center">
                                No users found.
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <h2 class="page-title text-start mb-4">{{ tab.name }} Overview</h2>
                        <p class="text-muted text-start">The {{ tab.name }} component will render here.</p>
                    </template>

                </div>
            </div>
        </div>

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
                    <button class="btn-confirm" @click="confirmRoleChange">Confirm</button>
                </div>
            </div>
        </div>

        <div v-if="modal.open" class="inner-warning-overlay" @mousedown.self="closeModal">
            <div class="confirm-card">
                <h3 class="confirm-title">{{ modal.title }}</h3>
                <p class="confirm-subtitle">{{ modal.message }}</p>
                <div class="confirm-actions">
                    <button class="btn-confirm" @click="closeModal">OK</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* --- Layout & Nav Styles (from AdminPanel.vue) --- */
.page-container {
    min-height: 85vh;
    height: 100vh; /* Keeps the panel full height so overflow scrolling works on the right */
}

.panel-content {
    background-color: #cbdad5;
}

.panel-nav {
    background: linear-gradient(210deg, #005f6b 0%, #004750 100%);
}

.nav-logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.8rem;
    .forum-title {
        color: rgba(255, 255, 255, 0.836);
    }
}

.forum-title {
    font-size: 2.0rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.418);
    font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
}

.nav-logo {
    width: 3.5rem;
}

.nav-divider {
    margin: auto;
    background-color: #6dbe4b;
    width: 75%;
    height: 3px;
    border-radius: 10px;
    margin-top: 6px;
    box-shadow: 0 2px 8px #6ebe4b86;
}

.nav-btns {
    border: none;
    background: none;
    transition: all 0.2s ease;
}

.nav-btn-active-container {
    border-right: 5px #6dbe4b solid;
    border-radius: 4px;
}

.nav-btns.nav-btn-active {
    background-color: green !important;
    border-radius: 5px;
}

.nav-btns:hover {
    background-color: rgba(211, 211, 211, 0.363);
    border-radius: 5px;
    color: white;

    .nav-btn-active {
        background: none;
    }
}

.nav-btns span,
.nav-btns i {
    color: white;
}

.nav-name {
    display: inline-block;
    width: unset;
    font-weight: 400;
    font-size: small;
}

@media (min-width: 768px) {
    .nav-logo { width: 3rem; }
    .nav-name { font-size: large; width: 14vw; font-weight: 600; }
}

@media (min-width: 992px) {
    .nav-logo { width: 4rem; }
}

@media (min-width: 1400px) {
    .nav-logo { width: 4rem; }
}

/* --- Content Styles (imported from AdminUsers.vue) --- */
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #004750;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
}

.toolbar input {
  flex: 1;
  padding: 10px 14px;
  font-size: 14px;
  border-radius: 14px;
  border: 1px solid #004750; /* Matches the dark green sidebar */
  background: #ffffff; /* Clean white background */
  color: #004750; /* Dark green text */
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.toolbar input::placeholder {
  color: #6b7280;
}

.toolbar input:focus {
  border-color: #6dbe4b; /* Matches the bright green active tab/divider */
  box-shadow: 0 0 0 3px rgba(109, 190, 75, 0.25); /* Soft glow using the accent green */
  background: #ffffff;
}

.err {
  color: #ff6b6b;
  font-weight: bold;
}

/* Admin Table */
.admin-card {
  width: 100%;
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.05);
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
  padding: 6px 10px;
  border-radius: 10px;
  font-weight: 600;
  border: 1px solid #ccc;
  outline: none;
  cursor: pointer;
}

.role-select.admin { background: #f2cece; color: #ff0000; border-color: #f2cece; }
.role-select.moderator { background: #fdf4d9; color: #d29e00; border-color: #fdf4d9; }
.role-select.user { background: #d5f5d7; color: #0a3800; border-color: #d5f5d7; }
.role-select.student { background: #b9d0e8; color: #0015ff; border-color: #b9d0e8; }

/* Overlay & Modals */
.inner-warning-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.confirm-card {
  background: #fff;
  color: #1f2937;
  width: min(500px, 90vw);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.25);
  text-align: center;
}

.confirm-title {
  margin: 0 0 12px;
  font-size: 22px;
  font-weight: 700;
  color: #1f2937;
}

.confirm-subtitle {
  margin: 0 0 24px;
  font-size: 15px;
  color: #4b5563;
  line-height: 1.5;
}

.confirm-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
}

.btn-back {
  background: #fff;
  border: 1px solid #cbd5e1;
  color: #374151;
  padding: 10px 20px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 600;
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
  font-weight: 700;
}

.btn-confirm:hover {
  background: #00363d;
}
</style>