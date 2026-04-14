/**
 * ForumHome search — unit tests.
 * Covers:
 * - no search triggered while typing, only fires on Enter
 * - shows results and active filter label after Enter is pressed
 * - paginates results when next/prev page buttons are clicked
 * - shows no-results state when the search returns zero posts
 */
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ForumHome from "@/views/forum/ForumHome.vue";

const {
  fetchPostsMock,
  fetchPinnedPostsMock,
  searchPostsMock,
} = vi.hoisted(() => ({
  fetchPostsMock: vi.fn(),
  fetchPinnedPostsMock: vi.fn(),
  searchPostsMock: vi.fn(),
}));

vi.mock("@/api/posts", () => ({
  fetchPosts: fetchPostsMock,
  fetchPinnedPosts: fetchPinnedPostsMock,
  searchPosts: searchPostsMock,
}));

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: false,
}));

vi.mock("@/components/layout/ForumHeader.vue", () => ({
  default: { template: "<div data-test='forum-header' />" },
}));

vi.mock("@/components/user/UserCard.vue", () => ({
  default: { template: "<div data-test='user-card' />" },
}));

vi.mock("@/components/forum/CreatePostButton.vue", () => ({
  default: { template: "<button data-test='create-post'>Create</button>" },
}));

vi.mock("@/components/admin/ViewReportsButton.vue", () => ({
  default: { template: "<button data-test='view-reports'>Reports</button>" },
}));

vi.mock("@/components/admin/AdminPanelButton.vue", () => ({
  default: { template: "<button data-test='admin-panel'>Admin</button>" },
}));

vi.mock("@/components/forum/PostCard.vue", () => ({
  default: {
    props: ["post"],
    template: `
      <article data-test="post-card">
        <h3>{{ post.title }}</h3>
        <p>{{ post.authorName }}</p>
      </article>
    `,
  },
}));

describe("ForumHome search acceptance criteria", () => {
  beforeEach(() => {
    vi.clearAllMocks();

    fetchPostsMock.mockResolvedValue({
      postsByCategory: [
        {
          categoryId: 1,
          categoryName: "General",
          postCount: 8,
          posts: [
            { postId: 1, title: "Local 1", authorName: "Jane", tags: [] },
            { postId: 2, title: "Local 2", authorName: "John", tags: [] },
          ],
        },
      ],
      totalPosts: 8,
    });

    fetchPinnedPostsMock.mockResolvedValue({ posts: [] });

    searchPostsMock.mockResolvedValue({
      ok: true,
      posts: [],
      meta: {
        page: 1,
        limit: 10,
        totalPosts: 0,
        totalPages: 1,
        hasNextPage: false,
        hasPrevPage: false,
      },
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  function mountPage() {
    return mount(ForumHome, {
      global: {
        stubs: {
          RouterLink: {
            template: "<a><slot /></a>",
          },
        },
      },
    });
  }

  async function triggerSearch(wrapper, value) {
    const input = wrapper.find('input[placeholder="Search all posts..."]');
    await input.setValue(value);
    await input.trigger("keyup.enter");
    await flushPromises();
  }

  it("keeps the page unchanged while typing and only searches on Enter", async () => {
    const wrapper = mountPage();
    await flushPromises();

    expect(fetchPostsMock).toHaveBeenCalledWith({ sort: "latest" });
    expect(fetchPinnedPostsMock).toHaveBeenCalled();
    expect(wrapper.text()).toContain("8 posts");
    expect(wrapper.text()).toContain("General");

    const input = wrapper.find('input[placeholder="Search all posts..."]');
    await input.setValue("database");
    await flushPromises();

    expect(searchPostsMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("8 posts");
    expect(wrapper.text()).toContain("General");
    expect(wrapper.text()).not.toContain("Filters active:");
    expect(wrapper.text()).not.toContain("No posts match your search or filters");
  });

  it("shows results and active filter label after Enter is pressed", async () => {
    const wrapper = mountPage();
    await flushPromises();

    searchPostsMock.mockResolvedValueOnce({
      ok: true,
      posts: [
        { postId: 101, title: "Database Match 1", authorName: "Alex", tags: [] },
        { postId: 102, title: "Database Match 2", authorName: "Sam", tags: [] },
      ],
      meta: { page: 1, limit: 10, totalPosts: 12, totalPages: 2, hasNextPage: true, hasPrevPage: false },
    });

    await triggerSearch(wrapper, "database");

    expect(searchPostsMock).toHaveBeenCalledWith({
      q: "database", page: 1, limit: 10, sort: "latest", categoryIds: [],
    });
    expect(wrapper.text()).toContain("Filters active:");
    expect(wrapper.text()).toContain('Search "database"');
    expect(wrapper.text()).toContain("Search Results");
    expect(wrapper.text()).toContain("Database Match 1");
    expect(wrapper.text()).toContain("Database Match 2");
    expect(wrapper.findAll('[data-test="post-card"]')).toHaveLength(2);
    expect(wrapper.text()).toContain("12 results");
    expect(wrapper.text()).toContain("1 / 2");
  });

  it("paginates search results when next page is clicked", async () => {
    const wrapper = mountPage();
    await flushPromises();

    searchPostsMock.mockResolvedValueOnce({
      ok: true,
      posts: [{ postId: 101, title: "Database Match 1", authorName: "Alex", tags: [] }],
      meta: { page: 1, limit: 10, totalPosts: 12, totalPages: 2, hasNextPage: true, hasPrevPage: false },
    });

    await triggerSearch(wrapper, "database");

    searchPostsMock.mockResolvedValueOnce({
      ok: true,
      posts: [{ postId: 103, title: "Database Match 3", authorName: "Taylor", tags: [] }],
      meta: { page: 2, limit: 10, totalPosts: 12, totalPages: 2, hasNextPage: false, hasPrevPage: true },
    });

    const navBtns = wrapper.findAll(".page-nav-btn");
    const nextButton = navBtns[navBtns.length - 1]; // last page-nav-btn is always "next"
    expect(nextButton.element.disabled).toBe(false);
    await nextButton.trigger("click");
    await flushPromises();

    expect(searchPostsMock).toHaveBeenLastCalledWith({
      q: "database", page: 2, limit: 10, sort: "latest", categoryIds: [],
    });
    expect(wrapper.text()).toContain("2 / 2");
    expect(wrapper.text()).toContain("Database Match 3");
  });

  it("shows no-results only after Enter submits the search", async () => {
    const wrapper = mountPage();
    await flushPromises();

    const input = wrapper.find('input[placeholder="Search all posts..."]');
    await input.setValue("nohits");
    await flushPromises();

    expect(searchPostsMock).not.toHaveBeenCalled();
    expect(wrapper.text()).not.toContain("No posts match your search or filters");

    searchPostsMock.mockResolvedValueOnce({
      ok: true,
      posts: [],
      meta: {
        page: 1,
        limit: 10,
        totalPosts: 0,
        totalPages: 1,
        hasNextPage: false,
        hasPrevPage: false,
      },
    });

    await input.trigger("keyup.enter");
    await flushPromises();

    expect(searchPostsMock).toHaveBeenCalledWith({
      q: "nohits",
      page: 1,
      limit: 10,
      sort: "latest",
      categoryIds: [],
    });

    expect(wrapper.text()).toContain("No posts match your search or filters");
  });
});