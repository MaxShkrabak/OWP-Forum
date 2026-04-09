<script setup>
import { ref, onMounted, computed, watch } from "vue";
import {
  getAdminUsers,
  updateUserBan,
  updateUserRole,
  getAdminRoles,
} from "@/api/admin";
import { formatBannedUntilDateTime } from "@/utils/banDate";
import { uid } from "@/stores/userStore";
import AdminPaginationControls from "@/components/admin/AdminPaginationControls.vue";

const q = ref("");
const users = ref([]);
const userPage = ref(1);
const userPerPage = ref(25);
const userTotal = ref(0);
const loading = ref(false);
const error = ref("");
const currentUserId = uid;

const showBanModal = ref(false);
const banTarget = ref(null);
const banKind = ref("permanent");
const banUntilDate = ref("");

const showWarning = ref(false);
const warningMessage = ref("");

const roleDraft = ref({});
const showRoleConfirm = ref(false);
const pendingRole = ref({ user: null, newRoleId: null, oldRoleId: null });
const roleInfoModal = ref({ open: false, title: "", message: "" });

const roles = ref([]);

// Tomorrow in local time (YYYY-MM-DD) so date picker min is correct in user's timezone
const minBanDate = computed(() => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
});

let searchTimeout = null;
function onSearchInput() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    userPage.value = 1;
    loadUsers();
  }, 350);
}

function onUserPage(p) {
  userPage.value = p;
  loadUsers();
}

function onUserPerPage(n) {
  userPerPage.value = n;
  userPage.value = 1;
  loadUsers();
}

async function loadUsers() {
  loading.value = true;
  error.value = "";
  try {
    const result = await getAdminUsers(q.value, {
      page: userPage.value,
      perPage: userPerPage.value,
    });
    if (
      result.users.length === 0 &&
      result.total > 0 &&
      userPage.value > 1
    ) {
      const maxPage = Math.max(
        1,
        Math.ceil(result.total / result.perPage),
      );
      userPage.value = maxPage;
      await loadUsers();
      return;
    }
    users.value = result.users;
    userTotal.value = result.total;
    userPage.value = result.page;
    userPerPage.value = result.perPage;
    const map = {};
    for (const u of users.value) map[u.userId] = Number(u.roleId);
    roleDraft.value = map;
  } catch (e) {
    error.value =
      e?.response?.data?.error || e.message || "Failed to load users";
    users.value = [];
    userTotal.value = 0;
  } finally {
    loading.value = false;
  }
}

function openBanModal(user) {
  banTarget.value = user;
  banKind.value = "permanent";
  banUntilDate.value = minBanDate.value;
  showBanModal.value = true;
}

// Keep date in sync when switching to temporary (ensure not before min)
watch(banKind, (kind) => {
  if (
    kind === "temporary" &&
    (!banUntilDate.value || banUntilDate.value < minBanDate.value)
  ) {
    banUntilDate.value = minBanDate.value;
  }
});

function closeBanModal() {
  showBanModal.value = false;
  banTarget.value = null;
}

function showWarningPopup(message) {
  warningMessage.value = message;
  showWarning.value = true;
}

function closeWarningPopup() {
  showWarning.value = false;
  warningMessage.value = "";
}

async function confirmBan() {
  if (!banTarget.value) return;
  const payload = { banned: true };
  if (banKind.value === "temporary") {
    if (!banUntilDate.value) {
      showWarningPopup("Please choose an end date for the temporary ban.");
      return;
    }
    payload.banType = "temporary";
    payload.bannedUntil = banUntilDate.value;
  } else {
    payload.banType = "permanent";
  }
  try {
    await updateUserBan(banTarget.value.userId, payload);
    banTarget.value.isBanned = true;
    banTarget.value.banType = payload.banType;
    banTarget.value.bannedUntil = payload.bannedUntil || null;
    closeBanModal();
  } catch (e) {
    showWarningPopup(e?.response?.data?.error || "Failed to update ban status");
  }
}

async function unban(user) {
  try {
    await updateUserBan(user.userId, { banned: false });
    user.isBanned = false;
    user.banType = null;
    user.bannedUntil = null;
  } catch (e) {
    showWarningPopup(e?.response?.data?.error || "Failed to update ban status");
  }
}

function banStatusLabel(u) {
  if (!u.isBanned) return "Active";
  if (u.banType === "temporary" && u.bannedUntil) {
    const formatted = formatBannedUntilDateTime(u.bannedUntil, {
      dateStyle: "short",
      timeStyle: "short",
    });
    return formatted ? "Until " + formatted : "Temporary";
  }
  return "Permanent";
}

function formatUserDisplay(u) {
  if (!u) return "";
  const first = (u.firstName || "").trim();
  const last = (u.lastName || "").trim();
  const email = (u.email || "").trim();
  const hasName = first || last;
  if (hasName) {
    const fullName = `${first} ${last}`.trim();
    return email ? `${fullName} (${email})` : fullName;
  }
  return email ? email : `ID: ${u.userId}`;
}

