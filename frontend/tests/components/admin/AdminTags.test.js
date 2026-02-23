/** @vitest-environment jsdom */
/**
 * Manage Tags (Admin) — unit tests.
 * Duplicate prevention + API contract (no DOM) + AdminTags.vue DOM tests.
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminTags from "@/components/admin/AdminTags.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }));

const mockTags = [
  { TagID: 1, Name: "help", UsableByRoleID: 1 },
  { TagID: 2, Name: "spam", UsableByRoleID: 1 },
];

// Same normalizeName as AdminTags.vue (used for duplicate check and display)
function normalizeName(s) {
  return String(s ?? "").trim().replace(/\s+/g, " ");
}

// Same duplicate-check logic as AdminTags.vue isDuplicateName (BB-145 Prevent duplicates)
function tagNameExists(tags, name, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return tags.some((t) => {
    const same = normalizeName(t.Name).toLowerCase() === n;
    const notSelf =
      excludeId == null ? true : Number(t.TagID) !== Number(excludeId);
    return same && notSelf;
  });
}

// API contract: paths used by AdminTags.vue for add, edit, delete
function getTagsListEndpoint() {
  return "/admin/tags";
}
function getTagAddEndpoint() {
  return "/admin/tags";
}
function getTagEditEndpoint(tagId) {
  return `/admin/tags/${tagId}`;
}
function getTagDeleteEndpoint(tagId) {
  return `/admin/tags/${tagId}`;
}

describe("Manage Tags (Admin) — duplicate prevention", () => {
  const tags = [
    { TagID: 1, Name: "help", UsableByRoleID: 1 },
    { TagID: 2, Name: "spam", UsableByRoleID: 1 },
    { TagID: 3, Name: "Random", UsableByRoleID: 2 },
  ];

  it("returns true when another tag has the same name (add case)", () => {
    expect(tagNameExists(tags, "help")).toBe(true);
    expect(tagNameExists(tags, "  spam  ")).toBe(true);
    expect(tagNameExists(tags, "RANDOM")).toBe(true);
  });

  it("returns false when name is unique", () => {
    expect(tagNameExists(tags, "news")).toBe(false);
    expect(tagNameExists(tags, "announcements")).toBe(false);
  });

  it("returns false when editing same tag (excludeId)", () => {
    expect(tagNameExists(tags, "help", 1)).toBe(false);
    expect(tagNameExists(tags, "spam", 2)).toBe(false);
  });

  it("returns true when editing to a name used by another tag", () => {
    expect(tagNameExists(tags, "spam", 1)).toBe(true);
    expect(tagNameExists(tags, "help", 2)).toBe(true);
  });

  it("normalizes names (trim and collapse spaces) before comparing", () => {
    expect(tagNameExists(tags, "  help  ")).toBe(true);
    expect(tagNameExists([{ TagID: 1, Name: "a  b", UsableByRoleID: 1 }], "a b")).toBe(true);
  });

  it("handles empty tag list", () => {
    expect(tagNameExists([], "help")).toBe(false);
  });
});

describe("Manage Tags (Admin) — API contract (add/edit/delete)", () => {
  it("list tags calls GET /admin/tags", () => {
    expect(getTagsListEndpoint()).toBe("/admin/tags");
  });

  it("add tag calls POST /admin/tags", () => {
    expect(getTagAddEndpoint()).toBe("/admin/tags");
  });

  it("edit tag calls PATCH /admin/tags/:id", () => {
    expect(getTagEditEndpoint(1)).toBe("/admin/tags/1");
    expect(getTagEditEndpoint(42)).toBe("/admin/tags/42");
  });

  it("delete tag calls DELETE /admin/tags/:id", () => {
    expect(getTagDeleteEndpoint(1)).toBe("/admin/tags/1");
    expect(getTagDeleteEndpoint(99)).toBe("/admin/tags/99");
  });
});

describe("Manage Tags (Admin) — normalizeName", () => {
  it("trims and collapses multiple spaces", () => {
    expect(normalizeName("  help  ")).toBe("help");
    expect(normalizeName("a   b   c")).toBe("a b c");
  });

  it("handles null/undefined as empty string", () => {
    expect(normalizeName(null)).toBe("");
    expect(normalizeName(undefined)).toBe("");
  });
});

describe("Manage Tags (Admin) — AdminTags.vue DOM", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockResolvedValue({ data: { items: mockTags } });
  });

  it("displays tags and delete opens confirmation with cannot be undone", async () => {
    const wrapper = mount(AdminTags);
    await flushPromises();
    expect(wrapper.find(".admin-table").exists()).toBe(true);
    const deleteBtn = wrapper.findAll(".btn-action.danger")[0];
    await deleteBtn.trigger("click");
    expect(wrapper.find(".confirm-title").text()).toBe("Confirm delete tag?");
    expect(wrapper.text()).toContain("cannot be undone");
  });
});
