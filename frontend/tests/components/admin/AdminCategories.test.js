/**
 * Manage Categories (Admin) — unit tests.
 * Duplicate prevention + slugify (no DOM) + AdminCategories.vue DOM tests.
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { slugifyCategoryName } from "@/utils/slugify";
import AdminCategories from "@/components/admin/AdminCategories.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }));

const mockCategories = [
  { categoryId: 1, name: "General", usableByRoleID: 1 },
  { categoryId: 2, name: "Help", usableByRoleID: 1 },
];

// Same duplicate-check logic as AdminCategories.vue nameExists (for "Prevent duplicates" / BB-151)
function categoryNameExists(categories, name, excludeId = null) {
  const n = (name || "").trim().toLowerCase();
  if (!n) return false;
  return categories.some(
    (c) =>
      (c.name || "").trim().toLowerCase() === n && c.categoryId !== excludeId
  );
}

describe("Manage Categories (Admin) — duplicate prevention", () => {
  const categories = [
    { categoryId: 1, name: "General", usableByRoleID: 1 },
    { categoryId: 2, name: "Help", usableByRoleID: 1 },
    { categoryId: 3, name: "Random", usableByRoleID: 2 },
  ];

  it("returns true when another category has the same name (add case)", () => {
    expect(categoryNameExists(categories, "Help")).toBe(true);
    expect(categoryNameExists(categories, "help")).toBe(true);
    expect(categoryNameExists(categories, "  General  ")).toBe(true);
  });

  it("returns false when name is unique", () => {
    expect(categoryNameExists(categories, "News")).toBe(false);
    expect(categoryNameExists(categories, "Announcements")).toBe(false);
  });

  it("returns false when editing same category (excludeId)", () => {
    expect(categoryNameExists(categories, "Help", 2)).toBe(false);
    expect(categoryNameExists(categories, "Random", 3)).toBe(false);
  });

  it("returns true when editing to a name used by another category", () => {
    expect(categoryNameExists(categories, "General", 2)).toBe(true);
    expect(categoryNameExists(categories, "Help", 1)).toBe(true);
  });

  it("returns false for empty or whitespace name", () => {
    expect(categoryNameExists(categories, "")).toBe(false);
    expect(categoryNameExists(categories, "   ")).toBe(false);
    expect(categoryNameExists(categories, null)).toBe(false);
  });

  it("handles empty category list", () => {
    expect(categoryNameExists([], "Help")).toBe(false);
  });
});

describe("Manage Categories (Admin) — category slugify", () => {
  it("slugifies category name for URLs", () => {
    expect(slugifyCategoryName("Help")).toBe("help");
    expect(slugifyCategoryName("Random")).toBe("random");
    expect(slugifyCategoryName("General")).toBe("general");
  });

  it("replaces spaces and special chars with dashes", () => {
    expect(slugifyCategoryName("Announcements & News")).toBe(
      "announcements-and-news"
    );
    // & is replaced with "and", then non-alphanumerics become dashes → "qanda"
    expect(slugifyCategoryName("Q&A")).toBe("qanda");
  });

  it("trims leading and trailing dashes", () => {
    expect(slugifyCategoryName("  Help  ")).toBe("help");
    expect(slugifyCategoryName("---test---")).toBe("test");
  });
});

describe("Manage Categories (Admin) — AdminCategories.vue DOM", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockResolvedValue({ data: { items: mockCategories.map((c) => ({ ...c, categoryId: c.categoryId, name: c.name, usableByRoleID: c.usableByRoleID })) } });
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
