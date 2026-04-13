<script setup>
import { ref, inject, computed, watch, provide, onMounted } from "vue";
import DOMPurify from 'dompurify';
import { useRouter } from "vue-router";
import UserRole from "@/components/user/UserRole.vue";
import {
  voteComment as apiVoteComment,
  fetchCommentReplies,
  updateComment as apiUpdateComment,
  deleteComment as apiDeleteComment,
  formatCommentData,
} from "@/api/comments";
import { isLoggedIn, uid, userRole, userRoleId } from "@/stores/userStore";
import { timeAgo } from "@/utils/time";
import TextEditor from "./TextEditor.vue";
import ReportingModal from "../user/ReportingModal.vue";

const props = defineProps({
  comment: Object,
  depth: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(["deleted"]);

const router = useRouter();

const localReplies = ref([]);
const isLoadingReplies = ref(false);
const isSubmittingReply = ref(false);
const hasFetched = ref(false);

const isVoting = ref(false);
const totalScore = ref(props.comment.score || 0);
const myVote = ref(Number(props.comment.myVote || 0));

const showReplies = ref(false);
const replyText = ref("");
const isHoveringToggle = ref(false);

const showReportModal = ref(false);
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);
const showOptionsMenu = ref(false);
const optionsMenuRef = ref(null);

const activeReplyId = inject("activeReplyId");
const submitReply = inject("submitReply");

const activeEditId = inject("activeEditId");
const openEditComment = inject("openEditComment");
const closeEditComment = inject("closeEditComment");
const markEditDirty = inject("markEditDirty");

const maxDepthContext = inject("maxDepthContext", null);
const autoExpandCommentId = inject("autoExpandCommentId", ref(null));

if (props.depth === 1) {
  provide("maxDepthContext", {
    parentId: props.comment.id,
    addReply: (newReplyData) => {
      localReplies.value.push({
        ...newReplyData,
        id: newReplyData.commentId,
        author: `${newReplyData.user.firstName} ${newReplyData.user.lastName}`,
        role: newReplyData.user.role,
        time: "Just now",
        text: newReplyData.content,
        replies: [],
      });
      showReplies.value = true;
      props.comment.replyCount = (props.comment.replyCount || 0) + 1;
    },
  });
}

const isReplying = computed(() => activeReplyId.value === props.comment.id);
const isEditing = computed(() => activeEditId?.value === props.comment.id);

const originalText = computed(() => props.comment.text || "");
const editText = ref(originalText.value);
const isSavingEdit = ref(false);
const showSaveConfirm = ref(false);
const editIsUploading = ref(false);
const replyIsUploading = ref(false);

const linkedImage = computed(() => {
  const sanitized = DOMPurify.sanitize(props.comment.text ?? '');
  return (
    sanitized.replace(
      /<img[^>]+src="([^"]+)"[^>]*\/?>/gi,
      (match, src) => {
        return `<a href="${src}" target="_blank" rel="noopener noreferrer">${match}</a>`;
      },
    ) ?? ""
  );
});

const isAuthor = computed(() => {
  const currentUid = Number(uid?.value ?? 0);
  const commentUserId = Number(
    props.comment.user?.userId ?? props.comment.userId ?? 0,
  );
  return currentUid > 0 && commentUserId === currentUid;
});

const currentUserRole = computed(() =>
  String(userRole?.value || "").toLowerCase(),
);

const isModeratorOrAdmin = computed(() => {
  return (
    currentUserRole.value === "moderator" || currentUserRole.value === "admin"
  );
});

const canEdit = computed(() => {
  return isAuthor.value || Number(userRoleId?.value ?? 0) >= 3;
});

const canDelete = computed(() => {
  return isAuthor.value || isModeratorOrAdmin.value;
});

const canReport = computed(() => {
  return isLoggedIn.value && !isAuthor.value && !isModeratorOrAdmin.value;
});

const toggleReply = () => {
  if (!isLoggedIn.value) {
    router.push("/login");
    return;
  }
  activeReplyId.value = isReplying.value ? null : props.comment.id;

  if (activeReplyId.value === props.comment.id) {
    const authorName =
      props.comment.author || props.comment.user?.firstName || "User";
    replyText.value = `@${authorName} `;
  } else {
    replyText.value = "";
  }
};

