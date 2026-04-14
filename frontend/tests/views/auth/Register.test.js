/**
 * Register — unit tests.
 * Covers:
 * - renders the registration form with all required fields
 * - submit button disabled until all fields pass validation
 * - submit button enabled when all fields are valid
 * - routes to /verify on successful registration
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import Register from "@/views/auth/Register.vue";
import { registerUser, requestOtp } from "@/api/auth";

const mockPush = vi.fn();

vi.mock("vue-router", () => ({
  useRouter: () => ({ push: mockPush }),
}));

vi.mock("@/api/auth", () => ({
  registerUser: vi.fn(),
  requestOtp: vi.fn(),
}));

const stubs = { RouterLink: { template: "<a><slot /></a>" } };

describe("Register.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders all form fields", () => {
    const wrapper = mount(Register, { global: { stubs } });
    expect(wrapper.find("input#first").exists()).toBe(true);
    expect(wrapper.find("input#last").exists()).toBe(true);
    expect(wrapper.find("input#ssn").exists()).toBe(true);
    expect(wrapper.find("input#email").exists()).toBe(true);
  });

  it("disables the submit button when fields are empty or invalid", async () => {
    const wrapper = mount(Register, { global: { stubs } });
    const btn = wrapper.find("button[type='submit']");
    expect(btn.element.disabled).toBe(true);

    await wrapper.find("input#first").setValue("Joe");
    await wrapper.find("input#last").setValue("Hornet");
    // SSN and email still empty
    expect(btn.element.disabled).toBe(true);
  });

  it("enables the submit button when all fields are valid", async () => {
    const wrapper = mount(Register, { global: { stubs } });

    await wrapper.find("input#first").setValue("Joe");
    await wrapper.find("input#last").setValue("Hornet");
    await wrapper.find("input#ssn").setValue("1234");
    await wrapper.find("input#email").setValue("joe@test.com");

    expect(wrapper.find("button[type='submit']").element.disabled).toBe(false);
  });

  it("routes to /verify on successful registration and OTP request", async () => {
    registerUser.mockResolvedValue({ ok: true });
    requestOtp.mockResolvedValue({ ok: true });

    const wrapper = mount(Register, { global: { stubs } });

    await wrapper.find("input#first").setValue("Joe");
    await wrapper.find("input#last").setValue("Hornet");
    await wrapper.find("input#ssn").setValue("1234");
    await wrapper.find("input#email").setValue("joe@test.com");

    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(registerUser).toHaveBeenCalledWith({
      first: "Joe",
      last: "Hornet",
      email: "joe@test.com",
    });
    expect(requestOtp).toHaveBeenCalledWith("joe@test.com");
    expect(mockPush).toHaveBeenCalledWith({
      path: "/verify",
      query: { email: "joe@test.com" },
    });
  });
});
