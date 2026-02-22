/** @vitest-environment jsdom */
import { describe, it, expect, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ForumHome from "@/views/forum/ForumHome.vue";

// Mock posts API so mount doesn't depend on backend
vi.mock("@/api/posts.js", () => ({
  fetchPosts: vi.fn(async () => ({
    postsByCategory: [],
    totalPosts: 0,
  })),
}));

vi.mock("@/api/auth.js", () => ({
  checkAuth: vi.fn(async () => ({
    data: null
  })),
}));

describe("ForumHome.vue", () => {
  const createWrapper = () =>
    mount(ForumHome, {
      global: {
        stubs: {
          Teleport: true,
          RouterLink: true,
        },
      },
    });

  const findSortSelect = (wrapper) => {
    const selects = wrapper.findAll("select");
    for (const sel of selects) {
      const values = sel.findAll("option").map((o) => o.element.value);
      if (values.includes("latest") && values.includes("upvotes") && values.includes("comments")) {
        return sel;
      }
    }
    return null;
  };

  it("defaults sort to 'latest'", async () => {
    const wrapper = createWrapper();
    await flushPromises();
    expect(wrapper.vm.sort).toBe("latest");
  });

  it("renders sorting options correctly", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const sortSelect = findSortSelect(wrapper);
    expect(sortSelect).not.toBeNull();

    const optionTexts = sortSelect.findAll("option").map((o) => o.text());
    expect(optionTexts).toContain("Most Upvotes");
    expect(optionTexts).toContain("Most Comments");
  });

  it("changes sort to 'upvotes' when selected", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const sortSelect = findSortSelect(wrapper);
    expect(sortSelect).not.toBeNull();

    await sortSelect.setValue("upvotes");
    expect(wrapper.vm.sort).toBe("upvotes");
  });

  it("changes sort to 'comments' when selected", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const sortSelect = findSortSelect(wrapper);
    expect(sortSelect).not.toBeNull();

    await sortSelect.setValue("comments");
    expect(wrapper.vm.sort).toBe("comments");
  });
});