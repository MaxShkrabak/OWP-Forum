<script setup>
import { ref, computed, provide, onMounted, onBeforeUnmount } from "vue";
import SingleComment from "./SingleComment.vue";

import {
  fetchComments as apiFetchComments,
  submitComment as apiSubmitComment,
  formatCommentData
} from '@/api/comments';
import {isLoggedIn } from '@/stores/userStore';

const props = defineProps({
  postId: {
    type: [Number, String],
    required: true,
  },
});

const flatCommentsList = ref([]);
const commentsTree = ref([]);
const isFocused = ref(false);
const newComment = ref("");
const activeReplyId = ref(null);
const activeEditId = ref(null);
const activeEditDirty = ref(false);
const pendingEditId = ref(null);
const showDiscardConfirm = ref(false);

const commentFeedbackModal = ref({
  open: false,
  title: "",
  type: null,
  limit: null,
  secondsLeft: null,
  fallbackMessage: "",
});

const countdownSeconds = ref(0);
let commentFeedbackTimer = null;

const formatWaitTime = (totalSeconds) => {
  const backendSeconds = Math.max(1, Math.ceil(Number(totalSeconds) || 0));
  const minutes = Math.floor(backendSeconds / 60);
  const seconds = backendSeconds % 60;

  if (minutes === 0) {
    return `${backendSeconds} second${backendSeconds === 1 ?"" : "s"}`;
  }

  if (seconds === 0){
    return `${minutes} minute${minutes === 1 ?"" : "s"}`;
  }
  
  return `${minutes} minute${minutes === 1 ?"" : "s"} ${seconds} second${seconds === 1 ?"" : "s"}`;
};

const stopCommentFeedbackCountdown=()=> {
  if (commentFeedbackTimer){
    clearInterval(commentFeedbackTimer);
    commentFeedbackTimer = null;
  }

  countdownSeconds.value = 0;
}

const startCommentFeedbackCountdown = (secondsLeft) => {
  stopCommentFeedbackCountdown();
  const initialSeconds = Math.max(1, Math.ceil(Number(secondsLeft) || 0));
  countdownSeconds.value = initialSeconds;
  const endsAt = Date.now() + initialSeconds *1000;

  const tick = () => {
    countdownSeconds.value = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));

    if (countdownSeconds.value <= 0){
      stopCommentFeedbackCountdown();
    }
  };

  tick();
  commentFeedbackTimer = window.setInterval(tick, 250);
};

const commentFeedbackMessage = computed(() => {
    const modal = commentFeedbackModal.value;
    
  if (!modal.open) {
    return "";
  }

    if (modal.type === "cooldown" && modal.secondsLeft !== null) {
      return countdownSeconds.value > 0
        ? `Please wait ${formatWaitTime(countdownSeconds.value)} before commenting again.`
        : "You can try commenting again now.";
    }

    if (modal.type === "hourly_limit" && modal.secondsLeft !== null) {
      const limitValue = Number(modal.limit) || 50;

      return countdownSeconds.value > 0
        ? `You've reached the ${limitValue} comments per hour limit. Try again in ${formatWaitTime(countdownSeconds.value)}.`
        : `You've reached the ${limitValue} comments per hour limit. You can try again now.`;
    }
  
  return modal.fallbackMessage;
});

const closeCommentFeedbackModal = () => {
  stopCommentFeedbackCountdown();

  commentFeedbackModal.value = {
    open: false,
    title: "",
    type: null,
    limit: null,
    secondsLeft: null,
    fallbackMessage: "",
  };
};

const openCommentFeedbackModal = (error, isReply = false) => {
  const response = error ?.response;
  const data = response ?.data ?? {};
  const rateLimit = data.rateLimit ?? {};
  const parseSecondsLeft = Number(rateLimit.secondsLeft);
  const hasSecondsLeft = Number.isFinite(parseSecondsLeft) && parseSecondsLeft > 0;
  const secondsLeft = hasSecondsLeft ? Math.ceil(parseSecondsLeft): null;
  const limitValue = Number(rateLimit.limit) || 50;

  stopCommentFeedbackCountdown();

  let title =  isReply ? "Unable to post reply" : "Unable to post comment";
  let type =  null;
  let fallbackMessage = data.error || "Please try again";

  if(response?.status === 429){
    type = typeof rateLimit.type === "string" ? rateLimit.type: null;

    if (type === "cooldown") {
      title = "You're commenting too fast";
      fallbackMessage = secondsLeft !== null
        ? `Please wait ${formatWaitTime(secondsLeft)} before commenting again.`
        : "Please wait a moment before commenting again.";
    } else if (type === "hourly_limit") {
      title = "Comment limit reached";
      fallbackMessage = secondsLeft !== null
        ? `You've reached the ${limitValue} comments per hour limit. Try again in ${formatWaitTime(secondsLeft)}.`
        : `You've reached the ${limitValue} comments per hour limit. Please try again soon.`;
    } else {
      title = "Comment restricted";
    }
  }

  commentFeedbackModal.value = {
    open: true,
    title,
    type,
    limit: limitValue,
    secondsLeft,
    fallbackMessage,
  };

  if (secondsLeft !== null){
    startCommentFeedbackCountdown(secondsLeft);
  }
};

