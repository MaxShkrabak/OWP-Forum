/**
 * TermsModal — unit tests.
 * Covers:
 * - shows links to Terms of Service and Privacy Policy
 * - Continue button is disabled until the I Agree checkbox is checked
 * - emits accepted and calls the API when Continue is clicked
 */
import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";

import TermsModal from "@/components/legal/TermsModal.vue";

const RouterLinkStub = {
  name: "RouterLink",
  props: ["to"],
  template: `<a :href="typeof to === 'string' ? to : (to?.path || '#')"><slot /></a>`,
};

vi.mock("@/api/client", () => {
  return {
    default: {
      get: vi.fn(),
      post: vi.fn(),
    },
  };
});

import client from "@/api/client";

describe("TermsModal.vue (unit)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows links to Terms and Privacy policy", () => {
    const wrapper = mount(TermsModal, {
      global: {
        stubs: { RouterLink: RouterLinkStub },
      },
    });

    const anchors = wrapper.findAll("a");
    const hrefs = anchors.map((a) => a.attributes("href"));

    expect(hrefs).toContain("/terms");
    expect(hrefs).toContain("/privacy");
  });

  it("Continue is disabled until I agree is checked", async () => {
    const wrapper = mount(TermsModal, {
      global: {
        stubs: { RouterLink: RouterLinkStub },
      },
    });

    const checkbox =
      wrapper.find('[data-test="terms-checkbox"]')?.exists()
        ? wrapper.find('[data-test="terms-checkbox"]')
        : wrapper.find('input[type="checkbox"]');

    expect(checkbox.exists()).toBe(true);

    const button =
      wrapper.find('[data-test="terms-continue"]')?.exists()
        ? wrapper.find('[data-test="terms-continue"]')
        : wrapper.find("button");

    expect(button.exists()).toBe(true);
    expect(button.attributes("disabled")).toBeDefined();

    await checkbox.setValue(true);
    expect(button.attributes("disabled")).toBeUndefined();
  });

  it("emits accepted when Continue is clicked after agreeing", async () => {
    client.post.mockResolvedValueOnce({ data: { ok: true } });

    const wrapper = mount(TermsModal, {
      global: {
        stubs: { RouterLink: RouterLinkStub },
      },
    });

    const checkbox =
      wrapper.find('[data-test="terms-checkbox"]')?.exists()
        ? wrapper.find('[data-test="terms-checkbox"]')
        : wrapper.find('input[type="checkbox"]');

    const button =
      wrapper.find('[data-test="terms-continue"]')?.exists()
        ? wrapper.find('[data-test="terms-continue"]')
        : wrapper.find("button");

    expect(checkbox.exists()).toBe(true);
    expect(button.exists()).toBe(true);

    await checkbox.setValue(true);
    await button.trigger("click");

    expect(client.post).toHaveBeenCalled();
    expect(wrapper.emitted("accepted")).toBeTruthy();
  });
});