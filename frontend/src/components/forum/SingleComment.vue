<script setup>
import { ref, inject, computed } from 'vue';
import UserRole from "@/components/user/UserRole.vue";

const props = defineProps({
  comment: Object,
  isLastChild: Boolean
});

const showReplies = ref(false);
const replyText = ref('');
const showMenu = ref(false);

const activeReplyId = inject('activeReplyId');

const isReplying = computed(() => activeReplyId.value === props.comment.id);
const toggleMenu = () => { showMenu.value = !showMenu.value; };

const toggleReply = () => { 
  if (isReplying.value) {
    activeReplyId.value = null;
  } else {
    activeReplyId.value = props.comment.id;
  }
};

const toggleRepliesDropdown = () => { showReplies.value = !showReplies.value; };

const totalScore = ref(0);
</script>

<template>
  <div class="comment-node mb-3 position-relative">
    <div class="d-flex gap-3 gap-sm-2 position-relative">

      <!-- Avatar -->
      <div class="avatar-col d-flex flex-column align-items-center flex-shrink-0">
        <div class="avatar-box shadow-sm overflow-hidden rounded-circle">
          <img src="@/assets/img/user-pfps-premade/pfp-1.png" class="w-100 h-100 object-fit-cover" alt="avatar" />
        </div>
        <div v-if="comment.replies?.length && showReplies" class="line-bridge"></div>
      </div>

      <div class="flex-grow-1 overflow-visible">
        <div class="d-flex align-items-center mb-1">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="author-name text-truncate small fw-bold">{{ comment.author }}</span>
            <UserRole :role="comment.role" />
            <span class="timestamp text-muted">{{ comment.time }}</span>

            <!-- Menu dropdown -->
            <div class="dropdown">
              <button class="btn-options border-0 bg-transparent p-1" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="pi pi-ellipsis-v small"></i>
              </button>

              <!-- Dropdown options -->
              <ul class="dropdown-menu shadow border-0 rounded-2">
                <li>
                  <button class="dropdown-item d-flex align-items-center gap-2 py-2" @click="report">
                    <i class="pi pi-flag small"></i> <span class="small">Report</span>
                  </button>
                </li>
                <li>
                  <button class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" @click="remove">
                    <i class="pi pi-trash small"></i> <span class="small">Delete</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Comment content -->
        <div class="comment-body mb-2 small">
          {{ comment.text }}
        </div>

        <div class="d-flex align-items-center gap-3 gap-sm-2 flex-wrap">
          <!-- Voting -->
          <div class="vote-container-horizontal d-flex align-items-center rounded-2 px-2 py-1">
            <button class="vote-btn-up pi pi-chevron-up border-0 bg-transparent p-0"></button>
            <span class="vote-count mx-2 fw-bold" :class="{ 'upvoted': totalScore > 0, 'downvoted': totalScore < 0 }">
              {{ totalScore }}
            </span>
            <button class="vote-btn-down pi pi-chevron-down border-0 bg-transparent p-0"></button>
          </div>

          <!-- Reply button -->
          <button class="action-btn border-0 bg-transparent fw-bold d-flex align-items-center gap-1 p-0"
            @click="toggleReply" :class="{ 'active': isReplying }">
            <span>Reply</span>
          </button>
        </div>

        <!-- Reply text box -->
        <div v-if="isReplying" class="mt-2">
          <div class="reply-box-container border rounded-3 overflow-hidden bg-white">
            <textarea v-model="replyText" class="reply-textarea w-100 border-0 outline-none p-3 small" rows="2"
              placeholder="What are your thoughts?"></textarea>

            <div class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2">
              <button class="btn-cancel border-0 bg-transparent fw-bold small"
                @click="activeReplyId = null">Cancel</button>
              <button class="btn-submit border-0 rounded-2 fw-bold px-3 py-1 small"
                :disabled="!replyText">Reply</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Comment replies -->
    <div v-if="comment.replies?.length" class="replies-wrapper position-relative">
      <div class="thread-spine" v-if="showReplies"></div>

      <div v-if="showReplies">
        <div v-for="(reply, index) in comment.replies" :key="reply.id"
          class="reply-item mt-3 position-relative ps-3 ps-sm-2">
          <div class="child-connector"></div>
          <SingleComment :comment="reply" :is-last-child="index === comment.replies.length - 1" />
        </div>
      </div>

      <div class="mt-2 position-relative ps-4 ps-sm-3">
        <button class="btn-toggle-replies border-0 bg-transparent fw-bold d-flex align-items-center gap-2 p-0"
          @click="toggleRepliesDropdown">
          <i :class="showReplies ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"></i>
          <span class="small">{{ showReplies ? 'Hide replies' : `View ${comment.replies.length} replies` }}</span>
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
  z-index: 5;
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
.vote-container-horizontal {
  background-color: #f8fafc;
  gap: 8px;
}
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
  color: #6b7280;
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

/* Thread lines */
.line-bridge {
  position: absolute;
  top: 20px;
  bottom: -16px;
  left: 50%;
  width: 2px;
  transform: translateX(-50%);
  background-color: #e5e7eb;
  z-index: 1;
}
.replies-wrapper {
  margin-left: 19px;
}
.thread-spine {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 24px;
  width: 2px;
  background-color: #e5e7eb;
}
.child-connector {
  position: absolute;
  top: 20px;
  left: 0;
  width: 16px;
  height: 24px;
  border-bottom: 2px solid #e5e7eb;
  border-left: 2px solid #e5e7eb;
  border-bottom-left-radius: 12px;
  margin-top: -24px;
}

.btn-toggle-replies {
  color: #035157;
}

@media (max-width: 599px) {
  .avatar-col {
    width: 32px;
  }
  .avatar-box {
    width: 32px;
    height: 32px;
  }
  .line-bridge {
    top: 16px;
  }
  .replies-wrapper {
    margin-left: 15px;
  }
  .child-connector {
    width: 12px;
  }
}
</style>