const startEdit = () => {
  if (!isEditing.value) {
    editText.value = originalText.value;
    openEditComment && openEditComment(props.comment.id);
  }
};

const cancelEdit = () => {
  editText.value = originalText.value;
  markEditDirty && markEditDirty(false);
  closeEditComment && closeEditComment();
};

const hasEditChanges = computed(
  () => editText.value.trim() !== originalText.value.trim(),
);

const saveEdit = () => {
  if (!hasEditChanges.value || isSavingEdit.value) return;
  showSaveConfirm.value = true;
};

const confirmSaveEdit = async () => {
  if (!hasEditChanges.value || isSavingEdit.value) {
    showSaveConfirm.value = false;
    return;
  }

  isSavingEdit.value = true;
  try {
    const data = await apiUpdateComment(
      props.comment.id,
      editText.value.trim(),
    );
    if (data && data.ok && data.comment) {
      props.comment.text = data.comment.content;
      if (props.comment.user && data.comment.user) {
        props.comment.user = data.comment.user;
      }
      if (typeof data.comment.updatedAt !== "undefined") {
        props.comment.updatedAt = data.comment.updatedAt;
        props.comment.wasEdited =
          data.comment.updatedAt !== null &&
          data.comment.updatedAt !== data.comment.createdAt;
      } else {
        props.comment.wasEdited = true;
      }
      markEditDirty && markEditDirty(false);
      closeEditComment && closeEditComment();
    }
  } catch (error) {
    alert("Failed to update comment.");
  } finally {
    isSavingEdit.value = false;
    showSaveConfirm.value = false;
  }
};

function getAvatarSrc(file) {
  return new URL(`../../assets/img/user-pfps-premade/${file}`, import.meta.url)
    .href;
}

const toggleRepliesDropdown = async () => {
  if (!showReplies.value && !hasFetched.value && props.comment.replyCount > 0) {
    isLoadingReplies.value = true;
    try {
      const data = await fetchCommentReplies(props.comment.id);
      if (data && data.ok) {
        localReplies.value = data.items.map(formatCommentData);
        hasFetched.value = true;
      }
    } catch (error) {
      console.error("Failed to load replies:", error);
    } finally {
      isLoadingReplies.value = false;
    }
  }
  showReplies.value = !showReplies.value;
};

const handleReply = async () => {
  if (isSubmittingReply.value) return;

  const targetParentId =
    props.depth >= 2 && maxDepthContext
      ? maxDepthContext.parentId
      : props.comment.id;

  isSubmittingReply.value = true;
  const newCommentData = await submitReply(replyText.value, targetParentId);
  isSubmittingReply.value = false;

  if (newCommentData) {
    replyText.value = "";

    if (props.depth >= 2 && maxDepthContext) {
      maxDepthContext.addReply(newCommentData);
      activeReplyId.value = null;
    } else {
      props.comment.replyCount = (props.comment.replyCount || 0) + 1;

      if (hasFetched.value) {
        localReplies.value.push({
          ...newCommentData,
          id: newCommentData.commentId,
          author: `${newCommentData.user.firstName} ${newCommentData.user.lastName}`,
          role: newCommentData.user.role,
          time: "Just now",
          text: newCommentData.content,
          replies: [],
        });

        showReplies.value = true;
      }
    }
  }
};

const openReportModal = () => {
  showReportModal.value = true;
};

const closeReportModal = () => {
  showReportModal.value = false;
};

const askDeleteComment = () => {
  showDeleteConfirm.value = true;
};

const confirmDeleteComment = async () => {
  if (isDeleting.value) return;

  isDeleting.value = true;
  try {
    const data = await apiDeleteComment(props.comment.id);
    if (data?.ok) {
      showDeleteConfirm.value = false;
      emit("deleted", props.comment.id);
    } else {
      alert(data?.error || "Failed to delete comment.");
    }
  } catch (error) {
    alert("Failed to delete comment.");
  } finally {
    isDeleting.value = false;
  }
};