const currentBatch = ref(1);
const commentsPerLoad = 10;
const hasMore = ref(true);
const isLoadingMore = ref(false);

const commentTotalCount = ref(0);

const sortOptions = [
  { label: 'Newest', value: 'latest' },
  { label: 'Oldest', value: 'oldest' },
  { label: 'Most Liked', value: 'mostLiked' }
];
const selectedSort = ref('latest');

provide('activeReplyId', activeReplyId);
provide('activeEditId', activeEditId);

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

provide('openEditComment', openEditComment);
provide('closeEditComment', closeEditComment);
provide('markEditDirty', markEditDirty);

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
      selectedSort.value
    );

    if (data && data.ok) {
      commentTotalCount.value = data.total || 0;

      if (
        flatCommentsList.value.length + data.items.length >=
        commentTotalCount.value
      ) {
        hasMore.value = false;
      }

      const formattedItems = data.items.map(formatCommentData);

      flatCommentsList.value = [...flatCommentsList.value, ...formattedItems];
      commentsTree.value = buildCommentTree(flatCommentsList.value);
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
  if (!newComment.value.trim()) return;
  try {
    const data = await apiSubmitComment(props.postId, newComment.value);
    if (data && data.ok) {
      newComment.value = "";
      isFocused.value = false;
      commentTotalCount.value++;

      const formatted = formatCommentData(data.comment);
      flatCommentsList.value.unshift(formatted);
      commentsTree.value = buildCommentTree(flatCommentsList.value);
    }
  } catch (error) {
    openCommentFeedbackModal(error);
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
    openCommentFeedbackModal(error, true);
    return false;
  }
};

provide("submitReply", submitReply);

const totalCommentsCount = computed(() => commentTotalCount.value);

const cancelComment = () => {
  newComment.value = "";
  isFocused.value = false;
};

onMounted(() => {
  loadComments();
});

onBeforeUnmount(() => {
  stopCommentFeedbackCountdown();
})

</script>

<template>
  <div class="comment-section bg-white text-start">
    <div
      class="comments-header d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 text-uppercase small"
    >
      <div class="d-flex align-items-center gap-2">
        <i class="pi pi-comments"></i>
        <span>Comments ({{ totalCommentsCount }})</span>
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
          <option v-for="option in sortOptions" :key="option.value" :value="option.value">
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
        >
          <textarea
            v-model="newComment"
            @focus="isFocused = true"
            :placeholder="isLoggedIn ? 'Add a comment...' : 'Sign in to comment'"
            :disabled="!isLoggedIn"
            class="comment-textarea w-100 border-0 p-3"
            rows="2"
          ></textarea>

          <div
            v-if="isFocused"
            class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2"
          >
            <button
              class="btn-cancel border-0 bg-transparent fw-bold"
              @click="cancelComment"
            >
              Cancel
            </button>
            <button
              class="btn-submit border-0 rounded-2 fw-bold px-4 py-2"
              :disabled="!newComment"
              @click="submitComment"
            >
              Comment
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
          v-if="commentFeedbackModal.open"
          class="comment-modal-mask d-flex align-items-center justify-content-center"
          @click.self="closeCommentFeedbackModal"
        >
          <div class="comment-modal-card shadow-lg">
            <p class="fw-bold mb-1">{{ commentFeedbackModal.title }}</p>
            <p class="small text-muted mb-3">{{ commentFeedbackMessage }}</p>
            <div class="d-flex justify-content-end">
              <button
                type="button"
                class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                @click="closeCommentFeedbackModal"
              >
                OK
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

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
              You have unsaved changes on another comment. If you continue, those changes will be lost.
            </p>
            <div class="d-flex justify-content-end gap-2">
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

.comment-textarea {
  outline: none;
  resize: vertical;
  font-size: 0.95rem;
  color: #1f2937;
  min-height: 80px;
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

.header-row {
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0.75rem;
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

@media (max-width: 599px) {
  .comment-textarea {
    font-size: 0.85rem;
    padding: 0.75rem !important;
  }
  .btn-submit {
    padding: 0.5rem 1rem !important;
    font-size: 0.8rem;
  }
}
</style>
