/** @vitest-environment jsdom */
import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import CategoryPage from "@/views/forum/CategoryPosts.vue";
import { fetchPosts, getTags } from "@/api/posts";
import { useRoute } from "vue-router";

vi.mock("vue-router", () => ({
  useRoute: vi.fn(),
  useRouter: vi.fn(() => ({ back: vi.fn() })),
}));

vi.mock("@/api/posts", () => ({
  fetchPosts: vi.fn(),
  getTags: vi.fn(),
}));

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: true,
  isBanned: false,
}));

describe("CategoryPage.vue", () => {
  let wrapper;

  const mockTags = [
    { tagId: 1, name: "Help" },
    { tagId: 2, name: "Research" },
  ];

  const postA = { postId: 1, title: "Post A", tags: ["Help"] };
  const postB = { postId: 2, title: "Post B", tags: ["Research"] };
  const postC = { postId: 3, title: "Post C", tags: ["Help", "Research"] };

  beforeEach(() => {
    vi.clearAllMocks();

    useRoute.mockReturnValue({ params: { categoryId: "5" } });
    getTags.mockResolvedValue(mockTags);
  });

  const createWrapper = () => {
    return mount(CategoryPage, {
      global: {
        stubs: {
          ForumHeader: true,
          PostCard: true,
          UserCard: true,
          CreatePostButton: true,
          ViewReportsButton: true,
        },
      },
    });
  };

  it("filters posts down to only Post C when both Help and Research tags are selected", async () => {
    fetchPosts.mockImplementation(async (args) => {
      let filteredPosts = [postA, postB, postC];

      if (args.tags && args.tags.length > 0) {
        filteredPosts = filteredPosts.filter((post) =>
          args.tags.every((selectedTag) => post.tags.includes(selectedTag)),
        );
      }

      return {
        posts: filteredPosts,
        categoryName: "General",
        meta: { totalPosts: filteredPosts.length, totalPages: 1 },
      };
    });

    wrapper = createWrapper();
    await flushPromises();

    let postCards = wrapper.findAllComponents({ name: "PostCard" });
    expect(postCards.length).toBe(3);

    const helpBtn = wrapper
      .findAll(".tag-pill")
      .find((b) => b.text().includes("Help"));
    await helpBtn.trigger("click");
    await flushPromises();

    postCards = wrapper.findAllComponents({ name: "PostCard" });
    expect(postCards.length).toBe(2);
    expect(fetchPosts).toHaveBeenLastCalledWith(
      expect.objectContaining({ tags: ["Help"] }),
    );

    const researchBtn = wrapper
      .findAll(".tag-pill")
      .find((b) => b.text().includes("Research"));
    await researchBtn.trigger("click");
    await flushPromises();

    postCards = wrapper.findAllComponents({ name: "PostCard" });
    expect(postCards.length).toBe(1);
    expect(postCards[0].props("post").postId).toBe(3);

    expect(fetchPosts).toHaveBeenLastCalledWith(
      expect.objectContaining({ tags: ["Help", "Research"] }),
    );
  });
});