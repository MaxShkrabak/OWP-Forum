/**
 * PostCard — unit tests for pin/unpin behavior.
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import PostCard from "@/components/forum/PostCard.vue";

const mocks = vi.hoisted(() => ({
  mockVotePost: vi.fn(),
  mockTogglePostPin: vi.fn(),
  mockPush: vi.fn(),
  store: {
    isLoggedIn: null,
    userRole: null,
  },
}));

vi.mock("@/api/posts", () => ({
  votePost: (...args) => mocks.mockVotePost(...args),
  togglePostPin: (...args) => mocks.mockTogglePostPin(...args),
}));

vi.mock("@/stores/userStore", async () => {
  const { ref } = await import("vue");

  mocks.store.isLoggedIn = ref(true);
  mocks.store.userRole = ref("admin");

  return {
    isLoggedIn: mocks.store.isLoggedIn,
    userRole: mocks.store.userRole,
  };
});

vi.mock("vue-router", () => ({
  RouterLink: {
    name: "RouterLink",
    props: ["to"],
    template: `<a :href="to"><slot /></a>`,
  },
  useRouter: () => ({
    push: mocks.mockPush,
  }),
}));

vi.mock("@/components/user/UserRole.vue", () => ({
  default: {
    name: "UserRole",
    props: ["role"],
    template: `<span class="user-role">{{ role }}</span>`,
  },
}));

vi.mock("@/components/user/ReportingModal.vue", () => ({
  default: {
    name: "ReportingModal",
    template: `<div class="reporting-modal-stub"></div>`,
  },
}));

vi.mock("@/utils/timeAgo", () => ({
  timeAgo: () => "1h ago",
}));

function makePost(overrides = {}) {
  return {
    PostID: 101,
    postId: 101,
    title: "Pinned post",
    createdAt: "2026-03-19T10:00:00Z",
    authorId: 7,
    authorName: "Jane Doe",
    authorRole: "Admin",
    authorAvatar: "default.png",
    tags: ["Official"],
    commentCount: 4,
    TotalScore: 12,
    myVote: 0,
    categoryName: "General",
    isPinned: false,
    ...overrides,
  };
}

describe("PostCard — pin/unpin", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.store.isLoggedIn.value = true;
    mocks.store.userRole.value = "admin";
  });

  it("shows pin icon for admin on posts from any category", () => {
    const wrapper = mount(PostCard, {
      props: {
        post: makePost({ categoryName: "Help" }),
      },
    });

    expect(wrapper.find(".pin-btn").exists()).toBe(true);
    expect(wrapper.find(".pin-icon").exists()).toBe(true);
  });

  it("shows pin icon for admin even when category is not announcement", () => {
    const wrapper = mount(PostCard, {
      props: {
        post: makePost({ categoryName: "Research" }),
      },
    });

    expect(wrapper.find(".pin-btn").exists()).toBe(true);
  });

  it("hides pin icon for non-admin users", () => {
    mocks.store.userRole.value = "user";

    const wrapper = mount(PostCard, {
      props: {
        post: makePost({ categoryName: "Help" }),
      },
    });

    expect(wrapper.find(".pin-btn").exists()).toBe(false);
  });

  it("emits refresh payload after successful pin", async () => {
    mocks.mockTogglePostPin.mockResolvedValue({
      ok: true,
      isPinned: true,
    });

    const post = makePost({ isPinned: false });

    const wrapper = mount(PostCard, {
      props: { post },
    });

    await wrapper.find(".pin-btn").trigger("click");
    await flushPromises();

    expect(mocks.mockTogglePostPin).toHaveBeenCalledWith(101);
    expect(post.isPinned).toBe(true);

    const emitted = wrapper.emitted("post-refresh");
    expect(emitted).toBeTruthy();
    expect(emitted[0][0]).toEqual({
      pinMessage: "Pinned successfully",
      pinMessageType: "success",
    });
  });

  it("emits refresh payload after successful unpin", async () => {
    mocks.mockTogglePostPin.mockResolvedValue({
      ok: true,
      isPinned: false,
    });

    const post = makePost({ isPinned: true });

    const wrapper = mount(PostCard, {
      props: { post },
    });

    await wrapper.find(".pin-btn").trigger("click");
    await flushPromises();

    expect(mocks.mockTogglePostPin).toHaveBeenCalledWith(101);
    expect(post.isPinned).toBe(false);

    const emitted = wrapper.emitted("post-refresh");
    expect(emitted).toBeTruthy();
    expect(emitted[0][0]).toEqual({
      pinMessage: "Unpinned successfully",
      pinMessageType: "success",
    });
  });

  it("shows error toast and does not emit refresh when pin request fails", async () => {
    mocks.mockTogglePostPin.mockResolvedValue({
      ok: false,
      error: "Failed to update pin state",
    });

    const wrapper = mount(PostCard, {
      props: {
        post: makePost(),
      },
    });

    await wrapper.find(".pin-btn").trigger("click");
    await flushPromises();

    expect(wrapper.text()).toContain("Failed to update pin state");
    expect(wrapper.emitted("post-refresh")).toBeFalsy();
  });
});