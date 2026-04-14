/**
 * ViewPost — unit tests.
 * Covers:
 * - renders post title, author, category, tags, and date
 * - view count formatting (plural, singular, locale-grouped large numbers)
 * - hides view count element when viewCount is absent
 * - Share button copies URL to clipboard and shows a toast
 * - shows error empty-state when post fetch fails
 */
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

const stubs = {
  RouterLink: { template: "<a><slot /></a>" },
  RouterView: { template: "<div />" },
  UserRole: true,
  ViewPostContent: true,
  PostModerationSidebar: true,
  CommentSection: true,
};

describe("ViewPost.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal("navigator", {
      ...navigator,
      clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
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
      tags: [{ tagId: 1, name: "Help" }, { tagId: 2, name: "Official" }, { tagId: 3, name: "Research" }],
      content: "Some content",
      viewCount: 1247,
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
    const renderedTagNames = renderedTags.map((t) => t.text());
    expect(renderedTags.length).toBe(3);
    expect(renderedTagNames).toContain("Help");
    expect(renderedTagNames).toContain("Official");
    expect(renderedTagNames).toContain("Research");

    expect(wrapper.find(".post-timestamp").text()).toContain("Feb 22, 2026");

    const viewLine = wrapper.find(".post-view-count");
    expect(viewLine.exists()).toBe(true);
    expect(viewLine.text()).toContain("1,247");
    expect(viewLine.text()).toContain("views");
  });

  it("shows singular 'view' when viewCount is 1", async () => {
    getPost.mockResolvedValue({
      title: "T",
      authorName: "A",
      authorRole: "User",
      authorAvatar: "pfp-0.png",
      createdAt: "2026-02-22 14:20:00",
      categoryName: "General",
      tags: [],
      content: "c",
      viewCount: 1,
    });

    const wrapper = mount(ViewPost, { global: { stubs } });
    await flushPromises();

    const viewLine = wrapper.find(".post-view-count");
    expect(viewLine.text()).toMatch(/1/);
    expect(viewLine.text()).toContain("view");
    expect(viewLine.text()).not.toContain("views");
  });

  it("formats large view counts with locale grouping", async () => {
    getPost.mockResolvedValue({
      title: "T",
      authorName: "A",
      authorRole: "User",
      authorAvatar: "pfp-0.png",
      createdAt: "2026-02-22 14:20:00",
      categoryName: "General",
      tags: [],
      content: "c",
      viewCount: 1000000,
    });

    const wrapper = mount(ViewPost, { global: { stubs } });
    await flushPromises();

    expect(wrapper.find(".post-view-count").text()).toContain(
      Number(1000000).toLocaleString(),
    );
  });

  it("does not render view count when viewCount is absent", async () => {
    getPost.mockResolvedValue({
      title: "T",
      authorName: "A",
      authorRole: "User",
      authorAvatar: "pfp-0.png",
      createdAt: "2026-02-22 14:20:00",
      categoryName: "General",
      tags: [],
      content: "c",
    });

    const wrapper = mount(ViewPost, { global: { stubs } });
    await flushPromises();

    expect(wrapper.find(".post-view-count").exists()).toBe(false);
  });

  it("Share copies the page URL and shows Link copied", async () => {
    const fakePost = {
      PostID: 123,
      title: "T",
      authorName: "A",
      authorRole: "user",
      authorAvatar: "pfp-1.png",
      createdAt: "2026-02-22 14:20:00",
      categoryName: "General",
      tags: [],
      content: "c",
    };

    getPost.mockResolvedValue(fakePost);

    const wrapper = mount(ViewPost, {
      global: { stubs },
    });

    await flushPromises();

    const shareBtn = wrapper.find(".share-btn");
    expect(shareBtn.exists()).toBe(true);

    await shareBtn.trigger("click");
    await flushPromises();

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      window.location.href,
    );

    expect(wrapper.find(".link-copied-toast").text()).toContain("Link copied");
  });

  it("shows error state if post fetch fails", async () => {
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
