/**
 * UserProfile — integration tests.
 * Covers:
 * - renders UserCard with fetched user data and PostCard with fetched posts
 * - switching to the liked tab calls the liked-posts API and renders those posts
 */
import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";
import UserProfile from "@/views/forum/UserProfile.vue";
import { fetchUser } from "@/api/users";
import { fetchPosts, fetchLikedPosts } from "@/api/posts";
import { uid as mockUid } from "@/stores/userStore";

vi.mock("@/api/users", () => ({
  fetchUser: vi.fn(),
  fetchUserStats: vi.fn(() =>
    Promise.resolve({
      ok: true,
      stats: { postCount: 5, voteScore: 0, commentCount: 10 },
    }),
  ),
}));

vi.mock("@/api/posts", () => ({
  fetchPosts: vi.fn(),
  fetchLikedPosts: vi.fn(),
  votePost: vi.fn(),
  togglePostPin: vi.fn(),
}));

const { mockRoute, mockRouterPush, mockRouterBack } = vi.hoisted(() => ({
  mockRoute: vi.fn(),
  mockRouterPush: vi.fn(),
  mockRouterBack: vi.fn(),
}));

vi.mock("vue-router", () => ({
  useRoute: () => mockRoute(),
  useRouter: () => ({ push: mockRouterPush, back: mockRouterBack }),
  RouterLink: {
    name: "RouterLink",
    props: ["to"],
    template: `<a :href="typeof to === 'string' ? to : '#'"><slot /></a>`,
  },
}));

vi.mock("@/stores/userStore", async () => {
  const { ref } = await import("vue");
  return {
    uid: ref(1),
    isLoggedIn: ref(true),
    fullName: ref("Test User"),
    userAvatar: ref("pfp-0.png"),
    userRole: ref("user"),
    userRoleId: ref(1),
  };
});


vi.mock("@/utils/pagination", () => ({
  getPaginationRange: vi.fn(() => [1]),
}));

vi.mock("@/utils/time", () => ({
  timeAgo: () => "1h ago",
}));

function makePost(overrides = {}) {
  return {
    postId: 101,
    title: "Jane's first post",
    authorName: "Jane Smith",
    authorRole: "Moderator",
    authorAvatar: "pfp-1.png",
    createdAt: "2026-01-01T00:00:00Z",
    tags: [],
    commentCount: 0,
    TotalScore: 0,
    myVote: 0,
    ...overrides,
  };
}

function makeWrapper() {
  return mount(UserProfile, {
    global: {
      stubs: {
        ForumHeader: true,
        UserSettings: true,
        pfpModal: true,
        ReportingModal: true,
        UserRole: true,
        RouterLink: { template: "<a><slot /></a>" },
      },
    },
  });
}

describe("UserProfile — integration", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();

    mockRoute.mockReturnValue({
      query: { id: "2" },
      params: {},
      meta: {},
      path: "/profile",
      fullPath: "/profile?id=2",
    });

    mockUid.value = 1;
  });

  it("renders UserCard with fetched user data and real PostCard with fetched posts", async () => {
    fetchUser.mockResolvedValue({
      ok: true,
      user: {
        avatar: "pfp-1.png",
        firstName: "Jane",
        lastName: "Smith",
        roleName: "Moderator",
      },
    });

    fetchPosts.mockResolvedValue({
      posts: [makePost()],
      meta: { totalPages: 1 },
    });

    fetchLikedPosts.mockResolvedValue({ posts: [], meta: { totalPages: 1 } });

    const wrapper = makeWrapper();
    await flushPromises();

    expect(fetchUser).toHaveBeenCalledWith("2");
    expect(fetchPosts).toHaveBeenCalled();

    // UserCard renders the name from fetchUser response
    expect(wrapper.text()).toContain("Jane Smith");

    // PostCard renders the post title from fetchPosts response
    expect(wrapper.text()).toContain("Jane's first post");
  });

  it("switching to liked tab calls liked-posts API and renders liked posts through the PostCard", async () => {
    mockUid.value = 2;

    fetchPosts.mockResolvedValue({
      posts: [makePost({ postId: 101, title: "My post" })],
      meta: { totalPages: 1 },
    });

    fetchLikedPosts.mockResolvedValue({
      posts: [makePost({ postId: 202, title: "A post I liked", authorName: "Other User" })],
      meta: { totalPages: 1 },
    });

    const wrapper = makeWrapper();
    await flushPromises();

    expect(wrapper.text()).toContain("My post");
    expect(fetchLikedPosts).not.toHaveBeenCalled();

    const likedTab = wrapper.findAll("button").find((b) => b.text().includes("Liked"));
    expect(likedTab).toBeTruthy();
    await likedTab.trigger("click");
    await flushPromises();

    expect(fetchLikedPosts).toHaveBeenCalled();
    expect(wrapper.text()).toContain("A post I liked");
    expect(wrapper.text()).not.toContain("My post");
  });
});
