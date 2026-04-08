/**
 * @vitest-environment jsdom
 */

import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { nextTick } from "vue";

const { mockUid, mockIsLoggedIn, mockUserRole, mockUserRoleId } = vi.hoisted(() => ({
  mockUid: { value: 2 },
  mockIsLoggedIn: { value: true },
  mockUserRole: { value: "Student" },
  mockUserRoleId: { value: 1 },
}));

vi.mock("@/stores/userStore", () => ({
  uid: mockUid,
  isLoggedIn: mockIsLoggedIn,
  userRole: mockUserRole,
  userRoleId: mockUserRoleId,
}));

import SingleComment from "@/components/forum/SingleComment.vue";

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
    mockUid.value = 2;
    mockIsLoggedIn.value = true;
    mockUserRole.value = "Student";
    mockUserRoleId.value = 1;
  });

  it("shows edit button for admin on another user's comment", async () => {
    mockUserRole.value = "Admin";
    mockUserRoleId.value = 4;

    const wrapper = mountComment();

    await wrapper.find(".comment-menu-btn").trigger("click");
    await nextTick();

    expect(wrapper.text()).toContain("Edit");
  });

  it("does not show edit button for normal user on another user's comment", async () => {
    mockUserRole.value = "Student";
    mockUserRoleId.value = 1;

    const wrapper = mountComment();

    await wrapper.find(".comment-menu-btn").trigger("click");
    await nextTick();

    expect(wrapper.text()).not.toContain("Edit");
  });
});