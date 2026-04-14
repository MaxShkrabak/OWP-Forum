import { describe, it, expect, vi, beforeEach } from "vitest";

const { mockCheckAuth, mockLogout } = vi.hoisted(() => ({
  mockCheckAuth: vi.fn(),
  mockLogout: vi.fn(),
}));

vi.mock("@/api/auth", () => ({
  checkAuth: mockCheckAuth,
  logout: mockLogout,
}));

describe("userStore local storage isolation", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.resetModules();
    localStorage.clear();

    mockCheckAuth.mockResolvedValue({
      ok: true,
      user: {
        userId: 42,
        firstName: "Test",
        lastName: "User",
        roleName: "User",
        roleId: 1,
        isBanned: 0,
        banType: null,
        bannedUntil: null,
        termsAccepted: 1,
        avatar: "waves.svg",
      },
    });
    mockLogout.mockResolvedValue({ ok: true });
  });

  it("clears user-scoped cooldown and notification prefs on logout", async () => {
    const store = await import("@/stores/userStore");
    await store.profileLoaded;

    store.blockPostCreationFor(60);
    localStorage.setItem(
      "notificationPreferences:42",
      JSON.stringify({
        pushNotifications: false,
        postLikes: false,
        postReplies: true,
      }),
    );

    expect(localStorage.getItem("createPostBlockedUntil:42")).toBeTruthy();
    expect(localStorage.getItem("notificationPreferences:42")).toBeTruthy();

    await store.logoutUser();

    expect(store.createPostBlockedUntil.value).toBe(0);
    expect(localStorage.getItem("createPostBlockedUntil:42")).toBeNull();
    expect(localStorage.getItem("notificationPreferences:42")).toBeNull();
  });
});
