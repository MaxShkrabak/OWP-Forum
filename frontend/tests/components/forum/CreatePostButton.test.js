/**
 * CreatePostButton — unit tests.
 * Covers:
 * - renders enabled Create Post button for a regular user with no cooldown
 * - button disabled and shows cooldown text when a cooldown is active
 * - moderators are exempt from cooldowns (button stays enabled)
 * - button disabled when user is banned
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import CreatePostButton from "@/components/forum/CreatePostButton.vue";

const { mockIsBanned, mockUserRoleId, mockCreatePostBlockedUntil } =
  vi.hoisted(() => {
    const vue = require("vue");
    return {
      mockIsBanned: vue.ref(false),
      mockUserRoleId: vue.ref(1),
      mockCreatePostBlockedUntil: vue.ref(0),
    };
  });

vi.mock("@/stores/userStore", () => ({
  isBanned: mockIsBanned,
  userRoleId: mockUserRoleId,
  createPostBlockedUntil: mockCreatePostBlockedUntil,
  blockPostCreationFor: vi.fn(),
}));

const stubs = {
  CreatePostModal: { template: "<div />" },
};

describe("CreatePostButton.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockIsBanned.value = false;
    mockUserRoleId.value = 1;
    mockCreatePostBlockedUntil.value = 0;
  });

  it("renders an enabled Create Post button for a regular user", () => {
    const wrapper = mount(CreatePostButton, { global: { stubs } });
    const btn = wrapper.find(".btn-create-post");
    expect(btn.exists()).toBe(true);
    expect(btn.element.disabled).toBe(false);
    expect(btn.text()).toContain("Create Post");
  });

  it("disables button and shows cooldown text when blocked", () => {
    mockCreatePostBlockedUntil.value = Date.now() + 30_000;

    const wrapper = mount(CreatePostButton, { global: { stubs } });
    const btn = wrapper.find(".btn-create-post");
    expect(btn.element.disabled).toBe(true);
    expect(btn.text()).toContain("Blocked for");
  });

  it("exempts moderators from cooldowns", () => {
    mockUserRoleId.value = 3;
    mockCreatePostBlockedUntil.value = Date.now() + 30_000;

    const wrapper = mount(CreatePostButton, { global: { stubs } });
    const btn = wrapper.find(".btn-create-post");
    expect(btn.element.disabled).toBe(false);
    expect(btn.text()).not.toContain("Blocked");
  });

  it("disables button when user is banned", () => {
    mockIsBanned.value = true;

    const wrapper = mount(CreatePostButton, { global: { stubs } });
    expect(wrapper.find(".btn-create-post").element.disabled).toBe(true);
  });
});
