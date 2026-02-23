<script setup>
import { ref, computed, provide } from 'vue';
import SingleComment from './SingleComment.vue';

// Some random comments
const comments = ref([
  {
    id: 1,
    author: 'Some OWP Admin',
    role: 'Admin',
    time: '2d ago',
    text: 'Well done Johnny! Keep it up!',
    replies: [
      {
        id: 3,
        author: 'Johnny Robert',
        role: 'User',
        time: '1d ago',
        text: 'Thanks! I really appreciate the feedback.',
        replies: [
          {
            id: 5,
            author: 'Some OWP Admin',
            role: 'Admin',
            time: '12h ago',
            text: 'Of course! We love seeing this kind of progress in the community.',
            replies: []
          }
        ]
      },
      {
        id: 4,
        author: 'Sarah Alice',
        role: 'User',
        time: '1d ago',
        text: 'Agreed! The quality of this post is top-notch.',
        replies: []
      }
    ]
  },
  {
    id: 2,
    author: 'Gerard Billington',
    role: 'User',
    time: '2d ago',
    text: 'Wow this is so cool!',
    replies: []
  },
  {
    id: 6,
    author: 'Dev Mike',
    role: 'User',
    time: '5h ago',
    text: 'Great post thank you!',
    replies: []
  },
  {
    id: 7,
    author: 'Dev Mike',
    role: 'User',
    time: '5h ago',
    text: 'Great post thank you!',
    replies: []
  }, 
  {
    id: 8,
    author: 'Dev Mike',
    role: 'User',
    time: '5h ago',
    text: 'Great post thank you!',
    replies: []
  },
  {
    id: 9,
    author: 'Dev Mike',
    role: 'User',
    time: '5h ago',
    text: 'Great post thank you!',
    replies: []
  },
  {
    id: 10,
    author: 'Dev Mike',
    role: 'User',
    time: '5h ago',
    text: 'Great post thank you!',
    replies: []
  },
]);

const isFocused = ref(false);
const newComment = ref('');
const displayLimit = ref(5);
const activeReplyId = ref(null);

provide('activeReplyId', activeReplyId);

// Helper to count all comments and replies
const countAll = (commentArray) => {
  let count = 0;
  for (const comment of commentArray) {
    count++;
    if (comment.replies && comment.replies.length > 0) {
      count += countAll(comment.replies);
    }
  }
  return count;
};

const visibleComments = computed(() => {
  return comments.value.slice(0, displayLimit.value);
});

const totalCommentsCount = computed(() => {
  return countAll(comments.value);
});

const showMoreTopLevel = () => {
  displayLimit.value += 5;
};

const cancelComment = () => {
  newComment.value = '';
  isFocused.value = false;
};
</script>

<template>
  <div class="comment-section p-4 rounded-3 border bg-white text-start">
    <h3 class="section-title fw-bold mb-4 pb-2 border-bottom d-inline-block">{{ totalCommentsCount }} Comments</h3>

    <!-- Comment textbox -->
    <div class="main-input-wrapper mb-4">
      <div class="reply-box-container border rounded-3 overflow-hidden bg-white" :class="{ 'focused-border': isFocused }">
        <textarea 
          v-model="newComment" 
          @focus="isFocused = true" 
          placeholder="Add a comment..." 
          class="comment-textarea w-100 border-0 p-3"
          rows="2"
        ></textarea>

        <!-- Action buttons-->
        <div v-if="isFocused" class="d-flex justify-content-end align-items-center gap-3 px-3 pb-2">
          <button class="btn-cancel border-0 bg-transparent fw-bold" @click="cancelComment">Cancel</button>
          <button class="btn-submit border-0 rounded-2 fw-bold px-4 py-2" :disabled="!newComment">Comment</button>
        </div>
      </div>
    </div>

    <div class="comments-container">
      <SingleComment v-for="comment in visibleComments" :key="comment.id" :comment="comment" />
    </div>

    <button v-if="displayLimit < comments.length" @click="showMoreTopLevel" 
            class="load-more-btn w-100 border py-2 rounded-3 fw-bold bg-transparent">
      Show more comments
    </button>
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
  color: #ffffff;
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