import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import ViewPost from "@/views/forum/ViewPost.vue";
import { getPost } from "@/api/posts";

vi.mock("vue-router", async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    useRoute: () => ({ params: { id: "123" } }),
    useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
  };
});

vi.mock("@/api/posts", () => ({
  getPost: vi.fn(),
}));

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: true,
  userRole: "user",
  userRoleId: 1,
  uid: 10,
}));

const stubs = [
  "UserRole",
  "ViewPostContent",
  "PostModerationSidebar",
  "CommentSection",
];

describe("ViewPost.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders the correct title, author, role, date, and tags of a post", async () => {
    const fakePost = {
      PostID: 123,
      title: "My first post!",
      authorName: "Bobby Bill",
      authorRole: "Moderator",
      authorAvatar: "pfp-1.png",
      createdAt: "2026-02-22 14:20:00",
      categoryName: "General",
      tags: [{ Name: "Help" }, { Name: "Official" }, { Name: "Research" }],
      content: "Some content",
    };

    getPost.mockResolvedValue(fakePost);

    const wrapper = mount(ViewPost, {
      global: { stubs },
    });

    await flushPromises();

    expect(wrapper.find(".post-title").text()).toBe(fakePost.title);
    expect(wrapper.find(".author-name").text()).toBe(fakePost.authorName);
    expect(wrapper.find(".category-label").text()).toContain("General");

    const renderedTags = wrapper.findAll(".post-tag");
    expect(renderedTags.length).toBe(3);
    expect(renderedTags[0].text()).toBe("Help");
    expect(renderedTags[1].text()).toBe("Official");
    expect(renderedTags[2].text()).toBe("Research");

    expect(wrapper.find(".post-timestamp").text()).toContain("Feb 22, 2026");
  });

  it("shows error state if post fetch fails", async () => {
    // component expects an error in the console if fetch fails
    const spy = vi.spyOn(console, "error").mockImplementation(() => {});

    getPost.mockRejectedValue(new Error("Failed"));
    const wrapper = mount(ViewPost, { global: { stubs } });
    await flushPromises();
    expect(wrapper.find(".empty-state").text()).toContain(
      "This post has been deleted or does not exist",
    );
    spy.mockRestore();
  });
});
