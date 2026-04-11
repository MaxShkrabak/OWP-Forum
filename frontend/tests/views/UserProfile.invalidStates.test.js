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

const { mockRoute, mockRouterPush, mockRouterBack, mockUid, mockIsLoggedIn } = vi.hoisted(() => ({
  mockRoute: vi.fn(),
  mockRouterPush: vi.fn(),
  mockRouterBack: vi.fn(),
  mockUid: { value: 0 },
  mockIsLoggedIn: { value: false },
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
  isLoggedIn: mockIsLoggedIn,
}));

vi.mock("@/utils/pagination", () => ({
  getPaginationRange: vi.fn(() => [1]),
}));

function makeWrapper() {
  return mount(UserProfile, {
    global: {
      stubs: {
        ForumHeader: true,
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

describe("UserProfile.vue state handling", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, "error").mockImplementation(() => {});
    localStorage.clear();
    mockUid.value = 0;

    fetchPosts.mockResolvedValue({
      posts: [],
      meta: { totalPages: 1 },
    });
    fetchLikedPosts.mockResolvedValue({
      posts: [],
      meta: { totalPages: 1 },
    });
  });

  it("shows the guest empty-state when no route query id is present", async () => {
    mockRoute.mockReturnValue({
      query: {},
      params: {},
      meta: {},
      path: "/profile",
      fullPath: "/profile",
    });

    const wrapper = makeWrapper();
    await flushPromises();

    expect(fetchUser).not.toHaveBeenCalled();
    expect(wrapper.find(".empty-state").text()).toContain(
      "Guest users do not have profiles. Please sign in to view your profile.",
    );
  });

  it("shows the user-does-not-exist state when fetchUser fails for a nonexistent id", async () => {
    mockRoute.mockReturnValue({
      query: { id: "999" },
      params: {},
      meta: {},
      path: "/profile",
      fullPath: "/profile?id=999",
    });
    fetchUser.mockRejectedValue(new Error("User not found"));

    const wrapper = makeWrapper();
    await flushPromises();

    expect(fetchUser).toHaveBeenCalledWith("999");
    expect(wrapper.find(".empty-state").text()).toContain("User does not exist.");
  });
});
