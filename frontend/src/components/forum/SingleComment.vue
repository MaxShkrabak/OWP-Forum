<script setup>
import { ref, inject, computed, watch } from 'vue';
import UserRole from "@/components/user/UserRole.vue";
import { voteComment as apiVoteComment, fetchCommentReplies } from '@/api/comments';
import { isLoggedIn } from '@/stores/userStore';
import { timeAgo } from '@/utils/timeAgo';

const props = defineProps({
  comment: Object,
  isLastChild: Boolean
});

const localReplies = ref([]);
const isLoadingReplies = ref(false);
const hasFetched = ref(false);

const isVoting = ref(false);
const totalScore = ref(props.comment.score || 0);
const myVote = ref(Number(props.comment.myVote || 0));

const showReplies = ref(false);
const replyText = ref('');
const isHoveringToggle = ref(false);

const activeReplyId = inject('activeReplyId');
const submitReply = inject('submitReply');

const isReplying = computed(() => activeReplyId.value === props.comment.id);

const toggleReply = () => {
  activeReplyId.value = isReplying.value ? null : props.comment.id;
};

function getAvatarSrc(file) {
  return new URL(`../../assets/img/user-pfps-premade/${file}`, import.meta.url).href;
}

const toggleRepliesDropdown = async () => {
  if (!showReplies.value && !hasFetched.value && props.comment.replyCount > 0) {
    isLoadingReplies.value = true;
    try {
      const data = await fetchCommentReplies(props.comment.id);
      if (data && data.ok) {
        localReplies.value = data.items.map(item => ({
          ...item,
          id: item.commentId,
          author: `${item.user.firstName} ${item.user.lastName}`,
          time: timeAgo(item.createdAt * 1000),
          text: item.content,
          replies: []
        }));
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
  const newCommentData = await submitReply(replyText.value, props.comment.id);

  if (newCommentData) {
    replyText.value = '';
    props.comment.replyCount = (props.comment.replyCount || 0) + 1;

    if (hasFetched.value) {
      localReplies.value.push({
        ...newCommentData,
        id: newCommentData.commentId,
        author: `${newCommentData.user.firstName} ${newCommentData.user.lastName}`,
        role: newCommentData.user.role,
        time: 'Just now',
        text: newCommentData.content,
        replies: []
      });
      
      showReplies.value = true;
    }
  }
};

const handleVote = async (direction) => {
  if (!isLoggedIn.value) { alert("Must be logged in to vote!"); return; }
  if (isVoting.value) return;

  let action = direction;
  if ((direction === 'upvote' && myVote.value === 1) ||
    (direction === 'downvote' && myVote.value === -1)) {
    action = 'clear';
  }

  isVoting.value = true;
  try {
    const data = await apiVoteComment(props.comment.id, action);
    if (data.ok) {
      totalScore.value = data.score;
      myVote.value = Number(data.myVote ?? 0);
    }
  } catch (error) {
    console.error("Vote error:", error);
  } finally {
    isVoting.value = false;
  }
};

watch(isLoggedIn, (loggedIn) => {
  if (!loggedIn) myVote.value = 0;
});
</script>

<template>
  <div class="comment-node mb-3 position-relative">
    <div v-if="localReplies.length || comment.replyCount > 0" class="thread-line"
      :class="{ 'highlighted-thread-bg': isHoveringToggle }" @click="toggleRepliesDropdown" title="Toggle replies">
    </div>

    <div class="d-flex gap-3 gap-sm-2 position-relative">
      <div class="avatar-col d-flex flex-column align-items-center flex-shrink-0">
        <div class="avatar-box shadow-sm overflow-hidden rounded-circle">
          <img :src="getAvatarSrc(comment.user?.avatar)" class="avatar-box" alt="user" />
        </div>
      </div>

      <div class="flex-grow-1 overflow-visible">
        <div class="d-flex align-items-center mb-1">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="author-name text-truncate small fw-bold">{{ comment.author }}</span>
            <UserRole :role="comment.user?.role" />
            <span class="timestamp text-muted">{{ comment.time }}</span>
          </div>
        </div>

        <div class="comment-body mb-2 small">{{ comment.text }}</div>

        <div class="d-flex align-items-center gap-3 gap-sm-2 flex-wrap">
          <div class="vote-container d-flex align-items-center rounded-4 px-2 py-1">
            <button @click="handleVote('upvote')" class="vote-btn-up pi pi-chevron-up border-0 bg-transparent p-0"
              :class="{ 'active': myVote === 1, 'is-voting': isVoting }"></button>
            <span class="vote-count mx-2 fw-bold"
              :class="{ 'upvoted': myVote === 1, 'downvoted': myVote === -1, 'voting-bounce': isVoting }">{{ totalScore
              }}</span>
            <button @click="handleVote('downvote')" class="vote-btn-down pi pi-chevron-down border-0 bg-transparent p-0"
              :class="{ 'active': myVote === -1, 'is-voting': isVoting }"></button>
          </div>

          <button class="action-btn border-0 bg-transparent fw-bold d-flex align-items-center gap-1 p-0"
            @click="toggleReply" :class="{ 'active': isReplying }">
            <span>Reply</span>
          </button>
        </div>

        <div v-if="isReplying" class="mt-2">
          <div class="reply-box-container border rounded-3 overflow-hidden bg-white">
            <textarea v-model="replyText" class="reply-textarea w-100 border-0 outline-none p-3 small" rows="2"
              placeholder="What are your thoughts?"></textarea>
            <div class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2">
              <button class="btn-cancel border-0 bg-transparent fw-bold small"
                @click="activeReplyId = null">Cancel</button>
              <button class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small" :disabled="!replyText"
                @click="handleReply">Reply</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="localReplies.length || comment.replyCount > 0" class="replies-wrapper position-relative">
      <div v-if="showReplies">
        <div v-for="(reply, index) in localReplies" :key="reply.id"
          class="reply-item mt-3 position-relative ps-3 ps-sm-2">
          <div class="child-connector" :class="{ 'highlighted-thread-border': isHoveringToggle }"></div>
          <SingleComment :comment="reply" :is-last-child="index === localReplies.length - 1" />
        </div>
      </div>

      <div class="position-relative ps-3 ps-sm-2 py-1 mt-2 d-flex align-items-center">
        <div class="child-connector toggle-connector" :class="{ 'highlighted-thread-border': isHoveringToggle }"></div>
        <button
          class="btn-toggle-replies border rounded-pill bg-transparent fw-bold d-flex align-items-center gap-2 px-3 py-1 ms-1"
          @mouseenter="isHoveringToggle = true" @mouseleave="isHoveringToggle = false" @click="toggleRepliesDropdown"
          :disabled="isLoadingReplies">

          <i v-if="isLoadingReplies" class="pi pi-spin pi-spinner"></i>
          <i v-else :class="showReplies ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"></i>

          <span class="small">
            {{ showReplies ? 'Hide replies' : `View ${comment.replyCount || localReplies.length} replies` }}
          </span>
        </button>
      </div>
    </div>
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

:deep(.role-pill) {
  border-radius: 3px !important;
  padding: 1px 4px !important;
  font-size: 0.5rem !important;
}

.timestamp {
  font-size: 12px;
}

/* Menu */
.dropdown-menu {
  left: 0 !important;
  right: auto !important;
  background-color: white;
}

.btn-options {
  color: #9ca3af;
  transition: color 0.2s;
}

.btn-options:hover {
  color: #111827;
}

.dropdown-item {
  font-size: 0.85rem;
  color: #1f2937;
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

.reply-textarea {
  resize: vertical;
  color: #1f2937;
  min-height: 60px;
  outline: none;
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
  background-color: #e5e7eb;
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
  border-bottom: 1px solid #e5e7eb;
  border-left: 1px solid #e5e7eb;
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
  border-color: #e5e7eb !important;
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
</style>