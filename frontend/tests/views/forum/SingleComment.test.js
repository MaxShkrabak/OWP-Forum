/**
 * @vitest-environment jsdom
 */

import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { nextTick } from "vue";

vi.mock("@/stores/userStore", async () => {
  const { ref } = await import("vue");
  return {
    uid: ref(2),
    isLoggedIn: ref(true),
    userRole: ref("Student"),
    userRoleId: ref(1),
  };
});

vi.mock("vue-router", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useRoute: () => ({ params: {}, query: {} }),
}));

import SingleComment from "@/components/forum/SingleComment.vue";
import * as userStore from "@/stores/userStore";

const mockComment = {
  id: 1,
  text: "Test comment",
  score: 0,
  myVote: 0,
  time: "Just now",
  author: "User One",
  isDeleted: false,
  replyCount: 0,
  user: {
    userId: 1,
    avatar: "default.png",
    role: "Student",
  },
};

function mountComment() {
  return mount(SingleComment, {
    props: {
      comment: mockComment,
    },
    global: {
      stubs: {
        RouterLink: {
          template: "<a><slot /></a>",
        },
        UserRole: true,
        TextEditor: true,
        ReportingModal: true,
        Teleport: true,
      },
      provide: {
        activeReplyId: { value: null },
        submitReply: vi.fn(),
        activeEditId: { value: null },
        openEditComment: vi.fn(),
        closeEditComment: vi.fn(),
        markEditDirty: vi.fn(),
        maxDepthContext: null,
      },
    },
  });
}

describe("SingleComment Admin Permissions", () => {
  beforeEach(() => {
    userStore.uid.value = 2;
    userStore.isLoggedIn.value = true;
    userStore.userRole.value = "Student";
    userStore.userRoleId.value = 1;
  });

  it("shows edit button for admin on another user's comment", async () => {
    userStore.userRole.value = "Admin";
    userStore.userRoleId.value = 4;

    const wrapper = mountComment();

    await wrapper.find(".comment-menu-btn").trigger("click");
    await nextTick();

    expect(wrapper.text()).toContain("Edit");
  });

  it("does not show edit button for normal user on another user's comment", async () => {
    userStore.userRole.value = "Student";
    userStore.userRoleId.value = 1;

    const wrapper = mountComment();

    await wrapper.find(".comment-menu-btn").trigger("click");
    await nextTick();

    expect(wrapper.text()).not.toContain("Edit");
  });
});