const handleVote = async (direction) => {
  if (!isLoggedIn.value) {
    router.push("/login");
    return;
  }
  if (isVoting.value) return;

  let action = direction;
  if (
    (direction === "upvote" && myVote.value === 1) ||
    (direction === "downvote" && myVote.value === -1)
  ) {
    action = "clear";
  }

  isVoting.value = true;
  try {
    const data = await apiVoteComment(props.comment.id, action);
    if (data?.ok) {
      totalScore.value = data.score;
      myVote.value = Number(data.myVote ?? 0);
    }
  } catch (error) {
    console.error("Vote error:", error);
  } finally {
    isVoting.value = false;
  }
};

watch(autoExpandCommentId, async (id) => {
  if (id && id === props.comment.id && !showReplies.value) {
    await toggleRepliesDropdown();
  }
});

watch(isLoggedIn, (loggedIn) => {
  if (!loggedIn) myVote.value = 0;
});

watch(editText, (newVal) => {
  if (!isEditing.value) return;
  markEditDirty && markEditDirty(newVal.trim() !== originalText.value.trim());
});

watch(isEditing, (active) => {
  if (!active) {
    editText.value = originalText.value;
  }
});

const hasOptions = computed(
  () =>
    !props.comment.isDeleted &&
    (isAuthor.value || canDelete.value || canReport.value),
);

const toggleOptionsMenu = () => {
  showOptionsMenu.value = !showOptionsMenu.value;
};

const handleClickOutsideOptions = (e) => {
  if (optionsMenuRef.value && !optionsMenuRef.value.contains(e.target)) {
    showOptionsMenu.value = false;
  }
};

watch(showOptionsMenu, (val) => {
  if (val) {
    document.addEventListener("click", handleClickOutsideOptions, true);
  } else {
    document.removeEventListener("click", handleClickOutsideOptions, true);
  }
});
</script>

