/**
 * VerifyPasscode — unit tests.
 * Covers:
 * - renders the passcode input and displays the email from the route query
 * - submit button disabled until a 6-digit code is entered
 * - routes to ForumHome on successful verification
 * - shows error message on incorrect or expired code
 * - shows network error message when API throws
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import VerifyPasscode from "@/views/auth/VerifyPasscode.vue";
import { verifyOtp } from "@/api/auth";

const mockPush = vi.fn();

vi.mock("vue-router", () => ({
  useRoute: () => ({ query: { email: "user@test.com" } }),
  useRouter: () => ({ push: mockPush }),
}));

vi.mock("@/api/auth", () => ({
  verifyOtp: vi.fn(),
}));

vi.mock("@/stores/userStore", () => ({
  syncProfileOnLoad: vi.fn(() => Promise.resolve()),
}));

const stubs = { RouterLink: { template: "<a><slot /></a>" } };

describe("VerifyPasscode.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders the passcode input and shows the email from the route", () => {
    const wrapper = mount(VerifyPasscode, { global: { stubs } });
    expect(wrapper.find("input#otp").exists()).toBe(true);
    expect(wrapper.text()).toContain("user@test.com");
  });

  it("disables submit until a 6-digit code is entered", async () => {
    const wrapper = mount(VerifyPasscode, { global: { stubs } });
    const btn = wrapper.find("button[type='submit']");
    expect(btn.element.disabled).toBe(true);

    await wrapper.find("input#otp").setValue("123");
    expect(btn.element.disabled).toBe(true);

    await wrapper.find("input#otp").setValue("123456");
    expect(btn.element.disabled).toBe(false);
  });

  it("routes to ForumHome on successful verification", async () => {
    verifyOtp.mockResolvedValue({ ok: true });

    const wrapper = mount(VerifyPasscode, { global: { stubs } });
    await wrapper.find("input#otp").setValue("123456");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(verifyOtp).toHaveBeenCalledWith("user@test.com", "123456");
    expect(mockPush).toHaveBeenCalledWith({ name: "ForumHome" });
  });

  it("shows error on incorrect or expired code", async () => {
    verifyOtp.mockResolvedValue({ ok: false, message: "Incorrect or expired code." });

    const wrapper = mount(VerifyPasscode, { global: { stubs } });
    await wrapper.find("input#otp").setValue("000000");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(wrapper.find(".notice.error").text()).toContain("Incorrect or expired code");
    expect(mockPush).not.toHaveBeenCalled();
  });

  it("shows a network error when the API throws", async () => {
    verifyOtp.mockRejectedValue(new Error("timeout"));

    const wrapper = mount(VerifyPasscode, { global: { stubs } });
    await wrapper.find("input#otp").setValue("123456");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(wrapper.find(".notice.error").text()).toContain("Network error");
  });
});
