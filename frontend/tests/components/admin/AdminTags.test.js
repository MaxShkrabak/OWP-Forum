/**
 * AdminTags — unit tests.
 * Covers:
 * - tags table renders on load
 * - clicking Delete opens confirmation modal with cannot-be-undone warning
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

describe("AdminTags.vue", () => {
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
