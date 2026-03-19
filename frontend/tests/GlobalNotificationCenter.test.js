/**
 * Global notifications — unit tests.
 * Focus:
 * - preference gating
 * - popup rendering from fetched notifications
 * - close marks read
 * - open marks read and navigates
 * - disabled preferences block popup display
 */
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import GlobalNotificationCenter from "@/components/user/GlobalNotificationCenter.vue";

const pushMock = vi.fn();

vi.mock("vue-router", () => ({
  useRouter: () => ({
    push: pushMock,
  }),
}));

const { mockFetchNotifications, mockMarkNotificationsRead } = vi.hoisted(() => ({
  mockFetchNotifications: vi.fn(),
  mockMarkNotificationsRead: vi.fn(),
}));

vi.mock("@/api/users", () => ({
  fetchNotifications: mockFetchNotifications,
  markNotificationsRead: mockMarkNotificationsRead,
}));

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: { value: true },
}));

describe("Global notifications — preference helpers", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("returns default preferences when localStorage is empty", async () => {
    const mod = await import("@/utils/notificationPreferences");
    expect(mod.getNotificationPreferences()).toEqual({
      emailNotifications: true,
      pushNotifications: true,
      postReplies: true,
      postLikes: true,
    });
  });

  it("disables notifications when pushNotifications is false", async () => {
    localStorage.setItem(
      "notificationPreferences",
      JSON.stringify({
        pushNotifications: false,
        postLikes: true,
        postReplies: true,
      })
    );

    const mod = await import("@/utils/notificationPreferences");
    expect(mod.isNotificationEnabled("postLike")).toBe(false);
    expect(mod.isNotificationEnabled("postReply")).toBe(false);
  });

  it("enables only the matching notification type", async () => {
    localStorage.setItem(
      "notificationPreferences",
      JSON.stringify({
        pushNotifications: true,
        postLikes: true,
        postReplies: false,
      })
    );

    const mod = await import("@/utils/notificationPreferences");
    expect(mod.isNotificationEnabled("postLike")).toBe(true);
    expect(mod.isNotificationEnabled("postReply")).toBe(false);
  });
});

describe("Global notifications — component behavior", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    localStorage.setItem(
      "notificationPreferences",
      JSON.stringify({
        emailNotifications: true,
        pushNotifications: true,
        postReplies: true,
        postLikes: true,
      })
    );

    mockMarkNotificationsRead.mockResolvedValue({ ok: true });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("renders popup when unread allowed notifications are fetched", async () => {
    mockFetchNotifications.mockResolvedValue({
      ok: true,
      items: [
        {
          notificationId: 101,
          postId: 41,
          type: "postLike",
          isRead: false,
          title: "Test post",
          createdAt: "2026-03-19T12:00:00Z",
        },
      ],
    });

    const wrapper = mount(GlobalNotificationCenter);
    await flushPromises();

    expect(wrapper.text()).toContain("Post liked");
    expect(wrapper.text()).toContain('Your post "Test post" received a like.');
  });

  it("does not render popup when matching preference is disabled", async () => {
    localStorage.setItem(
      "notificationPreferences",
      JSON.stringify({
        emailNotifications: true,
        pushNotifications: true,
        postReplies: true,
        postLikes: false,
      })
    );

    mockFetchNotifications.mockResolvedValue({
      ok: true,
      items: [
        {
          notificationId: 201,
          postId: 55,
          type: "postLike",
          isRead: false,
          title: "Hidden like",
          createdAt: "2026-03-19T12:00:00Z",
        },
      ],
    });

    const wrapper = mount(GlobalNotificationCenter);
    await flushPromises();

    expect(wrapper.text()).not.toContain("Post liked");
    expect(wrapper.text()).not.toContain("Hidden like");
  });

  it("Close marks notification as read and removes it", async () => {
    mockFetchNotifications.mockResolvedValue({
      ok: true,
      items: [
        {
          notificationId: 301,
          postId: 77,
          type: "postReply",
          isRead: false,
          title: "Reply target",
          createdAt: "2026-03-19T12:00:00Z",
        },
      ],
    });

    const wrapper = mount(GlobalNotificationCenter);
    await flushPromises();

    const buttons = wrapper.findAll("button");
    const closeBtn = buttons.find((b) => b.text() === "Close");

    expect(closeBtn).toBeTruthy();
    await closeBtn.trigger("click");
    await flushPromises();

    expect(mockMarkNotificationsRead).toHaveBeenCalledWith([301]);
    expect(wrapper.text()).not.toContain("Reply target");
  });

  it("Open marks notification as read and navigates to the post", async () => {
    mockFetchNotifications.mockResolvedValue({
      ok: true,
      items: [
        {
          notificationId: 401,
          postId: 88,
          type: "postLike",
          isRead: false,
          title: "Open me",
          createdAt: "2026-03-19T12:00:00Z",
        },
      ],
    });

    const wrapper = mount(GlobalNotificationCenter);
    await flushPromises();

    const buttons = wrapper.findAll("button");
    const openBtn = buttons.find((b) => b.text() === "Open");

    expect(openBtn).toBeTruthy();
    await openBtn.trigger("click");
    await flushPromises();

    expect(mockMarkNotificationsRead).toHaveBeenCalledWith([401]);
    expect(pushMock).toHaveBeenCalledWith("/posts/88");
  });
});