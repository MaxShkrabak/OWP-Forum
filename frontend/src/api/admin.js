import client from "./client";

export async function getAdminRoles() {
  const { data } = await client.get("/admin/roles");
  return (data.roles || []).map((r) => ({
    id: r.id,
    label: r.name.charAt(0).toUpperCase() + r.name.slice(1),
  }));
}

export async function getAdminCategories() {
  const res = await client.get("/admin/categories");
  return (res.data.items || []).map((c) => ({
    categoryId: Number(c.categoryId),
    name: c.name,
    usableByRoleID: Number(c.usableByRoleID),
  }));
}

export async function createCategory(name, usableByRoleID) {
  const { data } = await client.post("/admin/categories", {
    name,
    usableByRoleID,
  });
  return data;
}

export async function updateCategory(id, name, usableByRoleID) {
  const { data } = await client.patch(`/admin/categories/${id}`, {
    name,
    usableByRoleID,
  });
  return data;
}

export async function deleteCategory(id) {
  const { data } = await client.delete(`/admin/categories/${id}`);
  return data;
}

export async function getAdminTags() {
  const res = await client.get("/admin/tags");
  return res.data.items || [];
}

export async function createTag(name, usableByRoleId) {
  const { data } = await client.post("/admin/tags", { name, usableByRoleId });
  return data;
}

export async function updateTag(id, name, usableByRoleId) {
  const { data } = await client.patch(`/admin/tags/${id}`, {
    name,
    usableByRoleId,
  });
  return data;
}

export async function deleteTag(id) {
  const { data } = await client.delete(`/admin/tags/${id}`);
  return data;
}

export async function getAdminReportTags() {
  const res = await client.get("/admin/report-tags");
  return res.data.items || [];
}

export async function createReportTag(tagName) {
  const { data } = await client.post("/admin/report-tags", { tagName });
  return data;
}

export async function updateReportTag(id, tagName) {
  const { data } = await client.patch(`/admin/report-tags/${id}`, { tagName });
  return data;
}

export async function deleteReportTag(id) {
  const { data } = await client.delete(`/admin/report-tags/${id}`);
  return data;
}

export async function getAdminUsers(query = "", options = {}) {
  const page = options.page ?? 1;
  const perPage = options.perPage ?? 25;
  const params = { page, perPage };
  if (query.trim()) params.q = query.trim();
  const res = await client.get("/admin/users", { params });
  const users = (res.data.users || []).map((u) => ({
    ...u,
    isBanned: Boolean(Number(u.isBanned ?? 0)),
    banType:
      u.banType && (u.banType === "permanent" || u.banType === "temporary")
        ? u.banType
        : null,
    bannedUntil: u.bannedUntil ? String(u.bannedUntil) : null,
  }));
  return {
    users,
    total: Number(res.data.total ?? users.length),
    page: Number(res.data.page ?? page),
    perPage: Number(res.data.perPage ?? perPage),
  };
}

export async function updateUserBan(userId, payload) {
  const { data } = await client.patch(`/admin/users/${userId}/ban`, payload);
  return data;
}

export async function updateUserRole(userId, roleId) {
  const { data } = await client.patch(`/admin/users/${userId}/role`, {
    roleId,
  });
  return data;
}
