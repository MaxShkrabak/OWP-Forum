/**
 * AdminCategories — category visibility — unit tests.
 * Covers:
 * - loads categories and renders visibility labels (Public, User+, Student+, Moderator+, Admin only)
 * - edit modal pre-fills Public when visibleFromRoleId is null
 * - edit modal pre-fills numeric value when visibleFromRoleId is set
 * - saving with Public selected sends null to the API
 * - saving with a role selected sends the numeric roleId to the API
 * - creating a category with Public sends null visibleFromRoleId
 * - validation error shown when add name is empty
 */

import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminCategories from "@/components/admin/AdminCategories.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock("@/api/client", () => ({ default: mockClient }));

const mockCategories = [
  { categoryId: 1, name: "General", usableByRoleId: 1, visibleFromRoleId: null },
  { categoryId: 2, name: "Help", usableByRoleId: 1, visibleFromRoleId: 1 },
  { categoryId: 3, name: "Student Space", usableByRoleId: 1, visibleFromRoleId: 2 },
  { categoryId: 4, name: "Mods", usableByRoleId: 3, visibleFromRoleId: 3 },
  { categoryId: 5, name: "Admins", usableByRoleId: 4, visibleFromRoleId: 4 },
];

describe("Manage Categories Visibility (Admin) — AdminCategories.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/roles") {
        return Promise.resolve({
          data: {
            roles: [
              { id: 1, name: "user" },
              { id: 2, name: "student" },
              { id: 3, name: "moderator" },
              { id: 4, name: "admin" },
            ],
          },
        });
      }
      return Promise.resolve({ data: { items: mockCategories } });
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

  it("opens edit modal with Public when visibleFromRoleId is null", async () => {
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
    await selects[0].setValue("1"); // usableByRoleId
    await selects[1].setValue("public"); // visibility

    const saveButton = wrapper.find(".btn-confirm");
    await saveButton.trigger("click");
    await flushPromises();

    expect(mockClient.patch).toHaveBeenCalledTimes(1);
    expect(mockClient.patch).toHaveBeenCalledWith("/admin/categories/2", {
      name: "Help",
      usableByRoleId: 1,
      visibleFromRoleId: null,
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
      usableByRoleId: 1,
      visibleFromRoleId: 2,
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
      usableByRoleId: 1,
      visibleFromRoleId: null,
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