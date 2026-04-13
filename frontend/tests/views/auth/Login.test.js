/**
 * Login — unit tests.
 * Covers:
 * - renders the login form with email input and submit button
 * - shows error for invalid email format
 * - shows error when email is not registered
 * - routes to /verify on successful OTP request
 * - shows error when verifyEmail API fails
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import Login from "@/views/auth/Login.vue";
import { verifyEmail, requestOtp } from "@/api/auth";

const mockPush = vi.fn();

vi.mock("vue-router", () => ({
  useRouter: () => ({ push: mockPush }),
}));

vi.mock("@/api/auth", () => ({
  verifyEmail: vi.fn(),
  requestOtp: vi.fn(),
}));

const stubs = { RouterLink: { template: "<a><slot /></a>" } };

describe("Login.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders the login form with email input and submit button", () => {
    const wrapper = mount(Login, { global: { stubs } });
    expect(wrapper.find("input#email").exists()).toBe(true);
    expect(wrapper.find("button[type='submit']").text()).toContain("Get passcode");
  });

  it("shows an error for invalid email format", async () => {
    const wrapper = mount(Login, { global: { stubs } });

    await wrapper.find("input#email").setValue("not-an-email");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(wrapper.find(".notice.error").text()).toContain("valid email");
    expect(verifyEmail).not.toHaveBeenCalled();
  });

  it("shows an error when the email is not registered", async () => {
    verifyEmail.mockResolvedValue({ ok: true, emailExists: false });

    const wrapper = mount(Login, { global: { stubs } });
    await wrapper.find("input#email").setValue("unknown@test.com");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(wrapper.find(".notice.error").text()).toContain("not been registered");
    expect(requestOtp).not.toHaveBeenCalled();
  });

  it("routes to /verify on successful OTP request", async () => {
    verifyEmail.mockResolvedValue({ ok: true, emailExists: true });
    requestOtp.mockResolvedValue({ ok: true });

    const wrapper = mount(Login, { global: { stubs } });
    await wrapper.find("input#email").setValue("user@test.com");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(requestOtp).toHaveBeenCalledWith("user@test.com");
    expect(mockPush).toHaveBeenCalledWith({
      path: "/verify",
      query: { email: "user@test.com" },
    });
  });

  it("shows an error when verifyEmail throws", async () => {
    verifyEmail.mockRejectedValue(new Error("Network failure"));

    const wrapper = mount(Login, { global: { stubs } });
    await wrapper.find("input#email").setValue("user@test.com");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(wrapper.find(".notice.error").text()).toContain("Network failure");
  });
});
