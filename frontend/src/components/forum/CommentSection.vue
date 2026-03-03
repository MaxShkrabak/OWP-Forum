<script setup>
import { ref, computed, provide, onMounted } from 'vue';
import SingleComment from './SingleComment.vue';

import {
  fetchComments as apiFetchComments,
  submitComment as apiSubmitComment,
  formatCommentData
} from '@/api/comments';
import { uid } from '@/stores/userStore';

const props = defineProps({
  postId: {
    type: [Number, String],
    required: true
  }
});

const flatCommentsList = ref([]);
const commentsTree = ref([]);
const isFocused = ref(false);
const newComment = ref('');
const activeReplyId = ref(null);
const activeEditId = ref(null);
const activeEditDirty = ref(false);

const currentBatch = ref(1);
const commentsPerLoad = 10;
const hasMore = ref(true);
const isLoadingMore = ref(false);

const commentTotalCount = ref(0);

provide('activeReplyId', activeReplyId);
provide('activeEditId', activeEditId);

const openEditComment = (commentId) => {
  if (activeEditId.value === commentId) return;

  if (activeEditId.value !== null && activeEditDirty.value) {
    const confirmDiscard = window.confirm(
      'You have unsaved changes on another comment. Discard them and edit this comment instead?',
    );
    if (!confirmDiscard) return;
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

provide('openEditComment', openEditComment);
provide('closeEditComment', closeEditComment);
provide('markEditDirty', markEditDirty);

const buildCommentTree = (flatComments) => {
  const map = new Map();
  const tree = [];

  flatComments.forEach(comment => {
    map.set(comment.id, comment);
  });

  flatComments.forEach(comment => {
    if (comment.parentCommentId) {
      const parent = map.get(comment.parentCommentId);
      if (parent && !parent.replies.some(r => r.id === comment.id)) {
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
    const data = await apiFetchComments(props.postId, currentBatch.value, commentsPerLoad);

    if (data && data.ok) {
      commentTotalCount.value = data.total || 0;

      if (flatCommentsList.value.length + data.items.length >= commentTotalCount.value) {
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

const handleLoadMore = async () => {
  currentBatch.value++;
  await loadComments(false);
};

const submitComment = async () => {
  if (!newComment.value.trim()) return;
  try {
    const data = await apiSubmitComment(props.postId, newComment.value);
    if (data && data.ok) {
      newComment.value = '';
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
    const data = await apiSubmitComment(props.postId, replyContent, parentCommentId);
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

provide('submitReply', submitReply);

const totalCommentsCount = computed(() => commentTotalCount.value);

const cancelComment = () => {
  newComment.value = '';
  isFocused.value = false;
};

onMounted(() => {
  loadComments();
});
</script>

<template>
  <div class="comment-section p-4 rounded-3 border bg-white text-start">
    <h3 class="section-title fw-bold mb-4 pb-2 border-bottom d-inline-block">
      {{ totalCommentsCount }} Comments
    </h3>

    <div class="main-input-wrapper mb-4">
      <div class="reply-box-container border rounded-3 overflow-hidden bg-white"
        :class="{ 'focused-border': isFocused }">
        <textarea v-model="newComment" @focus="isFocused = true" placeholder="Add a comment..."
          class="comment-textarea w-100 border-0 p-3" rows="2"></textarea>

        <div v-if="isFocused" class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2">
          <button class="btn-cancel border-0 bg-transparent fw-bold" @click="cancelComment">Cancel</button>
          <button class="btn-submit border-0 rounded-2 fw-bold px-4 py-2" :disabled="!newComment"
            @click="submitComment">Comment</button>
        </div>
      </div>
    </div>

    <div class="comments-container">
      <SingleComment v-for="comment in commentsTree" :key="comment.id" :comment="comment" />
    </div>

    <div v-if="hasMore" class="mt-4">
      <button @click="handleLoadMore" :disabled="isLoadingMore"
        class="load-more-btn w-100 border py-2 rounded-3 fw-bold bg-transparent d-flex align-items-center justify-content-center gap-2">
        <i v-if="isLoadingMore" class="pi pi-spin pi-spinner"></i>
        <span>{{ isLoadingMore ? 'Loading...' : 'Show more comments' }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.section-title {
  color: #035157;
  border-bottom-color: #035157 !important;
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

@media (max-width: 599px) {
  .comment-section { padding: 1rem !important; }
  .comment-textarea { font-size: 0.85rem; padding: 0.75rem !important; }
  .btn-submit { padding: 0.5rem 1rem !important; font-size: 0.8rem; }
}
</style>