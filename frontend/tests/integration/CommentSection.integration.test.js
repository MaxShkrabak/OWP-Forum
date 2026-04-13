import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { ref } from "vue";
import CommentSection from "@/components/forum/CommentSection.vue";
import { fetchComments, submitComment } from "@/api/comments";
import { timeAgo } from "@/utils/time";
import TextEditor from "@/components/forum/TextEditor.vue";

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: ref(true),
  uid: ref(1),
  userRole: ref("user"),
  userRoleId: ref(1),
}));

vi.mock("vue-router", () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ params: {}, query: {} }),
}));

vi.mock("@/api/comments", async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    fetchComments: vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        total: 1,
        items: [
          {
            commentId: 1,
            content: "My first comment!",
            createdAt: "2026-04-08 22:33:11",
            user: { userId: 2, firstName: "Jane", lastName: "Doe" },
          },
        ],
      })
      .mockResolvedValue({
        ok: true,
        total: 2,
        items: [
          {
            commentId: 99,
            content: "This is my newly created comment",
            createdAt: "2026-04-08 23:00:00",
            user: { userId: 1, firstName: "Test", lastName: "User" },
          },
          {
            commentId: 1,
            content: "My first comment!",
            createdAt: "2026-04-08 22:33:11",
            user: { userId: 2, firstName: "Jane", lastName: "Doe" },
          },
        ],
      }),
    submitComment: vi.fn(() =>
      Promise.resolve({
        ok: true,
        comment: {
          commentId: 99,
          content: "This is my newly created comment",
          createdAt: "2026-04-08 23:00:00",
          user: { userId: 1, firstName: "Test", lastName: "User" },
        },
      }),
    ),
  };
});

const POST_ID = 5;
const INITIAL_PAGE = 1;
const PAGE_LIMIT = 10;

describe("CommentSection - Create Comment and Refresh", () => {
  let wrapper;

  beforeEach(async () => {
    wrapper = mount(CommentSection, {
      props: { postId: POST_ID },
      global: {
        stubs: { RouterLink: true },
      },
    });
    await flushPromises();
  });

  afterEach(() => {
    wrapper.unmount();
    vi.clearAllMocks();
  });

  it("loads initial comments on mount with correct content and timestamp", () => {
    expect(fetchComments).toHaveBeenCalledWith(POST_ID, INITIAL_PAGE, PAGE_LIMIT, "latest");

    const comments = wrapper.findAllComponents({ name: "SingleComment" });
    expect(comments.length).toBe(1);

    expect(comments[0].find(".comment-body").text()).toBe("My first comment!");
    expect(comments[0].find(".timestamp").text()).toBe(timeAgo("2026-04-08 22:33:11"));
  });

  it("submits a new comment and shows it in the list after refresh with correct timestamp", async () => {
    await wrapper.find(".reply-box-container").trigger("click");

    const editor = wrapper.findComponent(TextEditor);
    await editor.vm.$emit("update:modelValue", "This is my newly created comment");

    const submitBtn = wrapper.find(".main-input-wrapper .btn-submit");
    expect(submitBtn.element.disabled).toBe(false);

    await submitBtn.trigger("click");
    await flushPromises();

    expect(submitComment).toHaveBeenCalledWith(POST_ID, "This is my newly created comment");

    const allComments = wrapper.findAllComponents({ name: "SingleComment" });
    expect(allComments.length).toBe(2);

    expect(allComments[0].find(".comment-body").text()).toBe("This is my newly created comment");
    expect(allComments[0].find(".timestamp").text()).toBe(timeAgo("2026-04-08 23:00:00"));
  });
});
