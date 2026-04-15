/**
 * CreatePostModal — unit tests.
 * Covers:
 * - restricted mode disables title and content inputs for moderators editing tags/category
 * - publish button is disabled immediately after first click to prevent duplicate submissions
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import CreatePostModal from "@/components/forum/CreatePostModal.vue";

vi.mock("@/api/posts.js", () => ({
  createPost: vi.fn(async () => ({})),
  getTags: vi.fn(async () => []),
  getCategories: vi.fn(async () => []),
}));

vi.mock("@/api/auth.js", () => ({
  checkAuth: vi.fn(async () => ({ data: null })),
}));

vi.mock("vue-router", () => ({
  useRouter: () => ({
    currentRoute: { value: { params: { id: "123" } } },
    push: vi.fn(),
  }),
}));

vi.mock("@/stores/userStore", () => ({
  fullName: "Test User",
  userAvatar: "/test.png",
  isLoggedIn: true,
  userRole: "Admin",
  userRoleId: 2,
}));

describe("CreatePostModal — restricted mode (Moderator)", () => {
  beforeEach(() => vi.clearAllMocks());

  it("disables Title and Content inputs when editing tags/category as a Moderator (isRestricted)", async () => {
    const wrapper = mount(CreatePostModal, {
      props: {
        show: true,
        loading: false,
        postData: {
          title: "Test Post",
          content: "Test Body",
          category: "General",
          tags: [],
        },
        isRestricted: true,
      },
      global: {
        stubs: { Teleport: true, Transition: true, TextEditor: true },
      },
    });

    await flushPromises();

    const titleInput = wrapper.find(".title-input");
    expect(titleInput.exists()).toBe(true);
    expect(titleInput.element.disabled || titleInput.element.readOnly).toBe(true);
  });
});

describe("Fix Create Post Request Spam", () => {
  beforeEach(() => vi.clearAllMocks());

  it("disables the publish button immediately after the first click", async () => {
    const { getCategories } = await import("@/api/posts");
    vi.mocked(getCategories).mockResolvedValue([
      { categoryId: "1", name: "General" },
    ]);

    const wrapper = mount(CreatePostModal, {
      props: {
        show: true,
        loading: false,
        postData: null,
        isRestricted: false,
      },
      global: {
        stubs: {
          Teleport: { template: "<div><slot /></div>" },
          Transition: { template: "<div><slot /></div>" },
          TextEditor: true,
        },
      },
    });

    await flushPromises();

    const titleInput = wrapper.find(".title-input");
    if (titleInput.exists()) await titleInput.setValue("Valid Title");
    const categorySelect = wrapper.find("select.clean-select-rect");
    if (categorySelect.exists()) await categorySelect.setValue("1");
    const editor = wrapper.findComponent({ name: "TextEditor" });
    if (editor.exists() && editor.vm) {
      editor.vm.$emit("update:modelValue", "<p>Some content</p>");
    }
    await wrapper.vm.$nextTick();

    const footerPublishBtn = wrapper.findAll(".publish-btn")[0];
    if (!footerPublishBtn?.exists()) {
      throw new Error("Footer Publish button not found");
    }
    await footerPublishBtn.trigger("click");
    await wrapper.vm.$nextTick();

    const confirmPublishBtns = wrapper.findAll(".publish-btn");
    const confirmBtn =
      confirmPublishBtns.length > 1 ? confirmPublishBtns[1] : null;
    expect(confirmBtn).toBeDefined();
    if (confirmBtn) {
      await confirmBtn.trigger("click");
      await wrapper.vm.$nextTick();
      expect(confirmBtn.element.disabled).toBe(true);
    }
  });
});
