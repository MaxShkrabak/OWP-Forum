/**
 * Terms gate (App.vue) — unit tests.
 * Covers:
 * - shows TermsModal when user is logged in but has not accepted terms
 * - suppresses TermsModal when terms have already been accepted
 */
import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";

vi.mock("vue-router", () => ({
  useRoute: vi.fn(() => ({
    meta: {},
    fullPath: "/",
  })),
  useRouter: vi.fn(() => ({
    push: vi.fn(),
    replace: vi.fn(),
    back: vi.fn(),
  })),
}));

const { mockIsLoggedIn, mockTermsAccepted } = vi.hoisted(() => {
  const vue = require("vue");
  return {
    mockIsLoggedIn: vue.ref(false),
    mockTermsAccepted: vue.ref(false),
  };
});

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: mockIsLoggedIn,
  isBanned: { value: false },
  banType: { value: null },
  bannedUntil: { value: null },
  termsAccepted: mockTermsAccepted,
  profileLoaded: Promise.resolve(),
}));

import App from "@/App.vue";

describe("Terms Gate (App.vue)", () => {
  beforeEach(() => {
    mockIsLoggedIn.value = false;
    mockTermsAccepted.value = false;
  });

  it("shows TermsModal when termsAccepted = 0", async () => {
    mockIsLoggedIn.value = true;

    const wrapper = mount(App, {
      global: {
        stubs: {
          "router-view": { template: "<div />" },
          CSUSHeader: { template: "<div />" },
          OWPHeader: { template: "<div />" },
          Footer: { template: "<div />" },
          GlobalNotificationCenter: { template: "<div />" },
        },
      },
    });

    await flushPromises();

    expect(wrapper.html()).toContain("Terms");
  });

  it("does NOT show TermsModal when termsAccepted = 1", async () => {
    mockIsLoggedIn.value = true;
    mockTermsAccepted.value = true;

    const wrapper = mount(App, {
      global: {
        stubs: {
          "router-view": { template: "<div />" },
          CSUSHeader: { template: "<div />" },
          OWPHeader: { template: "<div />" },
          Footer: { template: "<div />" },
          GlobalNotificationCenter: { template: "<div />" },
        },
      },
    });

    await flushPromises();

    expect(wrapper.html()).not.toContain("I agree");
  });
});