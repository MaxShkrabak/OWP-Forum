/**
 * @vitest-environment jsdom
 */

import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import UserProfile from "@/views/forum/UserProfile.vue";
import { fetchUser } from "@/api/users";
import { fetchPosts, fetchLikedPosts } from "@/api/posts.js";

vi.mock("@/api/users", () => ({
  fetchUser: vi.fn(),
}));

vi.mock("@/api/posts.js", () => ({
  fetchPosts: vi.fn(),
  fetchLikedPosts: vi.fn(),
}));

const { mockRoute, mockRouterPush, mockRouterBack, mockUid } = vi.hoisted(() => ({
  mockRoute: vi.fn(),
  mockRouterPush: vi.fn(),
  mockRouterBack: vi.fn(),
  mockUid: { value: 1 },
}));

vi.mock("vue-router", () => ({
  useRoute: () => mockRoute(),
  useRouter: () => ({
    push: mockRouterPush,
    back: mockRouterBack,
  }),
}));

vi.mock("@/stores/userStore", () => ({
  uid: mockUid,
  isLoggedIn: { value: true },
}));

vi.mock("@/utils/pagination", () => ({
  getPaginationRange: vi.fn(() => [1]),
}));

function makeWrapper() {
  return mount(UserProfile, {
    global: {
      stubs: {
        ForumHeader: true,
        pfpModal: true,
        UserSettings: true,
        PostCard: {
          props: ["post"],
          template: '<div class="post-card">{{ post.title }}</div>',
        },
        UserCard: {
          props: ["newFullName", "avatar", "newRole", "userId", "isCurrUser"],
          template: `
            <div class="user-card-stub">
              <span class="user-name">{{ newFullName }}</span>
              <span class="user-role">{{ newRole }}</span>
              <img class="user-avatar" :src="avatar" />
            </div>
          `,
        },
      },
    },
  });
}

describe("UserProfile.vue", () => {
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

  it("renders profile card with fetched user name and avatar", async () => {
    fetchUser.mockResolvedValue({
      ok: true,
      user: {
        avatar: "test-avatar.png",
        firstName: "Test",
        lastName: "User",
        roleName: "User",
      },
    });

    fetchPosts.mockResolvedValue({
      posts: [{ postId: 101, title: "My first post" }],
      meta: { totalPages: 1 },
    });

    fetchLikedPosts.mockResolvedValue({
      posts: [],
      meta: { totalPages: 1 },
    });

    const wrapper = makeWrapper();
    await flushPromises();

    expect(fetchUser).toHaveBeenCalledWith("2");
    expect(fetchPosts).toHaveBeenCalled();
    expect(wrapper.find(".user-name").text()).toBe("Test User");
    expect(wrapper.find(".user-avatar").attributes("src")).toBe("test-avatar.png");
    expect(wrapper.text()).toContain("My first post");
  });

  it("loads initial posts tab and switching to liked posts calls liked-posts API", async () => {
    mockUid.value = 2;

    fetchUser.mockResolvedValue({
      ok: true,
      user: {
        avatar: "test-avatar.png",
        firstName: "Test",
        lastName: "User",
        roleName: "User",
      },
    });

    fetchPosts.mockResolvedValue({
      posts: [{ postId: 101, title: "My first post" }],
      meta: { totalPages: 1 },
    });

    fetchLikedPosts.mockResolvedValue({
      posts: [{ postId: 202, title: "Liked post" }],
      meta: { totalPages: 1 },
    });

    const wrapper = makeWrapper();
    await flushPromises();

    expect(fetchUser).not.toHaveBeenCalled();
    expect(fetchPosts).toHaveBeenCalled();
    expect(fetchLikedPosts).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("My first post");

    const buttons = wrapper.findAll("button");
    const likedTab = buttons.find((btn) => btn.text().includes("Liked"));

    expect(likedTab).toBeTruthy();

    await likedTab.trigger("click");
    await flushPromises();

    expect(fetchLikedPosts).toHaveBeenCalled();
    expect(wrapper.text()).toContain("Liked post");
  });
});