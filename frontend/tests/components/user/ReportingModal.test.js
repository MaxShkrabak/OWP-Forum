/**
 * ReportingModal — unit tests.
 * Covers:
 * - opens when isOpen prop changes to true
 * - displays the report type and target title
 * - renders all seeded report tags
 * - submit button enabled only when a tag is selected
 * - error banner on duplicate report (with shake animation)
 * - success view and close emission after a successful submit
 */
import { describe, it, expect, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ReportingModal from "@/components/user/ReportingModal.vue";
import { submitReport } from "@/api/reports";

vi.mock("@/api/reports", () => ({
  getReportTags: vi.fn(() =>
    Promise.resolve([
      { tagId: 1, name: "Spam" },
      { tagId: 2, name: "Harassment" },
      { tagId: 3, name: "Inappropriate" },
      { tagId: 4, name: "Misinformation" },
      { tagId: 5, name: "Other" },
    ]),
  ),
  submitReport: vi.fn(),
}));

describe("ReportingModal.vue", () => {
  const createWrapper = (props = {}) => {
    return mount(ReportingModal, {
      props: { isOpen: false, ...props },
      global: { stubs: { teleport: true } },
    });
  };

  it("opens the modal when the isOpen prop changes to true", async () => {
    const wrapper = createWrapper();

    expect(wrapper.find(".report-body").exists()).toBe(false);

    await wrapper.setProps({ isOpen: true });
    await flushPromises();

    expect(wrapper.find(".report-body").exists()).toBe(true);
    expect(wrapper.text()).toContain("Report");
  });

  it("displays the report type and post title correctly when opened", async () => {
    const wrapper = createWrapper({
      isOpen: true,
      type: "Post",
      targetTitle: "This is my first post!",
    });
    await flushPromises();

    expect(wrapper.text()).toContain("Report Post");
    expect(wrapper.text()).toContain("This is my first post!");
  });

  it("renders all seeded report tags from the database", async () => {
    const wrapper = createWrapper();
    await wrapper.setProps({ isOpen: true });
    await flushPromises();

    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Harassment");
    expect(wrapper.text()).toContain("Inappropriate");
  });

  it("toggles the submit button state based on tag selections", async () => {
    const wrapper = createWrapper();
    await wrapper.setProps({ isOpen: true });
    await flushPromises();

    const submitButton = wrapper.find(".btn-primary-action");
    expect(submitButton.element.disabled).toBe(true);

    await wrapper.find(".reason-card").trigger("click");

    expect(submitButton.element.disabled).toBe(false);

    await wrapper.find(".reason-card").trigger("click"); // unselect tag
    expect(submitButton.element.disabled).toBe(true);
  });

  it("shows an error message when a duplicate report is submitted", async () => {
    submitReport.mockResolvedValueOnce({
      ok: false,
      error: "You have already reported this.",
    });
    const wrapper = createWrapper();
    await wrapper.setProps({ isOpen: true });
    await flushPromises();

    await wrapper.find(".reason-card").trigger("click");

    await wrapper.find(".btn-primary-action").trigger("click");
    await flushPromises();

    // submit again to trigger the shake effect
    await wrapper.find(".btn-primary-action").trigger("click");

    const errorBanner = wrapper.find(".error-banner");
    expect(errorBanner.exists()).toBe(true);
    expect(errorBanner.text()).toContain("You have already reported this.");

    expect(errorBanner.classes()).toContain("shake-err");
  });

  it("shows success view and emits close when done", async () => {
    submitReport.mockResolvedValueOnce({ ok: true });
    const wrapper = createWrapper({ targetId: 1, type: "Post" });
    await wrapper.setProps({ isOpen: true });
    await flushPromises();

    await wrapper.find(".reason-card").trigger("click");
    await wrapper.find(".btn-primary-action").trigger("click");
    await flushPromises();

    expect(wrapper.text()).toContain("Thank You");
    expect(wrapper.text()).toContain("We've received your report for this");

    await wrapper.find(".btn-primary-action").trigger("click");
    expect(wrapper.emitted()).toHaveProperty("close");
  });
});