function isAdminUser(u) {
  if (!u) return false;
  if (Number(u.roleId) === 4) return true;
  return (u.roleName || "").toLowerCase() === "admin";
}

function roleLabel(id) {
  return roles.value.find((r) => r.id === Number(id))?.label || "";
}

function showRoleInfo(title, message) {
  roleInfoModal.value = { open: true, title, message };
}

function closeRoleInfo() {
  roleInfoModal.value.open = false;
}

function onRoleSelected(user) {
  const newRole = Number(roleDraft.value[user.userId]);
  const oldRole = Number(user.roleId);

  if (newRole === oldRole) return;

  if (Number(user.userId) === Number(currentUserId.value)) {
    roleDraft.value[user.userId] = oldRole;
    showRoleInfo("Action not allowed", "You cannot change your own role.");
    return;
  }

  const involvesElevated = oldRole >= 3 || newRole >= 3;
  if (involvesElevated) {
    pendingRole.value = { user, oldRoleId: oldRole, newRoleId: newRole };
    showRoleConfirm.value = true;
    return;
  }
  applyRoleChange(user, newRole);
}

async function applyRoleChange(user, newRole) {
  try {
    await updateUserRole(user.userId, newRole);
    user.roleId = String(newRole);
    user.roleName =
      newRole === 1
        ? "user"
        : newRole === 2
          ? "student"
          : newRole === 3
            ? "moderator"
            : "admin";
    roleDraft.value[user.userId] = newRole;
  } catch (e) {
    roleDraft.value[user.userId] = Number(user.roleId);
    alert(e?.response?.data?.error || "Failed to update role");
  }
}

function cancelRoleConfirm() {
  const u = pendingRole.value.user;
  if (u) roleDraft.value[u.userId] = Number(u.roleId);
  pendingRole.value = { user: null, newRoleId: null, oldRoleId: null };
  showRoleConfirm.value = false;
}

async function confirmRoleChange() {
  const u = pendingRole.value.user;
  const newRole = pendingRole.value.newRoleId;
  showRoleConfirm.value = false;
  if (u && newRole != null) await applyRoleChange(u, newRole);
  pendingRole.value = { user: null, newRoleId: null, oldRoleId: null };
}

onMounted(async () => {
  await Promise.all([
    loadUsers(),
    getAdminRoles().then((r) => (roles.value = r)),
  ]);
});
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

      <div class="table-wrapper">
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
            <tr
              v-for="u in users"
              :key="u.userId"
              :class="{ 'row-banned': u.isBanned }"
            >
              <td class="admin-id">{{ u.userId }}</td>
              <td>
                <div class="admin-name">
                  {{ (u.firstName || "") + " " + (u.lastName || "") }}
                </div>
              </td>
              <td class="admin-email">{{ u.email || "—" }}</td>
              <td>
                <select
                  class="role-select"
                  :class="roleLabel(roleDraft[u.userId]).toLowerCase()"
                  v-model="roleDraft[u.userId]"
                  :disabled="u.userId === currentUserId"
                  @change="onRoleSelected(u)"
                >
                  <option v-for="r in roles" :key="r.id" :value="r.id">
                    {{ r.label }}
                  </option>
                </select>
              </td>
              <td>
                <span v-if="u.isBanned" class="badge badge-banned">
                  <span class="desktop-only">{{ banStatusLabel(u) }}</span>
                </span>
                <span v-else class="badge badge-active">
                  <span class="desktop-only">Active</span>
                </span>
              </td>
              <td>
                <button
                  v-if="!u.isBanned && !isAdminUser(u)"
                  type="button"
                  class="btn-ban"
                  @click="openBanModal(u)"
                >
                  <span class="desktop-only"> Ban </span>
                  <i class="bi bi-x-lg mobile-only"></i>
                </button>
                <button
                  v-else-if="u.isBanned"
                  type="button"
                  class="btn-unban"
                  @click="unban(u)"
                >
                  <span class="desktop-only"> Unban </span>
                  <i class="bi bi-check-lg mobile-only"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!loading && users.length === 0" class="state mt-4 text-center">
        No users found.
      </div>

      <AdminPaginationControls
        v-if="!loading && userTotal > 0"
        :page="userPage"
        :per-page="userPerPage"
        :total="userTotal"
        :loading="loading"
        per-page-label="Users per page"
        @update:page="onUserPage"
        @update:per-page="onUserPerPage"
      />
    </div>

    <!-- Ban type modal -->
    <div
      v-if="showBanModal && banTarget"
      class="modal-overlay"
      @mousedown.self="closeBanModal"
    >
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
          <button type="button" class="btn-modal-cancel" @click="closeBanModal">
            Cancel
          </button>
          <button type="button" class="btn-modal-confirm" @click="confirmBan">
            Confirm ban
          </button>
        </div>
      </div>
    </div>

    <!-- Warning popup -->
    <div
      v-if="showWarning"
      class="modal-overlay"
      @mousedown.self="closeWarningPopup"
    >
      <div class="modal-card modal-warning">
        <div class="warning-icon">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h3 class="modal-title">Warning</h3>
        <p class="warning-message">{{ warningMessage }}</p>
        <div class="modal-actions">
          <button
            type="button"
            class="btn-modal-confirm"
            @click="closeWarningPopup"
          >
            OK
          </button>
        </div>
      </div>
    </div>

    <!-- Role change confirmation modal -->
    <div
      v-if="showRoleConfirm"
      class="modal-overlay"
      @mousedown.self="cancelRoleConfirm"
    >
      <div class="confirm-card">
        <h3 class="confirm-title">Confirm role change?</h3>
        <p class="confirm-subtitle">
          Change <strong>{{ formatUserDisplay(pendingRole.user) }}</strong> from
          <strong>{{ roleLabel(pendingRole.oldRoleId) }}</strong> to
          <strong>{{ roleLabel(pendingRole.newRoleId) }}</strong
          >?
        </p>
        <div class="confirm-actions">
          <button class="btn-back" @click="cancelRoleConfirm">Back</button>
          <button class="btn-confirm" @click="confirmRoleChange">
            Confirm
          </button>
        </div>
      </div>
    </div>

    <!-- Role info modal (e.g. cannot change own role) -->
    <div
      v-if="roleInfoModal.open"
      class="modal-overlay"
      @mousedown.self="closeRoleInfo"
    >
      <div class="confirm-card">
        <h3 class="confirm-title">{{ roleInfoModal.title }}</h3>
        <p class="confirm-subtitle">{{ roleInfoModal.message }}</p>
        <div class="confirm-actions">
          <button class="btn-confirm" @click="closeRoleInfo">OK</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.mobile-only {
  display: table;
}
.desktop-only {
  display: none;
}
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
  padding: 10px;
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
  font-size: 10px;
  padding: 8px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 2px solid #e5e7eb;
}

