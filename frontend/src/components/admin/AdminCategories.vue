<script setup>
import { ref, onMounted } from "vue";
import {
  getAdminCategories,
  createCategory,
  updateCategory,
  deleteCategory,
} from "@/api/admin";
import { useAdminRoles } from "@/composables/useAdminRoles";
import { isDuplicateName } from "@/utils/string";

const { roles, loadRoles, roleLabel } = useAdminRoles();

const categories = ref([]);
const loading = ref(false);
const error = ref('');
const addForm = ref({ open: false, name: '', usableByRoleID: 1, visibleFromRoleID: 'public' });
const editForm = ref({ open: false, categoryId: null, name: '', usableByRoleID: 1, visibleFromRoleID: 'public' });
const deleteConfirm = ref({ open: false, category: null });
const submitError = ref("");

const visibilityOptions = [
  { id: 'public', label: 'Public' },
  { id: '1', label: 'User+' },
  { id: '2', label: 'Student+' },
  { id: '3', label: 'Moderator+' },
  { id: '4', label: 'Admin only' },
];

function normalizeVisibilityForApi(value) {
  return value === 'public' ? null : Number(value);
}

async function loadCategories() {
  loading.value = true;
  error.value = "";
  try {
    const res = await client.get('/admin/categories');
    categories.value = (res.data.items || []).map((c) => ({
    categoryId: Number(c.categoryId),
    name: c.name,
    usableByRoleID: Number(c.usableByRoleID),
    visibleFromRoleID: c.visibleFromRoleID == null ? null : Number(c.visibleFromRoleID),
  }));
  } catch (e) {
    error.value =
      e?.response?.data?.error || e.message || "Failed to load categories";
    categories.value = [];
  } finally {
    loading.value = false;
  }
}

function openAdd() {
  submitError.value = '';
  addForm.value = { open: true, name: '', usableByRoleID: 1, visibleFromRoleID: 'public' };
}

function closeAdd() {
  addForm.value.open = false;
}

function openEdit(cat) {
  submitError.value = '';
  editForm.value = {
  open: true,
  categoryId: cat.categoryId,
  name: cat.name,
  usableByRoleID: cat.usableByRoleID,
  visibleFromRoleID: cat.visibleFromRoleID == null ? 'public' : String(cat.visibleFromRoleID),
};
}

function closeEdit() {
  editForm.value.open = false;
}

function openDeleteConfirm(cat) {
  deleteConfirm.value = { open: true, category: cat };
}

function closeDeleteConfirm() {
  deleteConfirm.value = { open: false, category: null };
}

async function submitAdd() {
  submitError.value = "";
  const name = addForm.value.name.trim();
  if (!name) {
    submitError.value = "Category name is required.";
    return;
  }
  if (isDuplicateName(name, categories.value, "name", "categoryId")) {
    submitError.value = "A category with this name already exists.";
    return;
  }
  try {
    await client.post('/admin/categories', {
      name,
      usableByRoleID: addForm.value.usableByRoleID,
      visibleFromRoleID: normalizeVisibilityForApi(addForm.value.visibleFromRoleID),
    });
    closeAdd();
    await loadCategories();
  } catch (e) {
    submitError.value =
      e?.response?.data?.error || e.message || "Failed to create category";
    if (e?.response?.status === 409) {
      submitError.value = "A category with this name already exists.";
    }
  }
}

async function submitEdit() {
  submitError.value = "";
  const name = editForm.value.name.trim();
  if (!name) {
    submitError.value = "Category name is required.";
    return;
  }
  if (
    isDuplicateName(
      name,
      categories.value,
      "name",
      "categoryId",
      editForm.value.categoryId,
    )
  ) {
    submitError.value = "A category with this name already exists.";
    return;
  }
  try {
    await updateCategory(
      editForm.value.categoryId,
      name,
      usableByRoleID: editForm.value.usableByRoleID,
      visibleFromRoleID: normalizeVisibilityForApi(editForm.value.visibleFromRoleID),
    });
    closeEdit();
    await loadCategories();
  } catch (e) {
    submitError.value =
      e?.response?.data?.error || e.message || "Failed to update category";
    if (e?.response?.status === 409) {
      submitError.value = "A category with this name already exists.";
    }
  }
}

