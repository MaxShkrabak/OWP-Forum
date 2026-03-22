import { mount, flushPromises, DOMWrapper } from "@vue/test-utils";
import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import CommentSection from "@/components/forum/CommentSection.vue";
import { fetchComments, submitComment, updateComment } from "@/api/comments";
import TextEditor from "@/components/forum/TextEditor.vue";

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: { value: true },
  uid: { value: 1 }, // Matches the userId: 1 in the mocked comments below
  userRole: { value: "user" },
}));

vi.mock("@/api/auth", () => ({
  checkAuth: vi.fn(() =>
    Promise.resolve({
      ok: true,
      user: { userId: 1, firstName: "Test", lastName: "User" },
    }),
  ),
  logout: vi.fn(() => Promise.resolve({ ok: true })),
}));

vi.mock("@/api/comments", () => ({
  fetchComments: vi.fn(() =>
    Promise.resolve({
      ok: true,
      total: 15,
      // We dynamically generate 10 items here so the "Show More" button stays visible
      items: Array.from({ length: 10 }, (_, i) => ({
        commentId: i + 1,
        content: `This is comment ${i + 1}`,
        user: { userId: 1, firstName: "John", lastName: "Rogers" },
        replies: [],
      })),
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

  afterEach(() => {
    wrapper.unmount();
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
    await wrapper.find(".reply-box-container").trigger("click");
    const submitButton = wrapper.find(".btn-submit");
    expect(submitButton.element.disabled).toBe(true);

    const editor = wrapper.findComponent(TextEditor);
    await editor.vm.$emit("update:modelValue", "This is a test comment");
    expect(submitButton.element.disabled).toBe(false);
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
    const initialCount = wrapper.findAllComponents({
      name: "SingleComment",
    }).length;

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

    const renderedComments = wrapper.findAllComponents({
      name: "SingleComment",
    });
    expect(renderedComments.length).toBe(initialCount + limit);

    expect(wrapper.find(".load-more-btn").exists()).toBe(true);
  });

  it("allows the author to edit their own comment and shows edited state", async () => {
    await flushPromises();

    const editButtons = wrapper.findAll(".btn-options");
    expect(editButtons.length).toBeGreaterThan(0);

    await editButtons[0].trigger("click");
    await flushPromises();

    const editors = wrapper.findAllComponents(TextEditor);

    const editEditor = editors.at(-1);
    expect(editEditor.exists()).toBe(true);

    await editEditor.vm.$emit("update:modelValue", "Updated comment content");

    const saveButton = wrapper.findAll(".btn-submit").at(-1);
    expect(saveButton.element.disabled).toBe(false);

    await saveButton.trigger("click");
    await flushPromises();

    const confirmButtonEl = document.querySelector(
      ".comment-modal-card .btn-submit",
    );
    expect(confirmButtonEl).not.toBeNull();

    await new DOMWrapper(confirmButtonEl).trigger("click");
    await flushPromises();

    expect(updateComment).toHaveBeenCalledWith(1, "Updated comment content");
    const editedLabel = wrapper.find(".edited-label");
    expect(editedLabel.exists()).toBe(true);
  });

  it("refetches comments when the sort option changes", async () => {
    await flushPromises();

    const select = wrapper.find("#comment-sort");
    await select.setValue("mostLiked");
    await flushPromises();

    expect(fetchComments).toHaveBeenCalledWith(12, 2, 10, "latest");
    const lastCallArgs = fetchComments.mock.calls.at(-1);
    expect(lastCallArgs[3]).toBe("mostLiked");
  });

  it("shows a centered rate limit modal with minutes and seconds", async () => {
    await flushPromises();

    submitComment.mockRejectedValueOnce({
      response: {
        status: 429,
        data: {
          ok: false,
          error:
            "You're commenting too fast. Please wait 75 seconds before commenting again.",
          rateLimit: {
            type: "cooldown",
            secondsLeft: 75,
          },
        },
      },
    });

    await wrapper.find(".reply-box-container").trigger("click");

    const editor = wrapper.findComponent(TextEditor);
    expect(editor.exists()).toBe(true);
    await editor.vm.$emit("update:modelValue", "Trying to post too quickly.");
    await flushPromises();

    const submitBtn = wrapper.find(".main-input-wrapper .btn-submit");
    await submitBtn.trigger("click");
    await flushPromises();

    expect(document.body.textContent).toContain("You're commenting too fast");
    expect(document.body.textContent).toContain("1 minute 15 seconds");
  });
});