.admin-table tbody tr {
  background: #fff;
  border-radius: 12px;
  transition:
    background 0.15s ease,
    box-shadow 0.15s ease;
}

.admin-table tbody tr.row-banned {
  background: #fef2f2;
}

.admin-table tbody td {
  padding: 12px 8px;
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
  font-weight: 500;
  color: #1f3d3a;
  font-size: 0.8rem;
}

.admin-email {
  font-size: 0.85rem;
  color: #5a6f6c;
}

/* Role Select */
.role-select {
  padding: 5px 2px;
  border-radius: 8px;
  font-weight: 600;
  border: 1px solid #ccc;
  outline: none;
  cursor: pointer;
  font-size: 12px;
}

.role-select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.role-select.admin {
  background: #fee2e2;
  color: #c91919;
  border-color: #fecaca;
  border-width: 2px;
}
.role-select.moderator {
  background: #fef3c7;
  color: #c56c06;
  border-color: #fde68a;
  border-width: 2px;
}
.role-select.student {
  background: #e0f2fe;
  color: #0376af;
  border-color: #bae6fd;
  border-width: 2px;
}
.role-select.user {
  background: #e8f5e9;
  color: #2a633e;
  border-color: #c8e6c9;
  border-width: 2px;
}

.badge {
  display: inline-block;
  padding: 6px 6px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

.badge-banned {
  background: #fecaca;
  color: #b91c1c;
  border: #ff6d6d 1px solid;
}

.badge-active {
  background: #d1fae5;
  color: #065f46;
  border: green 1px solid;
}

.btn-ban {
  padding: 1px 8px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 12px;
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
  padding: 1px 8px;
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

/* Table wrapper for horizontal scrolling on small screens */
.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

/* Role confirmation modal */
.confirm-card {
  background: #fff;
  color: #1f2937;
  width: min(500px, 90vw);
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

@media (min-width: 768px) {
  .admin-table thead th {
    font-size: 14px;
  }
  .badge {
    padding: 2px 10px;
  }
  .btn-ban {
    padding: 2px 14px;
  }
  .btn-unban {
    padding: 2px 14px;
  }
  .mobile-only {
    display: none;
  }
  .desktop-only {
    display: table;
  }
}

@media (max-width: 576px) {
  .admin-table thead th:nth-child(1),
  .admin-table thead th:nth-child(3) {
    display: none;
  }
  .admin-table tbody td:nth-child(1),
  .admin-table tbody td:nth-child(3) {
    display: none;
  }
  .admin-table tbody td {
    padding: 8px 6px;
  }
}
</style>
