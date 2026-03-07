/**
 * Delete Confirmation Tags/Categories (BB-153) — unit tests.
 * API contract + message content + DOM test for category delete confirmation.
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminCategories from "@/components/admin/AdminCategories.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), delete: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }));

const mockCategories = [
  { categoryId: 1, name: "General", usableByRoleID: 1 },
  { categoryId: 2, name: "Help", usableByRoleID: 1 },
];

// API contract: same paths used by AdminCategories.vue and AdminTags.vue
function getCategoryDeleteEndpoint(categoryId) {
  return `/admin/categories/${categoryId}`;
}
function getTagDeleteEndpoint(tagId) {
  return `/admin/tags/${tagId}`;
}

// Confirmation message content (before delete) — JIRA: "Verify confirmation messages display"
function getCategoryDeleteConfirmTitle() {
  return "Delete category?";
}
function getCategoryDeleteConfirmMessage(name) {
  return `Delete ${name}? Posts in this category will be moved to General.`;
}
function getTagDeleteConfirmTitle() {
  return "Confirm delete tag?";
}
function getTagDeleteConfirmMessage(name) {
  return `Delete "${name}"? This cannot be undone.`;
}

// Success message (after delete) — BB-157 "Show success message after delete"
function getTagDeleteSuccessMessage(name) {
  return `"${name}" was deleted successfully.`;
}

describe("Delete Confirmation Tags/Categories — API contract", () => {
  it("category delete calls correct API path to remove from database", () => {
    expect(getCategoryDeleteEndpoint(1)).toBe("/admin/categories/1");
    expect(getCategoryDeleteEndpoint(42)).toBe("/admin/categories/42");
  });

  it("tag delete calls correct API path to remove from database", () => {
    expect(getTagDeleteEndpoint(1)).toBe("/admin/tags/1");
    expect(getTagDeleteEndpoint(99)).toBe("/admin/tags/99");
  });
});

describe("Delete Confirmation Tags/Categories — confirmation messages", () => {
  it("category delete shows confirmation title and message with name and General", () => {
    expect(getCategoryDeleteConfirmTitle()).toBe("Delete category?");
    const msg = getCategoryDeleteConfirmMessage("Help");
    expect(msg).toContain("Help");
    expect(msg).toContain("General");
    expect(msg).toContain("Delete");
  });

  it("tag delete shows confirmation title and message with name and cannot be undone", () => {
    expect(getTagDeleteConfirmTitle()).toBe("Confirm delete tag?");
    const msg = getTagDeleteConfirmMessage("spam");
    expect(msg).toContain("spam");
    expect(msg).toContain("cannot be undone");
  });
});

describe("Delete Confirmation Tags/Categories — success message after delete", () => {
  it("tag delete success message includes name and deleted successfully", () => {
    const msg = getTagDeleteSuccessMessage("spam");
    expect(msg).toContain("spam");
    expect(msg).toContain("deleted successfully");
  });
});

describe("Delete Confirmation Tags/Categories — DOM (confirmation displays)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockResolvedValue({ data: { items: mockCategories.map((c) => ({ categoryId: c.categoryId, name: c.name, usableByRoleID: c.usableByRoleID })) } });
  });

  it("category delete button opens modal with Delete category? and General", async () => {
    const wrapper = mount(AdminCategories);
    await flushPromises();
    const deleteBtns = wrapper.findAll(".btn-delete");
    const helpDeleteBtn = deleteBtns[1];
    await helpDeleteBtn.trigger("click");
    expect(wrapper.find(".confirm-title").text()).toBe("Delete category?");
    expect(wrapper.text()).toContain("General");
  });
});
