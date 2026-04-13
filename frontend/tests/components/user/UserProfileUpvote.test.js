/** @vitest-environment jsdom */
/**
 * UserProfile — liked posts tab — unit tests.
 * Covers:
 * - displays liked posts when the liked tab is opened
 * - sort-triggered refetch reflects removed posts (fewer results rendered)
 * - pagination on the liked posts tab (next page, no preloading)
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref, reactive } from "vue";
import UserProfile from "@/views/forum/UserProfile.vue";

vi.mock("@/stores/userStore", () => ({
  uid: ref(5),
}));

const mockRoute = reactive({ query: { id: "5" } });
const mockBack = vi.fn();

vi.mock("vue-router", () => ({
  useRoute: () => mockRoute,
  useRouter: () => ({ back: mockBack }),
}));

vi.mock("@/api/users", () => ({
  fetchUser: vi.fn(),
}));

const mockFetchPosts = vi.fn();
const mockFetchLikedPosts = vi.fn();

vi.mock("@/api/posts.js", () => ({
  fetchPosts: (...args) => mockFetchPosts(...args),
  fetchLikedPosts: (...args) => mockFetchLikedPosts(...args),
}));

const stubs = {
  ForumHeader: { template: "<div />" },
  pfpModal: { template: "<div />" },
  UserSettings: { template: "<div />" },
  UserCard: { template: "<div />" },
  PostCard: {
    props: ["post"],
    template: `<div class="post-card-stub">{{ post.title }}</div>`,
  },
};

function findButtonContains(wrapper, text) {
  const btn = wrapper.findAll("button").find((b) => b.text().includes(text));
  if (!btn) throw new Error(`Button not found containing: "${text}"`);
  return btn;
}

function getSortSelect(wrapper) {
  const selects = wrapper.findAll("select.sort-select");
  if (selects.length < 2) throw new Error("Sort dropdown not found");
  return selects[1]; // second select is Sort
}

function renderedPostTitles(wrapper) {
  return wrapper.findAll(".post-card-stub").map((n) => n.text().trim());
}

describe("UserProfile — liked posts tab", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockBack.mockClear();

    localStorage.setItem("category_limit", "5");
    localStorage.setItem("category_sort", "latest");

    mockFetchPosts.mockResolvedValue({
      posts: [],
      meta: { totalPages: 1 },
    });

    // Stage machine for liked posts
    mockFetchLikedPosts.__stage = "initial";
    mockFetchLikedPosts.mockImplementation(async ({ page, sort, limit, userId }) => {
      // enforce "only X per page" contract
      if (limit !== 5) throw new Error(`Expected limit=5, got ${limit}`);
      if (String(userId) !== "5") throw new Error(`Expected userId=5, got ${userId}`);

      // 1 First open liked tab => 2 liked posts
      if (mockFetchLikedPosts.__stage === "initial" && page === 1 && sort === "latest") {
        mockFetchLikedPosts.__stage = "after-liked";
        return {
          ok: true,
          posts: [
            { PostID: 10, postId: 10, title: "Upvoted A" },
            { PostID: 11, postId: 11, title: "Upvoted B" },
          ],
          meta: { totalPages: 1 },
        };
      }

      // 2 Sort changed to oldest => refetch returns only B (simulates a post being removed)
      if (mockFetchLikedPosts.__stage === "after-liked" && page === 1 && sort === "oldest") {
        mockFetchLikedPosts.__stage = "pagination";
        return {
          ok: true,
          posts: [{ PostID: 11, postId: 11, title: "Upvoted B" }],
          meta: { totalPages: 1 },
        };
      }

      // 3 Pagination scenario => sort back to latest returns 5 posts + totalPages=3
      if (mockFetchLikedPosts.__stage === "pagination" && page === 1 && sort === "latest") {
        return {
          ok: true,
          posts: Array.from({ length: 5 }, (_, i) => ({
            PostID: i + 1,
            postId: i + 1,
            title: `P${i + 1}`,
          })),
          meta: { totalPages: 3 },
        };
      }

      // Page 2 only when navigating
      if (mockFetchLikedPosts.__stage === "pagination" && page === 2 && sort === "latest") {
        return {
          ok: true,
          posts: Array.from({ length: 5 }, (_, i) => ({
            PostID: i + 6,
            postId: i + 6,
            title: `P${i + 6}`,
          })),
          meta: { totalPages: 3 },
        };
      }

      return { ok: true, posts: [], meta: { totalPages: 1 } };
    });
  });

  it("covers display, removal-on-unvote, and pagination limit", async () => {
    const wrapper = mount(UserProfile, { global: { stubs } });
    await flushPromises();

    await findButtonContains(wrapper, "Liked Posts").trigger("click");
    await flushPromises();

    // 1 Shows both liked posts
    expect(renderedPostTitles(wrapper)).toEqual(["Upvoted A", "Upvoted B"]);

    // 2 Sort to oldest triggers refetch; refetch returns fewer posts (A gone)
    const sortSelect = getSortSelect(wrapper);
    await sortSelect.setValue("oldest");
    await flushPromises();

    expect(renderedPostTitles(wrapper)).toEqual(["Upvoted B"]);

    // 3 Back to latest triggers pagination dataset (5 posts on page 1)
    await sortSelect.setValue("latest");
    await flushPromises();

    expect(renderedPostTitles(wrapper)).toEqual(["P1", "P2", "P3", "P4", "P5"]);

    const nextBtn = wrapper.get("nav .page-nav-btn:last-child");
    await nextBtn.trigger("click");
    await flushPromises();

    // Now page 2 titles (still only 5)
    expect(renderedPostTitles(wrapper)).toEqual(["P6", "P7", "P8", "P9", "P10"]);

    // Ensure no preloading: only page 2 fetched when we navigated
    const pagesCalled = mockFetchLikedPosts.mock.calls.map((c) => c[0]?.page);
    expect(pagesCalled).toEqual([1, 1, 1, 2]);
  });
});