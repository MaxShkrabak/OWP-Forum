import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";

// 1) Mock vue-router used by ViewPost.vue
vi.mock("vue-router", () => ({
  useRoute: () => ({ params: { id: "123" } }),
  useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
}));

// 2) Mock API call used onMounted
const getPostMock = vi.fn();
vi.mock("@/api/posts", () => ({
  getPost: (...args) => getPostMock(...args),
  votePost: vi.fn(),
}));

// 3) Mock user store refs used by ViewPost.vue
const isLoggedIn = ref(true);
const userRole = ref("user");
const userRoleId = ref(1);
const uid = ref(0);

vi.mock("@/stores/userStore", () => ({
  isLoggedIn,
  userRole,
  userRoleId,
  uid,
}));

// IMPORTANT: import component AFTER mocks
import ViewPost from "@/views/forum/ViewPost.vue";

// 4) Stub child components (we only care about buttons)
const stubs = {
  ViewPostHeader: { template: "<div />" },
  ViewPostContent: { template: "<div />" },
  CommentSection: { template: "<div />" },
  CreatePostModal: { template: "<div />" },
};

// Helper to create post data
function makePost(authorId) {
  return {
    PostID: 123,
    authorId,
    myVote: 0,
    TotalScore: 0,
    content: "<p>hi</p>",
  };
}

async function mountAndWait() {
  const wrapper = mount(ViewPost, {
    global: { stubs },
  });
  await flushPromises();
  return wrapper;
}

describe("ViewPost - Edit/Delete visibility by role", () => {
  beforeEach(() => {
    vi.clearAllMocks();

    // default: logged-in normal user
    isLoggedIn.value = true;
    userRole.value = "user";
    userRoleId.value = 1; // < 3 => not admin/mod
    uid.value = 10;

    // default post: someone else is author
    getPostMock.mockResolvedValue(makePost(999));
  });

  it("Author sees Edit + Delete (full), no metadata button", async () => {
    uid.value = 42;
    getPostMock.mockResolvedValue(makePost(42));

    const wrapper = await mountAndWait();

    expect(wrapper.text()).toContain("Edit Post");
    expect(wrapper.text()).toContain("Delete Post");
    expect(wrapper.text()).not.toContain("Update Category & Tags");
  });

  it("Non-author normal user sees no moderation buttons", async () => {
    uid.value = 10;
    userRoleId.value = 1;
    getPostMock.mockResolvedValue(makePost(999));

    const wrapper = await mountAndWait();

    expect(wrapper.text()).not.toContain("Edit Post");
    expect(wrapper.text()).not.toContain("Delete Post");
    expect(wrapper.text()).not.toContain("Update Category & Tags");
  });

  it("Admin/Moderator non-author sees Delete + Metadata, no Edit", async () => {
    uid.value = 10;
    userRoleId.value = 3; // >=3 => admin/mod in your code
    getPostMock.mockResolvedValue(makePost(999));

    const wrapper = await mountAndWait();

    expect(wrapper.text()).not.toContain("Edit Post");
    expect(wrapper.text()).toContain("Delete Post");
    expect(wrapper.text()).toContain("Update Category & Tags");
  });
});