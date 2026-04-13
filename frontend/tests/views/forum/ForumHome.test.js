/**
 * ForumHome — homepage unit tests.
 * Covers:
 * - renders pinned posts at the top of their category
 * - renders category sections with posts on initial load
 * - category checkbox filter shows only selected categories
 * - shows total post count
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref } from "vue";
import ForumHome from "@/views/forum/ForumHome.vue";

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: ref(false),
}));

vi.mock("vue-router", () => ({
  RouterLink: { template: "<a><slot /></a>" },
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ params: {}, query: {} }),
}));

const mockFetchPosts = vi.fn();
const mockFetchPinnedPosts = vi.fn();
const mockSearchPosts = vi.fn();

vi.mock("@/api/posts", () => ({
  fetchPosts: (...args) => mockFetchPosts(...args),
  fetchPinnedPosts: (...args) => mockFetchPinnedPosts(...args),
  searchPosts: (...args) => mockSearchPosts(...args),
}));

const stubs = {
  ForumHeader: { template: "<div />" },
  UserCard: { template: "<div />" },
  AdminPanelButton: { template: "<div />" },
  CreatePostButton: { template: "<div />" },
  ViewReportsButton: { template: "<div />" },
  PostCard: {
    props: ["post"],
    template: `<div class="post-card-stub">{{ post.title }}</div>`,
  },
};

const sampleHomepageData = {
  postsByCategory: [
    {
      categoryId: 1,
      categoryName: "General",
      postCount: 3,
      posts: [
        { postId: 1, title: "Regular post A" },
        { postId: 2, title: "Regular post B" },
        { postId: 3, title: "Regular post C" },
      ],
    },
    {
      categoryId: 2,
      categoryName: "Help",
      postCount: 1,
      posts: [{ postId: 4, title: "Help post" }],
    },
  ],
  totalPosts: 4,
};

const samplePinnedData = {
  posts: [
    { postId: 99, title: "Pinned announcement", categoryId: 1 },
  ],
};

describe("ForumHome.vue — homepage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchPosts.mockResolvedValue(sampleHomepageData);
    mockFetchPinnedPosts.mockResolvedValue(samplePinnedData);
  });

  it("renders category sections with posts on load", async () => {
    const wrapper = mount(ForumHome, { global: { stubs } });
    await flushPromises();

    const categories = wrapper.findAll(".category-group");
    expect(categories.length).toBe(2);
    expect(wrapper.text()).toContain("General");
    expect(wrapper.text()).toContain("Help");
  });

  it("renders pinned posts at the top of their category", async () => {
    const wrapper = mount(ForumHome, { global: { stubs } });
    await flushPromises();

    const generalGroup = wrapper.findAll(".category-group")[0];
    const titles = generalGroup
      .findAll(".post-card-stub")
      .map((n) => n.text());

    expect(titles[0]).toBe("Pinned announcement");
  });

  it("shows total post count", async () => {
    const wrapper = mount(ForumHome, { global: { stubs } });
    await flushPromises();

    expect(wrapper.text()).toContain("4 posts");
  });

  it("filters to only selected categories when a checkbox is checked", async () => {
    const wrapper = mount(ForumHome, { global: { stubs } });
    await flushPromises();

    const checkboxes = wrapper.findAll("input[type='checkbox']");
    expect(checkboxes.length).toBe(2);

    // Check the "Help" category (second checkbox)
    await checkboxes[1].setValue(true);

    const categories = wrapper.findAll(".category-group");
    expect(categories.length).toBe(1);
    expect(wrapper.text()).toContain("Help");
  });
});
