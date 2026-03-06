import { mount } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";

vi.mock("vue-router", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/api/posts", () => ({ votePost: vi.fn() }));
vi.mock("@/api/client", () => ({ default: { delete: vi.fn() } }));

vi.mock("@/stores/userStore", async () => {
  const { ref } = await import("vue");
  return {
    isLoggedIn: ref(true),
    userRole: ref("user"),
    userRoleId: ref(1),
    uid: ref(0),
  };
});

import { userRoleId, uid } from "@/stores/userStore";
import PostModerationSidebar from "@/components/admin/PostModerationSidebar.vue";

const STUBS = ["CreatePostModal", "ReportingModal"];

function mountSidebar(authorId) {
  return mount(PostModerationSidebar, {
    props: { post: { PostID: 123, authorId, myVote: 0, TotalScore: 0 } },
    global: { stubs: STUBS },
  });
}

describe("PostModerationSidebar.vue", () => {
  it("shows Edit Post and Delete to the author", () => {
    uid.value = 42;
    userRoleId.value = 1;
    const buttons = mountSidebar(42)
      .findAll("button")
      .map((b) => b.text());
    expect(buttons).toContain("Edit Post");
    expect(buttons).toContain("Delete");
    expect(buttons).not.toContain("Edit");
  });

  it("shows no edit/delete buttons to a non-author", () => {
    uid.value = 10;
    userRoleId.value = 1;
    const buttons = mountSidebar(999)
      .findAll("button")
      .map((b) => b.text());
    expect(buttons).not.toContain("Edit Post");
    expect(buttons).not.toContain("Edit");
    expect(buttons).not.toContain("Delete");
  });

  it("shows Edit and Delete to an admin/mod who is not the author", () => {
    uid.value = 10;
    userRoleId.value = 3;
    const buttons = mountSidebar(999)
      .findAll("button")
      .map((b) => b.text());
    expect(buttons).not.toContain("Edit Post");
    expect(buttons).toContain("Edit");
    expect(buttons).toContain("Delete");
  });
});
