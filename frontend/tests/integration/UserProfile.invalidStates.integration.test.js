import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import UserProfile from "@/views/forum/UserProfile.vue";
import { fetchUser } from "@/api/users";
import { fetchPosts, fetchLikedPosts } from "@/api/posts";

vi.mock("@/api/users", () => ({
  fetchUser: vi.fn(),
  fetchUserStats: vi.fn(() =>
    Promise.resolve({ ok: true, postCount: 0, commentCount: 0, likedCount: 0 }),
  ),
}));

vi.mock("@/api/posts", () => ({
  fetchPosts: vi.fn(),
  fetchLikedPosts: vi.fn(),
  votePost: vi.fn(),
  togglePostPin: vi.fn(),
}));

const { mockRoute, mockRouterPush, mockRouterBack, mockUid, mockIsLoggedIn } =
  vi.hoisted(() => ({
    mockRoute: vi.fn(),
    mockRouterPush: vi.fn(),
    mockRouterBack: vi.fn(),
    mockUid: { value: 0 },
    mockIsLoggedIn: { value: false },
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

vi.mock("@/stores/userStore", () => ({
  uid: mockUid,
  isLoggedIn: mockIsLoggedIn,
  fullName: { value: "" },
  userAvatar: { value: "pfp-0.png" },
  userRole: { value: "user" },
  userRoleId: { value: 1 },
}));

vi.mock("@/utils/pagination", () => ({
  getPaginationRange: vi.fn(() => [1]),
}));

vi.mock("@/utils/time", () => ({
  timeAgo: () => "1h ago",
}));

function makeWrapper() {
  return mount(UserProfile, {
    global: {
      stubs: {
        ForumHeader: true,
        UserSettings: true,
        pfpModal: true,
        ReportingModal: true,
        UserRole: true,
      },
    },
  });
}

describe("UserProfile — invalid states integration", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, "error").mockImplementation(() => {});
    localStorage.clear();
    mockUid.value = 0;
    mockIsLoggedIn.value = false;

    fetchPosts.mockResolvedValue({ posts: [], meta: { totalPages: 1 } });
    fetchLikedPosts.mockResolvedValue({ posts: [], meta: { totalPages: 1 } });
  });

  it("shows guest empty-state when no route query id is present", async () => {
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

  it("shows user-not-found empty-state when fetchUser rejects", async () => {
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
