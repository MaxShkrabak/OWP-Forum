/**
 * Manage Categories Visibility (Admin) — unit tests.
 * Covers:
 * - loading visibleFromRoleID from API
 * - rendering visibility labels
 * - opening edit modal with correct visibility
 * - sending null for Public
 * - sending numeric values for User+/Student+/Moderator+/Admin only
 */

import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminCategories from "@/components/admin/AdminCategories.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock("@/api/client", () => ({ default: mockClient }));

const mockCategories = [
  { categoryId: 1, name: "General", usableByRoleID: 1, visibleFromRoleID: null },
  { categoryId: 2, name: "Help", usableByRoleID: 1, visibleFromRoleID: 1 },
  { categoryId: 3, name: "Student Space", usableByRoleID: 1, visibleFromRoleID: 2 },
  { categoryId: 4, name: "Mods", usableByRoleID: 3, visibleFromRoleID: 3 },
  { categoryId: 5, name: "Admins", usableByRoleID: 4, visibleFromRoleID: 4 },
];

describe("Manage Categories Visibility (Admin) — AdminCategories.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockResolvedValue({
      data: {
        items: mockCategories,
      },
    });
    mockClient.patch.mockResolvedValue({ data: { ok: true } });
    mockClient.post.mockResolvedValue({ data: { ok: true } });
  });

  it("loads categories and renders visibility labels", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    expect(wrapper.find(".admin-table").exists()).toBe(true);
    expect(wrapper.text()).toContain("Public");
    expect(wrapper.text()).toContain("User+");
    expect(wrapper.text()).toContain("Student+");
    expect(wrapper.text()).toContain("Moderator+");
    expect(wrapper.text()).toContain("Admin only");
  });

  it("opens edit modal with Public when visibleFromRoleID is null", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    const rows = wrapper.findAll(".admin-table tbody tr");
    await rows[0].find(".btn-action").trigger("click");

    const visibilitySelect = wrapper.findAll("select").at(-1);
    expect(visibilitySelect.exists()).toBe(true);
    expect(visibilitySelect.element.value).toBe("public");
  });

  it("opens edit modal with numeric visibility converted to string", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    const rows = wrapper.findAll(".admin-table tbody tr");
    await rows[2].find(".btn-action").trigger("click");

    const visibilitySelect = wrapper.findAll("select").at(-1);
    expect(visibilitySelect.element.value).toBe("2");
  });

  it("sends null when editing category visibility to Public", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    const rows = wrapper.findAll(".admin-table tbody tr");
    await rows[1].find(".btn-action").trigger("click");

    const inputs = wrapper.findAll("input");
    await inputs[0].setValue("Help");

    const selects = wrapper.findAll("select");
    await selects[0].setValue("1"); // usableByRoleID
    await selects[1].setValue("public"); // visibility

    const saveButton = wrapper.find(".btn-confirm");
    await saveButton.trigger("click");
    await flushPromises();

    expect(mockClient.patch).toHaveBeenCalledTimes(1);
    expect(mockClient.patch).toHaveBeenCalledWith("/admin/categories/2", {
      name: "Help",
      usableByRoleID: 1,
      visibleFromRoleID: null,
    });
  });

  it("sends numeric visibility when editing category to Student+", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    const rows = wrapper.findAll(".admin-table tbody tr");
    await rows[0].find(".btn-action").trigger("click");

    const inputs = wrapper.findAll("input");
    await inputs[0].setValue("General");

    const selects = wrapper.findAll("select");
    await selects[0].setValue("1");
    await selects[1].setValue("2");

    const saveButton = wrapper.find(".btn-confirm");
    await saveButton.trigger("click");
    await flushPromises();

    expect(mockClient.patch).toHaveBeenCalledWith("/admin/categories/1", {
      name: "General",
      usableByRoleID: 1,
      visibleFromRoleID: 2,
    });
  });

  it("sends null when creating category with Public visibility", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    await wrapper.find(".btn-add").trigger("click");

    const input = wrapper.find("input");
    await input.setValue("Public Category");

    const selects = wrapper.findAll("select");
    await selects[0].setValue("1");
    await selects[1].setValue("public");

    const addButtons = wrapper.findAll(".btn-confirm");
    await addButtons[0].trigger("click");
    await flushPromises();

    expect(mockClient.post).toHaveBeenCalledWith("/admin/categories", {
      name: "Public Category",
      usableByRoleID: 1,
      visibleFromRoleID: null,
    });
  });

  it("shows validation error when add name is empty", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    await wrapper.find(".btn-add").trigger("click");
    const addButtons = wrapper.findAll(".btn-confirm");
    await addButtons[0].trigger("click");

    expect(wrapper.text()).toContain("Category name is required.");
    expect(mockClient.post).not.toHaveBeenCalled();
  });
});