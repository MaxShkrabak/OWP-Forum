/**
 * Global notifications — unit tests.
 * Covers:
 * - preference defaults
 * - preference gating
 * - popup rendering from fetched unread notifications
 * - close marks read and removes
 * - open marks read and navigates
 * - auto-dismiss after 5 seconds
 * - rate limit / max visible notifications
 */
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import GlobalNotificationCenter from "@/components/user/GlobalNotificationCenter.vue";

const pushMock = vi.fn();

vi.mock("vue-router", () => ({
  useRouter: () => ({
    push: pushMock,
  }),
  useRoute: () => ({
    fullPath: "/",
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
    vi.useRealTimers();
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

  it("auto-dismisses notification after 5 seconds", async () => {
    vi.useFakeTimers();

    mockFetchNotifications.mockResolvedValue({
      ok: true,
      items: [
        {
          notificationId: 501,
          postId: 10,
          type: "postLike",
          isRead: false,
          title: "Auto dismiss post",
          createdAt: "2026-03-19T12:00:00Z",
        },
      ],
    });

    const wrapper = mount(GlobalNotificationCenter);
    await flushPromises();

    expect(wrapper.text()).toContain("Auto dismiss post");

    vi.advanceTimersByTime(5000);
    await flushPromises();

    expect(mockMarkNotificationsRead).toHaveBeenCalledWith([501]);
    expect(wrapper.text()).not.toContain("Auto dismiss post");
  });

  it("shows at most 3 notifications and discards overflow notifications", async () => {
    mockFetchNotifications.mockResolvedValue({
      ok: true,
      items: [
        {
          notificationId: 1,
          postId: 1,
          type: "postLike",
          isRead: false,
          title: "Post 1",
          createdAt: "2026-03-19T12:00:00Z",
        },
        {
          notificationId: 2,
          postId: 2,
          type: "postLike",
          isRead: false,
          title: "Post 2",
          createdAt: "2026-03-19T12:00:01Z",
        },
        {
          notificationId: 3,
          postId: 3,
          type: "postReply",
          isRead: false,
          title: "Post 3",
          createdAt: "2026-03-19T12:00:02Z",
        },
        {
          notificationId: 4,
          postId: 4,
          type: "postReply",
          isRead: false,
          title: "Post 4",
          createdAt: "2026-03-19T12:00:03Z",
        },
      ],
    });

    const wrapper = mount(GlobalNotificationCenter);
    await flushPromises();

    const popups = wrapper.findAll(".notification-popup");
    expect(popups.length).toBe(3);

    expect(wrapper.text()).toContain("Post 1");
    expect(wrapper.text()).toContain("Post 2");
    expect(wrapper.text()).toContain("Post 3");
    expect(wrapper.text()).not.toContain("Post 4");

    expect(mockMarkNotificationsRead).toHaveBeenCalledWith([4]);
  });
});

it("discards notifications with disabled types and marks them as read", async () => {
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
        notificationId: 9,
        postId: 99,
        type: "postLike",
        isRead: false,
        title: "Disabled like",
        createdAt: "2026-03-19T12:00:00Z",
      },
    ],
  });

  const wrapper = mount(GlobalNotificationCenter);
  await flushPromises();

  expect(wrapper.text()).not.toContain("Disabled like");
  expect(mockMarkNotificationsRead).toHaveBeenCalledWith([9]);
});