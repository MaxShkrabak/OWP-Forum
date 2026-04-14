/**
 * AdminCategories — unit tests.
 * Covers:
 * - categories table renders on load
 * - empty state shown when no categories exist
 * - clicking Delete opens confirmation modal with category name and General fallback note
 * - confirming Delete calls the API and closes the modal
 * - Edit button opens the form with the category's current name prefilled
 * - Add form shows inline error when submitted with an empty name
 * - Add form shows inline error when submitted with a duplicate name
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminCategories from "@/components/admin/AdminCategories.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }));

const mockCategories = [
  { categoryId: 1, name: "General", usableByRoleId: 1 },
  { categoryId: 2, name: "Help", usableByRoleId: 1 },
];

describe("AdminCategories.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockResolvedValue({ data: { items: mockCategories.map((c) => ({ ...c, categoryId: c.categoryId, name: c.name, usableByRoleId: c.usableByRoleId })) } });
  });

  it("displays categories from the database and delete opens confirmation with name and General", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();
    expect(wrapper.find(".admin-table").exists()).toBe(true);
    const rows = wrapper.findAll(".admin-table tbody tr");
    expect(rows.length).toBe(2);
    const deleteButtons = wrapper.findAll(".btn-delete");
    expect(deleteButtons[0].element.disabled).toBe(true);
    await deleteButtons[1].trigger("click");
    expect(wrapper.find(".confirm-title").text()).toBe("Delete category?");
    expect(wrapper.text()).toContain("Help");
    expect(wrapper.text()).toContain("General");
  });

  it("shows empty state when no categories are returned", async () => {
    mockClient.get.mockResolvedValue({ data: { items: [] } });
    const wrapper = mount(AdminCategories);
    await flushPromises();
    expect(wrapper.find(".admin-table").exists()).toBe(false);
    expect(wrapper.text()).toContain("No categories yet");
  });

  it("confirming Delete calls the delete API and closes the modal", async () => {
    mockClient.delete.mockResolvedValue({ data: {} });
    const wrapper = mount(AdminCategories);
    await flushPromises();

    await wrapper.findAll(".btn-delete")[1].trigger("click");
    await wrapper.find(".btn-confirm.btn-danger").trigger("click");
    await flushPromises();

    expect(mockClient.delete).toHaveBeenCalledTimes(1);
    expect(wrapper.find(".confirm-title").exists()).toBe(false);
  });

  it("Edit button opens the form with the category name prefilled", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    await wrapper.findAll(".btn-action:not(.btn-delete)")[0].trigger("click");

    expect(wrapper.find(".form-title").text()).toBe("Edit category");
    expect(wrapper.find("input.form-input").element.value).toBe("General");
  });

  it("shows an inline error when the Add form is submitted with an empty name", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    await wrapper.find(".btn-add").trigger("click");
    await wrapper.find(".btn-confirm").trigger("click");

    expect(wrapper.find(".err").text()).toContain("Category name is required");
  });

  it("shows an inline error when the Add form is submitted with a duplicate name", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();

    await wrapper.find(".btn-add").trigger("click");
    await wrapper.find("input.form-input").setValue("General");
    await wrapper.find(".btn-confirm").trigger("click");

    expect(wrapper.find(".err").text()).toContain("already exists");
  });
});
