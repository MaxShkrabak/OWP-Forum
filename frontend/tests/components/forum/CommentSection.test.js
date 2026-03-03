/** @vitest-environment jsdom */
import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, beforeEach, vi } from "vitest";
import CommentSection from "@/components/forum/CommentSection.vue";
import { fetchComments } from "@/api/comments";

vi.mock("@/api/auth", () => ({
  checkAuth: vi.fn(() =>
    Promise.resolve({
      ok: true,
      user: { User_ID: 1, FirstName: "Test", LastName: "User" },
    }),
  ),
  logout: vi.fn(() => Promise.resolve({ ok: true })),
}));

vi.mock("@/api/comments", () => ({
  fetchComments: vi.fn(() =>
    Promise.resolve({
      ok: true,
      total: 15,
      items: [
        {
          id: 1,
          content: "This is my first comment!",
          user: { firstName: "John", lastName: "Rogers" },
          replies: [],
        },
        {
          id: 2,
          content: "Wow this post is cool!",
          user: { firstName: "Jack", lastName: "Timothy" },
          replies: [],
        },
      ],
    }),
  ),
  submitComment: vi.fn(),
  formatCommentData: vi.fn((data) => ({
    ...data,
    id: data.id || Math.random(),
    replies: [],
  })),
}));
describe("CommentSection.vue", () => {
  let wrapper;

  beforeEach(() => {
    wrapper = mount(CommentSection, {
      props: {
        postId: 12,
      },
      global: {
        stubs: { SingleComment: false },
      },
    });
  });

  it("displays the correct total number of comments", () => {
    expect(wrapper.find(".comments-header").text()).toContain("Comments (15)");
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
    const replyButtons = wrapper.findAll(".action-btn");
    expect(replyButtons.length).toBeGreaterThanOrEqual(2);

    await replyButtons[0].trigger("click");

    let openReplyBoxes = wrapper
      .find(".comments-container")
      .findAll(".reply-box-container");
    expect(openReplyBoxes.length).toBe(1);

    await replyButtons[1].trigger("click");

    openReplyBoxes = wrapper
      .find(".comments-container")
      .findAll(".reply-box-container");
    expect(openReplyBoxes.length).toBe(1);
  });

  it("fetches exactly 10 more comments and appends them when 'Show more' is clicked", async () => {
    const limit = 10;
    await flushPromises();
    const initialCount = wrapper.findAllComponents({ name: "SingleComment" }).length;

    const secondBatchItems = [];
    for (let i = 0; i < limit; i++) {
      const newComment = {
        id: 100 + i,
        content: `New comment ${i}`,
        user: { firstName: "Bill", lastName: "Test" },
        replies: [],
      };
      secondBatchItems.push(newComment);
    }

    fetchComments.mockResolvedValueOnce({
      ok: true,
      total: 25,
      items: secondBatchItems,
    });

    const loadMoreBtn = wrapper.find(".load-more-btn");
    await loadMoreBtn.trigger("click");
    await flushPromises();

    const renderedComments = wrapper.findAllComponents({ name: "SingleComment" });
    expect(renderedComments.length).toBe(initialCount + limit);

    expect(wrapper.find(".load-more-btn").exists()).toBe(true);
  });
});
