import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ForumHome from "../../src/views/forum/ForumHome.vue";

const {
  fetchPostsMock,
  fetchPinnedPostsMock,
  searchPostsMock,
} = vi.hoisted(() => ({
  fetchPostsMock: vi.fn(),
  fetchPinnedPostsMock: vi.fn(),
  searchPostsMock: vi.fn(),
}));

vi.mock("../../src/api/posts", () => ({
  fetchPosts: fetchPostsMock,
  fetchPinnedPosts: fetchPinnedPostsMock,
  searchPosts: searchPostsMock,
}));

vi.mock("../../src/stores/userStore", () => ({
  isLoggedIn: false,
}));

vi.mock("../../src/components/layout/ForumHeader.vue", () => ({
  default: { template: "<div data-test='forum-header' />" },
}));

vi.mock("../../src/components/user/UserCard.vue", () => ({
  default: { template: "<div data-test='user-card' />" },
}));

vi.mock("../../src/components/forum/CreatePostButton.vue", () => ({
  default: { template: "<button data-test='create-post'>Create</button>" },
}));

vi.mock("../../src/components/admin/ViewReportsButton.vue", () => ({
  default: { template: "<button data-test='view-reports'>Reports</button>" },
}));

vi.mock("../../src/components/admin/AdminPanelButton.vue", () => ({
  default: { template: "<button data-test='admin-panel'>Admin</button>" },
}));

vi.mock("../../src/components/forum/PostCard.vue", () => ({
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
    vi.advanceTimersByTime(400);
    await flushPromises();
  }

  it("meets the search-bar acceptance criteria", async () => {
    vi.useFakeTimers();

    const wrapper = mountPage();
    await flushPromises();

    expect(fetchPostsMock).toHaveBeenCalledWith({ sort: "latest" });
    expect(fetchPinnedPostsMock).toHaveBeenCalled();

    searchPostsMock.mockResolvedValueOnce({
      ok: true,
      posts: [
        { postId: 101, title: "Database Match 1", authorName: "Alex", tags: [] },
        { postId: 102, title: "Database Match 2", authorName: "Sam", tags: [] },
      ],
      meta: {
        page: 1,
        limit: 10,
        totalPosts: 12,
        totalPages: 2,
        hasNextPage: true,
        hasPrevPage: false,
      },
    });

    await triggerSearch(wrapper, "database");

    expect(searchPostsMock).toHaveBeenCalledWith({
      q: "database",
      page: 1,
      limit: 10,
      sort: "latest",
      categoryIds: [],
    });

    expect(wrapper.text()).toContain("Search Results");
    expect(wrapper.text()).toContain("12 results");
    expect(wrapper.text()).toContain("Database Match 1");
    expect(wrapper.text()).toContain("Database Match 2");
    expect(wrapper.findAll('[data-test="post-card"]')).toHaveLength(2);

    expect(wrapper.text()).toContain("Page 1 / 2");

    const nextButton = wrapper
      .findAll("button")
      .find((button) => button.text() === "Next");

    expect(nextButton).toBeTruthy();
    expect(nextButton.element.disabled).toBe(false);

    searchPostsMock.mockResolvedValueOnce({
      ok: true,
      posts: [
        { postId: 103, title: "Database Match 3", authorName: "Taylor", tags: [] },
      ],
      meta: {
        page: 2,
        limit: 10,
        totalPosts: 12,
        totalPages: 2,
        hasNextPage: false,
        hasPrevPage: true,
      },
    });

    await nextButton.trigger("click");
    await flushPromises();

    expect(searchPostsMock).toHaveBeenLastCalledWith({
      q: "database",
      page: 2,
      limit: 10,
      sort: "latest",
      categoryIds: [],
    });

    expect(wrapper.text()).toContain("Page 2 / 2");
    expect(wrapper.text()).toContain("Database Match 3");

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

    await triggerSearch(wrapper, "nohits");

    expect(searchPostsMock).toHaveBeenLastCalledWith({
      q: "nohits",
      page: 1,
      limit: 10,
      sort: "latest",
      categoryIds: [],
    });

    expect(wrapper.text()).toContain("No posts match your search or filters");
  });
});