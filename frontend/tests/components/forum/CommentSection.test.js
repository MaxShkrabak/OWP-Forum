import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, beforeEach, vi } from "vitest";
import CommentSection from "@/components/forum/CommentSection.vue";
import { fetchComments, updateComment } from "@/api/comments";

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
          commentId: 1,
          content: "This is my first comment!",
          user: { userId: 1, firstName: "John", lastName: "Rogers" },
          replies: [],
        },
        {
          commentId: 2,
          content: "Wow this post is cool!",
          user: { userId: 2, firstName: "Jack", lastName: "Timothy" },
          replies: [],
        },
      ],
    }),
  ),
  submitComment: vi.fn(),
  updateComment: vi.fn(() =>
    Promise.resolve({
      ok: true,
      comment: {
        commentId: 1,
        content: "Updated comment content",
        createdAt: 1700000000,
        updatedAt: 1700001000,
        user: { userId: 1, firstName: "John", lastName: "Rogers" },
      },
    }),
  ),
  formatCommentData: vi.fn((data) => ({
    ...data,
    id: data.commentId || data.id || Math.random(),
    author: data.user
      ? `${data.user.firstName} ${data.user.lastName}`
      : "Unknown",
    text: data.content,
    user: {
      ...(data.user || {}),
      role: data.user?.role || "user",
    },
    replies: [],
    replyCount: data.replyCount || 0,
    updatedAt: data.updatedAt ?? null,
    wasEdited:
      data.updatedAt !== undefined &&
      data.updatedAt !== null &&
      data.updatedAt !== data.createdAt,
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

  it("renders a sort dropdown with the correct options", () => {
    const select = wrapper.find("#comment-sort");
    const options = select.findAll("option").map((o) => o.text());
    expect(options).toEqual(["Newest", "Oldest", "Most Liked"]);
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

  it("allows the author to edit their own comment and shows edited state", async () => {
    await flushPromises();

    const editButtons = wrapper.findAll(".btn-options");
    expect(editButtons.length).toBeGreaterThan(0);

    await editButtons[0].trigger("click");

    const editTextarea = wrapper.find("textarea.reply-textarea");
    expect(editTextarea.exists()).toBe(true);

    await editTextarea.setValue("Updated comment content");

    const saveButton = wrapper.findAll(".btn-submit").at(-1);
    expect(saveButton.element.disabled).toBe(false);

    // First click opens the confirmation modal
    await saveButton.trigger("click");
    await flushPromises();

    // Confirm in the modal
    const confirmButton = document.querySelector(
      ".comment-modal-card .btn-submit",
    );
    expect(confirmButton).not.toBeNull();
    confirmButton.click();
    await flushPromises();

    expect(updateComment).toHaveBeenCalled();
    const editedLabel = wrapper.find(".edited-label");
    expect(editedLabel.exists()).toBe(true);
  });

  it("refetches comments when the sort option changes", async () => {
    await flushPromises();

    const select = wrapper.find("#comment-sort");
    await select.setValue("mostLiked");
    await flushPromises();

    expect(fetchComments).toHaveBeenCalled();
    const lastCallArgs = fetchComments.mock.calls.at(-1);
    expect(lastCallArgs[3]).toBe("mostLiked");
  });
});
