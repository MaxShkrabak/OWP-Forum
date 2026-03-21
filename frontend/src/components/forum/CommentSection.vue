<script setup>
import { ref, provide, onMounted } from "vue";
import { useRouter } from "vue-router";
import SingleComment from "./SingleComment.vue";
import CommentEditor from "./TextEditor.vue";

import {
  fetchComments as apiFetchComments,
  submitComment as apiSubmitComment,
  formatCommentData,
} from "@/api/comments";
import { uid, isLoggedIn } from "@/stores/userStore";

const props = defineProps({
  postId: {
    type: [Number, String],
    required: true,
  },
});

const router = useRouter();

const flatCommentsList = ref([]);
const commentsTree = ref([]);
const isFocused = ref(false);
const newComment = ref("");
const activeReplyId = ref(null);
const activeEditId = ref(null);
const activeEditDirty = ref(false);
const pendingEditId = ref(null);
const showDiscardConfirm = ref(false);

const currentBatch = ref(1);
const commentsPerLoad = 10;
const hasMore = ref(true);
const isLoadingMore = ref(false);

const commentTotalCount = ref(0);
const isUploading = ref(false);
const editorRef = ref(null);

const sortOptions = [
  { label: "Newest", value: "latest" },
  { label: "Oldest", value: "oldest" },
  { label: "Most Liked", value: "mostLiked" },
];
const selectedSort = ref("latest");

provide("activeReplyId", activeReplyId);
provide("activeEditId", activeEditId);

const openEditComment = (commentId) => {
  if (activeEditId.value === commentId) return;

  if (activeEditId.value !== null && activeEditDirty.value) {
    pendingEditId.value = commentId;
    showDiscardConfirm.value = true;
    return;
  }

  activeEditId.value = commentId;
  activeEditDirty.value = false;
};

const closeEditComment = () => {
  activeEditId.value = null;
  activeEditDirty.value = false;
};

const markEditDirty = (dirty) => {
  activeEditDirty.value = !!dirty;
};

const confirmSwitchEdit = () => {
  if (pendingEditId.value === null) {
    showDiscardConfirm.value = false;
    return;
  }
  activeEditId.value = pendingEditId.value;
  activeEditDirty.value = false;
  pendingEditId.value = null;
  showDiscardConfirm.value = false;
};

const cancelSwitchEdit = () => {
  pendingEditId.value = null;
  showDiscardConfirm.value = false;
};

provide("openEditComment", openEditComment);
provide("closeEditComment", closeEditComment);
provide("markEditDirty", markEditDirty);

const buildCommentTree = (flatComments) => {
  const map = new Map();
  const tree = [];

  flatComments.forEach((comment) => {
    map.set(comment.id, comment);
  });

  flatComments.forEach((comment) => {
    if (comment.parentCommentId) {
      const parent = map.get(comment.parentCommentId);
      if (parent && !parent.replies.some((r) => r.id === comment.id)) {
        parent.replies.push(map.get(comment.id));
      }
    } else {
      tree.push(map.get(comment.id));
    }
  });

  return tree;
};

const handleCommentBoxClick = () => {
  if (isLoggedIn.value) {
    isFocused.value = true;
  } else {
    router.push("/login");
  }
};

const loadComments = async (isInitial = true) => {
  if (isInitial) {
    currentBatch.value = 1;
    flatCommentsList.value = [];
    hasMore.value = true;
  }

  isLoadingMore.value = true;

  try {
    const data = await apiFetchComments(
      props.postId,
      currentBatch.value,
      commentsPerLoad,
      selectedSort.value,
    );

    if (data && data.ok) {
      commentTotalCount.value = data.total || 0;

      const formattedItems = data.items.map(formatCommentData);
      flatCommentsList.value = [...flatCommentsList.value, ...formattedItems];
      commentsTree.value = buildCommentTree(flatCommentsList.value);

      if (
        data.items.length < commentsPerLoad ||
        currentBatch.value * commentsPerLoad >= commentTotalCount.value ||
        flatCommentsList.value.length >= commentTotalCount.value
      ) {
        hasMore.value = false;
      }
    }
  } catch (error) {
    console.error("Load error:", error);
  } finally {
    isLoadingMore.value = false;
  }
};

const handleSortChange = async () => {
  await loadComments(true);
};

const handleLoadMore = async () => {
  currentBatch.value++;
  await loadComments(false);
};

const submitComment = async () => {
  const cleanContent = newComment.value.replace(/(<([^>]+)>)/gi, "").trim();
  if (!cleanContent) return;

  try {
    const data = await apiSubmitComment(props.postId, newComment.value);
    if (data && data.ok) {
      newComment.value = "";
      editorRef.value?.clearContent();
      isFocused.value = false;
      commentTotalCount.value++;

      const formatted = formatCommentData(data.comment);
      flatCommentsList.value.unshift(formatted);
      commentsTree.value = buildCommentTree(flatCommentsList.value);
    }
  } catch (error) {
    alert("Failed to post comment.");
  }
};

const submitReply = async (replyContent, parentCommentId) => {
  if (!replyContent.trim()) return false;
  try {
    const data = await apiSubmitComment(
      props.postId,
      replyContent,
      parentCommentId,
    );
    if (data && data.ok) {
      activeReplyId.value = null;
      commentTotalCount.value++;
      return data.comment;
    }
    return false;
  } catch (error) {
    alert("Failed to post reply.");
    return false;
  }
};

