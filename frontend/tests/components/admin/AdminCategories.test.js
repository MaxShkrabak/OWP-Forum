/**
 * AdminCategories — unit tests.
 * Covers:
 * - categories table renders on load
 * - clicking Delete opens confirmation modal with category name and General fallback note
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
});