async function confirmDelete() {
  const cat = deleteConfirm.value.category;
  if (!cat) return;
  try {
    await deleteCategory(cat.categoryId);
    closeDeleteConfirm();
    await loadCategories();
  } catch (e) {
    error.value =
      e?.response?.data?.error || e.message || "Failed to delete category";
  }
}

function roleLabel(roleId) {
  return roles.find((r) => r.id === roleId)?.label || 'User';
}

function visibilityLabel(roleId) {
  if (roleId == null) return 'Public';
  return visibilityOptions.find((v) => String(v.id) === String(roleId))?.label || 'Public';
}

onMounted(loadCategories);
</script>

<template>
  <div class="admin-categories-wrapper text-start">
    <h2 class="page-title mb-4">Manage Categories</h2>

    <div class="admin-card">
      <div
        class="toolbar mb-4 d-flex justify-content-between align-items-center"
      >
        <span class="text-muted"
          >* Deleted category posts move to General.</span
        >
        <button type="button" class="btn-add" @click="openAdd">
          <i class="bi bi-plus-lg"></i> Add
          <span class="d-none d-sm-inline">category</span>
        </button>
      </div>

      <div v-if="loading" class="state mt-3 text-center">Loading…</div>
      <div v-if="error" class="err mt-3">{{ error }}</div>

      <div class="table-wrapper">
        <table v-if="!loading && categories.length" class="admin-table mt-3">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Min role</th>
              <th>Visibility</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cat in categories" :key="cat.categoryId">
              <td class="admin-id">{{ cat.categoryId }}</td>
              <td class="admin-name">{{ cat.name }}</td>
              <td>
                <span class="role-full">{{ roleLabel(cat.usableByRoleID) }}</span>
                <span class="role-short">{{ roleLabel(cat.usableByRoleID).charAt(0) }}</span>
              </td>
              <td>
                <span class="role-full">{{ visibilityLabel(cat.visibleFromRoleID) }}</span>
                <span class="role-short">{{ visibilityLabel(cat.visibleFromRoleID).charAt(0) }}</span>
              </td>
              <td>
                <div class="actions">
                  <button type="button" class="btn-action" @click="openEdit(cat)" title="Edit">
                    <i class="bi bi-pencil-square"></i> <span class="btn-text">Edit</span>
                  </button>
                  <button
                    type="button"
                    class="btn-action danger btn-delete"
                    :disabled="cat.name === 'General'"
                    :title="cat.name === 'General' ? 'Cannot delete General' : 'Delete'"
                    @click="openDeleteConfirm(cat)"
                  >
                    <i class="bi bi-trash"></i>
                    <span class="btn-text">Delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="!loading && categories.length === 0"
        class="state mt-4 text-center"
      >
        No categories yet. Add one above.
      </div>
    </div>

    <div v-if="addForm.open" class="form-overlay" @mousedown.self="closeAdd">
      <div class="form-card">
        <h3 class="form-title">Add category</h3>
        <div class="form-group">
          <label>Name</label>
          <input
            v-model="addForm.name"
            type="text"
            class="form-input"
            placeholder="Category name"
          />
        </div>
        <div class="form-group">
          <label>Minimum role</label>
          <select v-model.number="addForm.usableByRoleID" class="form-select">
            <option v-for="r in roles" :key="r.id" :value="r.id">
              {{ r.label }}
            </option>
          </select>
        </div>
        <div class="form-group">
          <label>Visibility</label>
          <select v-model="addForm.visibleFromRoleID" class="form-select">
            <option v-for="v in visibilityOptions" :key="v.id" :value="v.id">{{ v.label }}</option>
          </select>
        </div>
        <p v-if="submitError" class="err mb-2">{{ submitError }}</p>
        <div class="form-actions">
          <button type="button" class="btn-back" @click="closeAdd">
            Cancel
          </button>
          <button type="button" class="btn-confirm" @click="submitAdd">
            Add
          </button>
        </div>
      </div>
    </div>

    <div v-if="editForm.open" class="form-overlay" @mousedown.self="closeEdit">
      <div class="form-card">
        <h3 class="form-title">Edit category</h3>
        <div class="form-group">
          <label>Name</label>
          <input
            v-model="editForm.name"
            type="text"
            class="form-input"
            placeholder="Category name"
          />
        </div>
        <div class="form-group">
          <label>Minimum role</label>
          <select v-model.number="editForm.usableByRoleID" class="form-select">
            <option v-for="r in roles" :key="r.id" :value="r.id">
              {{ r.label }}
            </option>
          </select>
        </div>
        <div class="form-group">
          <label>Visibility</label>
          <select v-model="editForm.visibleFromRoleID" class="form-select">
            <option v-for="v in visibilityOptions" :key="v.id" :value="v.id">{{ v.label }}</option>
          </select>
        </div>
        <p v-if="submitError" class="err mb-2">{{ submitError }}</p>
        <div class="form-actions">
          <button type="button" class="btn-back" @click="closeEdit">
            Cancel
          </button>
          <button type="button" class="btn-confirm" @click="submitEdit">
            Save
          </button>
        </div>
      </div>
    </div>

    <div v-if="deleteConfirm.open" class="inner-warning-overlay" @mousedown.self="closeDeleteConfirm">
      <div class="confirm-card">
        <h3 class="confirm-title">Delete category?</h3>
        <p class="confirm-subtitle">
          Delete <strong>{{ deleteConfirm.category?.name }}</strong
          >? Posts in this category will be moved to <strong>General</strong>.
        </p>
        <div class="confirm-actions">
          <button type="button" class="btn-back" @click="closeDeleteConfirm">
            Cancel
          </button>
          <button
            type="button"
            class="btn-confirm btn-danger"
            @click="confirmDelete"
          >
            Delete
          </button>
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

