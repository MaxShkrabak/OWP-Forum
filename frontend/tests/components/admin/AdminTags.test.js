/**
 * AdminTags — unit tests.
 * Covers:
 * - tags table renders on load
 * - empty state shown when no tags exist
 * - search input filters the tags table by name
 * - clicking Delete opens confirmation modal with cannot-be-undone warning
 * - confirming Delete calls the API and shows a success message
 * - clicking Edit opens the form with the tag's current name prefilled
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

  it("shows 'No tags found' when the API returns no tags", async () => {
    mockClient.get.mockResolvedValue({ data: { items: [] } });
    const wrapper = mount(AdminTags);
    await flushPromises();
    expect(wrapper.find(".admin-table").exists()).toBe(false);
    expect(wrapper.text()).toContain("No tags found");
  });

  it("search input filters the table to only matching tag names", async () => {
    const wrapper = mount(AdminTags);
    await flushPromises();
    expect(wrapper.findAll("tbody tr").length).toBe(2);

    await wrapper.find("input").setValue("help");
    expect(wrapper.findAll("tbody tr").length).toBe(1);
    expect(wrapper.find(".admin-name").text()).toBe("help");
  });

  it("confirming Delete calls the delete API and shows a success message", async () => {
    mockClient.delete.mockResolvedValue({ data: {} });
    const wrapper = mount(AdminTags);
    await flushPromises();

    await wrapper.findAll(".btn-action.danger")[0].trigger("click");
    await wrapper.find(".btn-confirm").trigger("click");
    await flushPromises();

    expect(mockClient.delete).toHaveBeenCalledTimes(1);
    expect(wrapper.find(".confirm-title").text()).toBe("Tag deleted");
  });

  it("Edit button opens the form with the selected tag's name prefilled", async () => {
    const wrapper = mount(AdminTags);
    await flushPromises();

    await wrapper.findAll(".btn-action:not(.danger)")[0].trigger("click");

    expect(wrapper.find(".confirm-title").text()).toBe("Edit Tag");
    expect(wrapper.find("input.field-input").element.value).toBe("help");
  });
});
