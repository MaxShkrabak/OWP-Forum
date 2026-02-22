/** @vitest-environment jsdom */
import { mount } from "@vue/test-utils";
import { describe, it, expect, beforeEach } from "vitest";
import CommentSection from "@/components/forum/CommentSection.vue";


describe("CommentSection.vue", () => {
  let wrapper;

  beforeEach(() => {
    wrapper = mount(CommentSection, {
      global: {
        stubs: { SingleComment: true },
      },
    });
  });

  // TODO: this test will need to be changed once backend is linked
  it("displays the correct total number of comments", () => {
    expect(wrapper.find(".section-title").text()).toContain("10 Comments");
  });

  it("disables the submit button if there is no data, and enables it when text is entered", async () => {
    const textarea = wrapper.find(".comment-textarea");
    await textarea.trigger("focus");

    const submitBtn = wrapper.find(".btn-submit");
    expect(submitBtn.element.disabled).toBe(true);

    await textarea.setValue("Hello!! this is my first comment.");
    expect(submitBtn.element.disabled).toBe(false);

    await textarea.setValue("");
    expect(submitBtn.element.disabled).toBe(true);
  });

  it("only allows one reply box to be open at a time", async () => {
    const wrapper = mount(CommentSection);

    const replyButtons = wrapper.findAll(".action-btn");

    expect(replyButtons.length).toBeGreaterThanOrEqual(2);

    await replyButtons[0].trigger("click");

    let openReplyBoxes = wrapper.findAll(".reply-box-container");
    expect(openReplyBoxes.length).toBe(2);

    await replyButtons[1].trigger("click");

    openReplyBoxes = wrapper.findAll(".reply-box-container");
    expect(openReplyBoxes.length).toBe(2);
  });
});