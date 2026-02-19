<script setup>
import { ref, onMounted } from 'vue'
import client from '@/api/client'

const q = ref('')
const users = ref([])
const loading = ref(false)
const error = ref('')

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
      IsBanned: Boolean(Number(u.IsBanned ?? 0))
    }))
  } catch (e) {
    error.value = e?.response?.data?.error || e.message || 'Failed to load users'
    users.value = []
  } finally {
    loading.value = false
  }
}

async function toggleBan(user) {
  const newBanned = !user.IsBanned
  try {
    await client.patch(`/admin/users/${user.User_ID}/ban`, { banned: newBanned })
    user.IsBanned = newBanned
  } catch (e) {
    alert(e?.response?.data?.error || 'Failed to update ban status')
  }
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
              <span v-if="u.IsBanned" class="badge badge-banned">Banned</span>
              <span v-else class="badge badge-active">Active</span>
            </td>
            <td>
              <button
                type="button"
                class="btn-ban"
                :class="{ 'btn-unban': u.IsBanned }"
                @click="toggleBan(u)"
              >
                {{ u.IsBanned ? 'Unban' : 'Ban' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!loading && users.length === 0" class="state mt-4 text-center">
        No users found.
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
  border-color: #059669;
  background: #ecfdf5;
  color: #059669;
}

.btn-unban:hover {
  background: #059669;
  color: #fff;
}
</style>