.admin-card {
  width: 100%;
  background: #ffffff;
  border-radius: 16px;
  padding: 10px 5px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
}

.toolbar .text-muted {
  font-size: 0.9rem;
  font-style: italic;
}

.btn-add {
  background: #004750;
  color: #fff;
  border: none;
  padding: 10px 18px;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-add:hover {
  background: #00363d;
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
  transition:
    background 0.15s ease,
    box-shadow 0.15s ease;
}

.admin-table tbody td {
  padding: 12px 10px;
  vertical-align: middle;
}

.role-short { display: none; }

.admin-id {
  color: #888;
  font-size: 0.85rem;
}
.admin-name {
  font-weight: 600;
  font-size: 1.1rem;
  color: #1f3d3a;
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

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.actions {
  display: flex;
  flex-direction: row;
  gap: 4px;
}
.btn-edit:hover:not(:disabled) {
  background: #e0f2f1;
  color: #004750;
}
.btn-delete:hover:not(:disabled) {
  background: #ffebee;
  color: #c62828;
}

.err {
  color: #c62828;
  font-weight: 500;
}
.state {
  color: #64748b;
}

.form-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.form-card {
  background: #fff;
  color: #1f2937;
  width: min(420px, 90vw);
  border-radius: 20px;
  padding: 28px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  text-align: left;
}

.form-title {
  margin: 0 0 20px;
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-weight: 600;
  font-size: 14px;
  color: #374151;
  margin-bottom: 6px;
}

.form-input,
.form-select {
  width: 100%;
  padding: 10px 14px;
  font-size: 15px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  outline: none;
}

.form-input:focus,
.form-select:focus {
  border-color: #004750;
  box-shadow: 0 0 0 2px rgba(0, 71, 80, 0.2);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

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

.btn-danger {
  background: #c62828;
}
.btn-danger:hover {
  background: #b71c1c;
}

.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

@media (max-width: 576px) {
  .admin-table thead th:nth-child(1) {
    display: none;
  }
  .admin-table tbody td:nth-child(1) {
    display: none;
  }
  .admin-table tbody td {
    padding: 8px 6px;
  }
  .admin-name {
    font-size: 0.8rem;
  }

  .role-full { display: none !important; }
  .role-short { display: inline !important; }
  .btn-text { display: none; }
}
</style>