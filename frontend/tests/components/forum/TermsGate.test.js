/** @vitest-environment jsdom */
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

vi.mock("@/stores/userStore", async () => {
  const vue = await import("vue");
  return {
    isLoggedIn: vue.ref(false),
    isBanned: vue.ref(false),
    banType: vue.ref(null),
    bannedUntil: vue.ref(null),
  };
});

vi.mock("@/api/client", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));
import client from "@/api/client";

import App from "@/App.vue";

describe("Terms Gate (App.vue)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows TermsModal when /me says termsAccepted = 0", async () => {
    client.get.mockResolvedValueOnce({
      data: { ok: true, user: { termsAccepted: 0 } },
    });

    const wrapper = mount(App, {
      global: {
        stubs: {
          "router-view": { template: "<div />" },
          CSUSHeader: { template: "<div />" },
          OWPHeader: { template: "<div />" },
          Footer: { template: "<div />" },
        },
      },
    });

    await flushPromises();

    expect(wrapper.html()).toContain("Terms");
  });

  it("does NOT show TermsModal when /me says termsAccepted = 1", async () => {
    client.get.mockResolvedValueOnce({
      data: { ok: true, user: { termsAccepted: 1 } },
    });

    const wrapper = mount(App, {
      global: {
        stubs: {
          "router-view": { template: "<div />" },
          CSUSHeader: { template: "<div />" },
          OWPHeader: { template: "<div />" },
          Footer: { template: "<div />" },
        },
      },
    });

    await flushPromises();

    expect(wrapper.html()).not.toContain("I agree");
  });
});