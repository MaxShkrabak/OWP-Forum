/**
 * UserSettings — unit tests.
 * Covers:
 * - renders profile picture and notification preference sections
 * - clicking an avatar marks it as selected
 * - saving persists the chosen avatar and notification preferences, then closes the modal
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import UserSettings from "@/components/user/UserSettings.vue";

const {
  mockUpdateUserAvatar,
  mockGetNotificationSettings,
  mockSaveNotificationSettings,
} = vi.hoisted(() => ({
  mockUpdateUserAvatar: vi.fn(),
  mockGetNotificationSettings: vi.fn(),
  mockSaveNotificationSettings: vi.fn(),
}));

vi.mock("@/api/auth", () => ({
  updateUserAvatar: mockUpdateUserAvatar,
}));

vi.mock("@/api/users", () => ({
  getNotificationSettings: mockGetNotificationSettings,
  saveNotificationSettings: mockSaveNotificationSettings,
}));

vi.mock("@/stores/userStore", async () => {
  const { ref } = await import("vue");
  return {
    userAvatar: ref("/src/assets/img/user-pfps-premade/pfp-0.png"),
  };
});

describe("UserSettings.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(window, "alert").mockImplementation(() => {});
    localStorage.clear();
    localStorage.setItem("uid", "42");
    mockGetNotificationSettings.mockResolvedValue({
      ok: true,
      settings: { emailNotifications: true },
    });
    mockUpdateUserAvatar.mockResolvedValue({ ok: true });
    mockSaveNotificationSettings.mockResolvedValue({ ok: true });
  });

  it("renders profile and notification sections", async () => {
    const wrapper = mount(UserSettings);
    await flushPromises();

    expect(wrapper.text()).toContain("User Settings");
    expect(wrapper.text()).toContain("Profile Picture");
    expect(wrapper.text()).toContain("Notification Preferences");
    expect(wrapper.findAll(".notification-item")).toHaveLength(4);
  });

  it("updates selected avatar when an avatar is clicked", async () => {
    const wrapper = mount(UserSettings);
    await flushPromises();

    const avatars = wrapper.findAll(".pfp-selector");
    expect(avatars.length).toBeGreaterThan(1);

    await avatars[1].trigger("click");

    expect(avatars[1].classes()).toContain("pfp-selected");
  });

  it("saves avatar + notification preferences and updates store", async () => {
    const hide = vi.fn();
    window.bootstrap = {
      Modal: {
        getInstance: vi.fn(() => ({ hide })),
      },
    };
    document.body.innerHTML = '<div id="userSettingsModal"></div>';

    const wrapper = mount(UserSettings);
    await flushPromises();

    const avatars = wrapper.findAll(".pfp-selector");
    await avatars[1].trigger("click");
    await wrapper.find("#pushNotifications").setValue(false);
    await wrapper.find(".save-btn").trigger("click");
    await flushPromises();

    expect(mockUpdateUserAvatar).toHaveBeenCalledTimes(1);
    expect(mockSaveNotificationSettings).toHaveBeenCalledTimes(1);
    expect(localStorage.getItem("userAvatar")).toBeTruthy();
    expect(localStorage.getItem("notificationPreferences:42")).toBeTruthy();
    expect(hide).toHaveBeenCalledTimes(1);
  });

  it("rolls back the avatar if notification settings fail after avatar save", async () => {
    mockSaveNotificationSettings.mockResolvedValueOnce({ ok: false });

    const wrapper = mount(UserSettings);
    await flushPromises();

    const avatars = wrapper.findAll(".pfp-selector");
    await avatars[1].trigger("click");
    await wrapper.find(".save-btn").trigger("click");
    await flushPromises();

    expect(mockUpdateUserAvatar).toHaveBeenCalledTimes(2);
    expect(mockUpdateUserAvatar.mock.calls[0][0]).not.toBe(
      mockUpdateUserAvatar.mock.calls[1][0],
    );
    expect(localStorage.getItem("userAvatar")).toBeNull();
    expect(window.alert).toHaveBeenCalledWith(
      "Could not save your notification preferences, please try again later.",
    );
  });
});