<template>
  <div class="comment-node mb-3 position-relative" :id="'comment-' + comment.id">
    <div
      v-if="localReplies.length || comment.replyCount > 0"
      class="thread-line"
      :class="{ 'highlighted-thread-bg': isHoveringToggle }"
      @click="toggleRepliesDropdown"
      title="Toggle replies"
    ></div>

    <div class="d-flex gap-3 gap-sm-2 position-relative">
      <div
        class="avatar-col d-flex flex-column align-items-center flex-shrink-0"
      >
        <div class="avatar-box shadow-sm overflow-hidden rounded-circle">
          <div v-if="comment.isDeleted" class="avatar-box deleted-avatar"></div>
          <img
            v-else
            :src="getAvatarSrc(comment.user?.avatar)"
            class="avatar-box"
            alt="user"
          />
        </div>
      </div>

      <div class="flex-grow-1 overflow-visible">
        <div class="d-flex align-items-center mb-1 gap-2 flex-wrap">
          <span
            v-if="comment.isDeleted"
            class="author-name text-truncate small fw-bold pe-2 text-muted fst-italic"
          >
            [deleted]
          </span>
          <RouterLink
            v-else
            style="text-decoration: none; color: inherit"
            :to="`/profile?id=${comment.user?.userId}`"
          >
            <span class="author-name text-truncate small fw-bold pe-2">{{
              comment.author
            }}</span>
            <UserRole :role="comment.user?.role" />
          </RouterLink>
          <span class="timestamp text-muted">
            {{ comment.time }}
            <span v-if="comment.wasEdited" class="edited-label ms-1">(edited)</span>
          </span>
          <div v-if="hasOptions" class="position-relative" ref="optionsMenuRef">
            <button
              class="comment-menu-btn border-0 bg-transparent p-0"
              type="button"
              @click.stop="toggleOptionsMenu"
            >
              <i class="pi pi-ellipsis-v"></i>
            </button>
            <Transition name="comment-menu-fade">
              <div v-if="showOptionsMenu" class="comment-menu-popup shadow-sm">
                <button
                  v-if="canEdit"
                  class="comment-menu-item d-flex align-items-center gap-2 w-100 border-0 bg-transparent px-3 py-2"
                  @click="
                    startEdit();
                    showOptionsMenu = false;
                  "
                >
                  <i class="pi pi-pencil comment-menu-icon"></i>
                  <span>Edit</span>
                </button>
                <button
                  v-if="canDelete"
                  class="comment-menu-item comment-menu-item-delete d-flex align-items-center gap-2 w-100 border-0 bg-transparent px-3 py-2"
                  @click="
                    askDeleteComment();
                    showOptionsMenu = false;
                  "
                >
                  <i class="pi pi-trash comment-menu-icon"></i>
                  <span>Delete</span>
                </button>
                <button
                  v-if="canReport"
                  class="comment-menu-item d-flex align-items-center gap-2 w-100 border-0 bg-transparent px-3 py-2"
                  @click="
                    openReportModal();
                    showOptionsMenu = false;
                  "
                >
                  <i class="pi pi-flag comment-menu-icon"></i>
                  <span>Report</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>

        <div v-if="isEditing" class="comment-body mb-2">
          <div class="border rounded-3 overflow-hidden bg-white">
            <TextEditor
              v-model="editText"
              v-model:isUploading="editIsUploading"
              :compact="true"
              :show-toolbar="true"
              placeholder="Edit your comment..."
            />
            <div
              class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2 pt-2 bg-white"
            >
              <button
                class="btn-cancel border-0 bg-transparent fw-bold small"
                type="button"
                @click="cancelEdit"
              >
                Cancel
              </button>
              <button
                class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                type="button"
                :disabled="!hasEditChanges || isSavingEdit || editIsUploading"
                @click="saveEdit"
              >
                {{ editIsUploading ? "Uploading..." : "Save" }}
              </button>
            </div>
          </div>
        </div>

        <div
          v-else-if="comment.isDeleted"
          class="comment-body mb-2 small text-muted fst-italic"
        >
          [deleted]
        </div>
        <div v-else class="comment-body mb-2 small" v-html="linkedImage"></div>

        <div
          v-if="!comment.isDeleted"
          class="d-flex align-items-center gap-3 gap-sm-2 flex-wrap"
        >
          <div
            class="vote-container d-flex align-items-center rounded-4 px-2 py-1"
          >
            <button
              @click="handleVote('upvote')"
              class="vote-btn-up pi pi-chevron-up border-0 bg-transparent p-0"
              :class="{ active: myVote === 1, 'is-voting': isVoting }"
            ></button>
            <span
              class="vote-count mx-2 fw-bold"
              :class="{
                upvoted: myVote === 1,
                downvoted: myVote === -1,
                'voting-bounce': isVoting,
              }"
              >{{ totalScore }}</span
            >
            <button
              @click="handleVote('downvote')"
              class="vote-btn-down pi pi-chevron-down border-0 bg-transparent p-0"
              :class="{ active: myVote === -1, 'is-voting': isVoting }"
            ></button>
          </div>

          <button
            class="action-btn border-0 bg-transparent fw-bold d-flex align-items-center gap-1 p-0"
            @click="toggleReply"
            :class="{ active: isReplying }"
          >
            <span>Reply</span>
          </button>
        </div>

        <div v-if="isReplying" class="mt-2">
          <div
            class="reply-box-container mb-2 border rounded-3 overflow-hidden bg-white"
          >
            <TextEditor
              v-model="replyText"
              v-model:isUploading="replyIsUploading"
              :compact="true"
              :show-toolbar="true"
              placeholder="Write a reply..."
            />
            <div
              class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2 pt-2 bg-white"
            >
              <button
                class="btn-cancel border-0 bg-transparent fw-bold small"
                @click="activeReplyId = null"
              >
                Cancel
              </button>
              <button
                class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                :disabled="
                  !replyText || replyText === '<p></p>' || replyIsUploading || isSubmittingReply
                "
                @click="handleReply"
              >
                {{ replyIsUploading ? "Uploading..." : isSubmittingReply ? "Posting..." : "Reply" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="localReplies.length || comment.replyCount > 0"
      class="replies-wrapper position-relative"
    >
      <div v-if="showReplies">
        <div
          v-for="(reply, index) in localReplies"
          :key="reply.id"
          class="reply-item mt-3 position-relative ps-3 ps-sm-2"
        >
          <div
            class="child-connector"
            :class="{ 'highlighted-thread-border': isHoveringToggle }"
          ></div>
          <SingleComment
            :comment="reply"
            :depth="depth + 1"
            :is-last-child="index === localReplies.length - 1"
            @deleted="emit('deleted', $event)"
          />
        </div>
      </div>

      <div
        class="position-relative ps-3 ps-sm-2 py-1 mt-2 d-flex align-items-center"
      >
        <div
          class="child-connector toggle-connector"
          :class="{ 'highlighted-thread-border': isHoveringToggle }"
        ></div>
        <button
          class="btn-toggle-replies border rounded-pill bg-transparent fw-bold d-flex align-items-center gap-2 px-3 py-1 ms-1"
          @mouseenter="isHoveringToggle = true"
          @mouseleave="isHoveringToggle = false"
          @click="toggleRepliesDropdown"
          :disabled="isLoadingReplies"
        >
          <i v-if="isLoadingReplies" class="pi pi-spin pi-spinner"></i>
          <i
            v-else
            :class="showReplies ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
          ></i>

          <span class="small">
            {{
              showReplies
                ? "Hide replies"
                : `View ${comment.replyCount || localReplies.length} replies`
            }}
          </span>
        </button>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showSaveConfirm"
          class="comment-modal-mask d-flex align-items-center justify-content-center"
          @click.self="showSaveConfirm = false"
        >
          <div class="comment-modal-card shadow-lg">
            <p class="fw-bold mb-1">Save changes?</p>
            <p class="small text-muted mb-3">
              This will update your comment for everyone viewing the discussion.
            </p>
            <div class="d-flex justify-content-end gap-2">
              <button
                type="button"
                class="btn-cancel border-0 bg-transparent fw-bold small"
                @click="showSaveConfirm = false"
              >
                Back
              </button>
              <button
                type="button"
                class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                :disabled="isSavingEdit"
                @click="confirmSaveEdit"
              >
                <span
                  v-if="isSavingEdit"
                  class="pi pi-spin pi-spinner me-1"
                ></span>
                Save changes
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showDeleteConfirm"
          class="comment-modal-mask d-flex align-items-center justify-content-center"
          @click.self="showDeleteConfirm = false"
        >
          <div class="comment-modal-card shadow-lg">
            <p class="fw-bold mb-1">Delete comment?</p>
            <p class="small text-muted mb-3">This action cannot be undone.</p>
            <div class="d-flex justify-content-end gap-2">
              <button
                type="button"
                class="btn-cancel border-0 bg-transparent fw-bold small"
                @click="showDeleteConfirm = false"
              >
                Cancel
              </button>
              <button
                type="button"
                class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                :disabled="isDeleting"
                @click="confirmDeleteComment"
              >
                <span
                  v-if="isDeleting"
                  class="pi pi-spin pi-spinner me-1"
                ></span>
                Delete
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <ReportingModal
      :isOpen="showReportModal"
      :targetId="comment.id"
      :targetTitle="comment.text?.replace(/<[^>]*>/g, '').trim() || 'Comment'"
      type="comment"
      @close="closeReportModal"
    />
  </div>
</template>

<style scoped>
.avatar-col {
  width: 40px;
  position: relative;
}

.avatar-box {
  width: 40px;
  height: 40px;
  position: relative;
  z-index: 0;
}

.deleted-avatar {
  background-color: #91959e;
}

.author-name:hover {
  color: #007a4c;
  transition: color 0.1s;
  text-decoration: underline;
}

:deep(.role-pill) {
  border-radius: 3px !important;
  padding: 1px 4px !important;
  font-size: 0.5rem !important;
}

.comment-body {
  overflow-wrap: break-word;
  word-break: break-word;
}

.comment-body :deep(img) {
  max-width: 100%;
  max-height: 400px;
  border-radius: 6px;
  display: block;
  margin-top: 4px;
  object-fit: contain;
  cursor: pointer;
}

.timestamp {
  font-size: 12px;
}

.edited-label {
  font-style: italic;
  opacity: 0.75;
  font-size: 11px;
}

/* Menu */
.comment-menu-btn {
  color: #9ca3af;
  transition: color 0.2s;
}

.comment-menu-btn:hover {
  color: #111827;
}

/* Voting styles */
.vote-btn-up,
.vote-btn-down {
  color: #bac7c4;
  font-size: 0.9rem;
  transition: color 0.2s;
}

.vote-btn-up:hover {
  color: #043927;
}

.vote-btn-down:hover {
  color: #5e2b2c;
}

.vote-count {
  font-size: 0.8rem;
  color: #1a1a1b;
  min-width: 14px;
  text-align: center;
}

.vote-count.upvoted {
  color: #043927;
}

.vote-count.downvoted {
  color: #5e2b2c;
}

/* Action buttons */
.action-btn {
  color: #035157;
  font-size: 0.85rem;
}

.action-btn:hover {
  color: #111827;
}

.btn-cancel {
  color: #4b5563;
}

.btn-submit {
  background: #035157;
  color: white;
}

.btn-submit:disabled {
  background-color: #03515788;
  color: #ffffff;
  cursor: not-allowed;
}

.thread-line {
  position: absolute;
  top: 40px;
  left: 19px;
  bottom: 30px;
  width: 1px;
  background-color: #c5cad3;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.replies-wrapper {
  margin-left: 19px;
}

.child-connector {
  position: absolute;
  top: 20px;
  left: 0;
  width: 12px;
  height: 24px;
  border-bottom: 1px solid #c5cad3;
  border-left: 1px solid #c5cad3;
  border-bottom-left-radius: 12px;
  margin-top: -12px;
  transition: border-color 0.2s ease;
}

.toggle-connector {
  top: 50% !important;
  height: 20px !important;
  margin-top: -20px !important;
}

.btn-toggle-replies {
  color: #035157;
  border-width: 1px !important;
  border-color: #c5cad3 !important;
  transition: border-color 0.2s ease;
}

.btn-toggle-replies:hover {
  border-color: #035157 !important;
}

.highlighted-thread-bg {
  background-color: #035157 !important;
}

.highlighted-thread-border {
  border-bottom-color: #035157 !important;
  border-left-color: #035157 !important;
}

@media (max-width: 599px) {
  .avatar-col {
    width: 32px;
  }

  .avatar-box {
    width: 32px;
    height: 32px;
  }

  .thread-line {
    top: 32px;
    left: 15px;
  }

  .replies-wrapper {
    margin-left: 15px;
  }

  .child-connector {
    width: 20px;
    top: 12px;
  }
}

.vote-btn-up.active {
  color: #043927 !important;
  transform: scale(1.2);
}

.vote-btn-down.active {
  color: #5e2b2c !important;
  transform: scale(1.2);
}

.is-voting {
  opacity: 0.5;
  pointer-events: none;
}

@keyframes count-bounce {
  0% {
    transform: translateY(0);
  }

  25% {
    transform: translateY(-3px);
  }

  50% {
    transform: translateY(2px);
  }

  100% {
    transform: translateY(0);
  }
}

.voting-bounce {
  animation: count-bounce 0.6s infinite ease-in-out;
  display: inline-block;
}

.comment-modal-mask {
  position: fixed;
  inset: 0;
  z-index: 1050;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
}

.comment-modal-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  max-width: 360px;
  width: 90%;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.comment-menu-popup {
  position: absolute;
  top: 100%;
  margin-top: 8px;
  right: 0;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  min-width: 140px;
  z-index: 100;
  overflow: hidden;
}

.comment-menu-item {
  font-size: 0.875rem;
  color: #374151;
  cursor: pointer;
  transition: background-color 0.15s ease;
  text-align: left;
}

.comment-menu-item:hover {
  background-color: #f3f4f6;
}

.comment-menu-item-delete {
  color: #b91c1c;
}

.comment-menu-item-delete:hover {
  background-color: #fef2f2;
}

.comment-menu-icon {
  font-size: 0.8rem;
  width: 14px;
  text-align: center;
}

.comment-menu-fade-enter-active,
.comment-menu-fade-leave-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}

.comment-menu-fade-enter-from,
.comment-menu-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