provide("submitReply", submitReply);

const cancelComment = () => {
  newComment.value = "";
  editorRef.value?.clearContent();
  isFocused.value = false;
};

onMounted(() => {
  loadComments();
});
</script>

<template>
  <div class="comment-section bg-white text-start">
    <div
      class="comments-header d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 text-uppercase small"
    >
      <div class="d-flex align-items-center gap-2">
        <i class="pi pi-comments"></i>
        <span>Comments ({{ commentTotalCount }})</span>
      </div>

      <div class="sort-dropdown d-inline-flex align-items-center">
        <i class="pi pi-sort-alt d-sm-none me-1"></i>
        <span class="sort-label d-none d-sm-inline-block me-2">Sort:</span>
        <select
          id="comment-sort"
          v-model="selectedSort"
          @change="handleSortChange"
          class="sort-select"
        >
          <option
            v-for="option in sortOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <div class="p-3 p-md-4">
      <div class="main-input-wrapper mb-4">
        <div
          class="reply-box-container border rounded-3 overflow-hidden bg-white"
          :class="{ 'focused-border': isFocused }"
          :style="{ cursor: !isLoggedIn ? 'pointer' : 'text' }"
          @click="handleCommentBoxClick()"
        >
          <CommentEditor
            ref="editorRef"
            v-model="newComment"
            v-model:isUploading="isUploading"
            :disabled="!isLoggedIn"
            :compact="true"
            :show-toolbar="isFocused"
            :placeholder="
              isLoggedIn ? 'Add a comment...' : 'Sign in to comment'
            "
          />

          <div
            v-if="isFocused"
            class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2 pt-2"
          >
            <button
              class="btn-cancel border-0 bg-transparent fw-bold"
              @click.stop="cancelComment"
            >
              Cancel
            </button>
            <button
              class="btn-submit border-0 rounded-2 fw-bold px-4 py-2"
              :disabled="!newComment || newComment === '<p></p>' || isUploading"
              @click.stop="submitComment"
            >
              {{ isUploading ? "Uploading..." : "Comment" }}
            </button>
          </div>
        </div>
      </div>

      <div class="comments-container">
        <SingleComment
          v-for="comment in commentsTree"
          :key="comment.id"
          :comment="comment"
        />
      </div>

      <div v-if="hasMore" class="mt-4">
        <button
          @click="handleLoadMore"
          :disabled="isLoadingMore"
          class="load-more-btn w-100 border py-2 rounded-3 fw-bold bg-transparent d-flex align-items-center justify-content-center gap-2"
        >
          <i v-if="isLoadingMore" class="pi pi-spin pi-spinner"></i>
          <span>{{ isLoadingMore ? "Loading..." : "Show more comments" }}</span>
        </button>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showDiscardConfirm"
          class="comment-modal-mask d-flex align-items-center justify-content-center"
          @click.self="cancelSwitchEdit"
        >
          <div class="comment-modal-card shadow-lg">
            <p class="fw-bold mb-1">Discard unsaved changes?</p>
            <p class="small text-muted mb-3">
              You have unsaved changes on another comment. If you continue,
              those changes will be lost.
            </p>
            <div class="d-flex justify-content-end gap-2">
              <button
                type="button"
                class="btn-cancel border-0 bg-transparent fw-bold small"
                @click="cancelSwitchEdit"
              >
                Back
              </button>
              <button
                type="button"
                class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                @click="confirmSwitchEdit"
              >
                Discard & switch
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.comments-header {
  background: #f0f7f3;
  border-bottom: 1px solid #cce3d6;
  font-weight: 800;
  color: #1e4d38;
}

.reply-box-container {
  transition: border-color 0.2s;
  border-color: #03515752 !important;
}
.focused-border {
  border-color: #035157 !important;
}

.btn-cancel {
  color: #4b5563;
  font-size: 0.9rem;
}

.btn-submit {
  background: #035157;
  color: white;
  font-size: 0.9rem;
}

.btn-submit:disabled {
  background-color: #03515769 !important;
  cursor: not-allowed;
}

.load-more-btn {
  border-color: #004750 !important;
  color: #004750;
  transition: 0.2s;
}

.load-more-btn:hover {
  background: rgba(0, 71, 80, 0.05) !important;
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

.sort-dropdown {
  background-color: rgba(30, 77, 56, 0.06); /* Subtle green tint */
  border: 1px solid #cce3d6;
  border-radius: 6px;
  padding: 0.35rem 0.5rem 0.35rem 0.75rem;
  color: #1e4d38;
  transition: all 0.2s ease;
}

.sort-dropdown:hover {
  background-color: rgba(30, 77, 56, 0.1);
  border-color: #8aab97;
}

.sort-label {
  font-size: 0.75rem;
  font-weight: 800;
  color: #1e4d38;
}

.sort-dropdown i {
  font-size: 0.8rem;
  color: #1e4d38;
}

.sort-select {
  background-color: transparent;
  border: none;
  outline: none;
  font-size: 0.75rem;
  font-weight: 700;
  color: #1e4d38;
  cursor: pointer;
  text-transform: uppercase;
  padding-right: 0.25rem;
}

/* Keeps the actual dropdown options legible */
.sort-select option {
  color: #1f2937;
  text-transform: none;
  font-weight: normal;
}
</style>
