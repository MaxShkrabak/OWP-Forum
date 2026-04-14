/**
 * ForumHome sorting — unit tests.
 * Covers:
 * - defaults sort to 'latest' on mount
 * - renders all sort options in the dropdown
 * - updates sort ref when a different option is selected
 */
import { describe, it, expect, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ForumHome from "@/views/forum/ForumHome.vue";

vi.mock("@/api/posts", () => ({
  fetchPosts: vi.fn(async () => ({ postsByCategory: [], totalPosts: 0 })),
  fetchPinnedPosts: vi.fn(async () => ({ posts: [] })),
  searchPosts: vi.fn(async () => ({ ok: true, posts: [], meta: {} })),
}));

vi.mock("@/api/auth", () => ({
  checkAuth: vi.fn(async () => ({ data: null })),
}));

vi.mock("vue-router", () => ({
  useRoute: () => ({ params: {}, query: {}, meta: {} }),
  useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
  RouterLink: { name: "RouterLink", props: ["to"], template: `<a><slot /></a>` },
}));

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: { value: false },
  userRoleId: { value: 1 },
  userRole: "user",
  uid: { value: 0 },
  fullName: "",
  userAvatar: "pfp-0.png",
}));

describe("ForumHome.vue", () => {
  const createWrapper = () =>
    mount(ForumHome, {
      global: {
        stubs: {
          Teleport: true,
          RouterLink: true,
          UserRole: true,
          ForumHeader: true,
          CreatePostButton: true,
          ViewReportsButton: true,
          AdminPanelButton: true,
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

  it("updates sort when a different option is selected", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const sortSelect = findSortSelect(wrapper);
    expect(sortSelect).not.toBeNull();

    await sortSelect.setValue("upvotes");
    expect(wrapper.vm.sort).toBe("upvotes");

    await sortSelect.setValue("comments");
    expect(wrapper.vm.sort).toBe("comments");
  });
});