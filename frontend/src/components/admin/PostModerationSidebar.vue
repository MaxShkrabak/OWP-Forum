<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import client from "@/api/client";
import { votePost } from "@/api/posts";
import { isLoggedIn, userRole, userRoleId, uid } from "@/stores/userStore";
import CreatePostModal from "@/components/forum/CreatePostModal.vue";
import ReportingModal from "@/components/user/ReportingModal.vue"

const props = defineProps({
  post: { type: Object, required: true },
});

const router = useRouter();

const isVoting = ref(false);
const isFollowing = ref(false);
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);
const showEditModal = ref(false);
const showReportModal = ref(false);
const isRestricted = ref(false);

const isAuthor = computed(
  () => Number(props.post?.authorId) === Number(uid.value),
);
const isAdminOrMod = computed(() => Number(userRoleId.value) >= 3);

const canReport = computed(() => {
  if (isAuthor.value) return false;
  if (!isLoggedIn.value) return true;
  const role = (userRole?.value || "").toLowerCase();
  return !(role === "admin" || role === "moderator");
});

const canDelete = computed(() => isAuthor.value || isAdminOrMod.value);
const showMetadataButton = computed(
  () => isAdminOrMod.value && !isAuthor.value,
);

const toggleFollow = () => {
  if (!isLoggedIn.value) {
    router.push("/login");
    return;
  }
  isFollowing.value = !isFollowing.value;
};

const handleReport = () => {
  if (!isLoggedIn.value) {
    router.push("/login");
    return;
  }
  showReportModal.value = true;
};

async function handleVote(dir) {
  if (!isLoggedIn.value) {
    router.push("/login");
    return;
  }
  if (isVoting.value || !props.post) return;

  const currentVote = Number(props.post.myVote ?? 0);
  let action = dir;
  if (
    (dir === "up" && currentVote === 1) ||
    (dir === "down" && currentVote === -1)
  ) {
    action = "clear";
  }

  isVoting.value = true;
  try {
    const data = await votePost(props.post.postId, action);
    if (data?.ok) {
      props.post.myVote = data.myVote;
      props.post.totalScore = data.score;
    }
  } catch (err) {
    console.error("Vote error:", err);
  } finally {
    isVoting.value = false;
  }
}

function openRestrictedModal(modalType) {
  isRestricted.value = modalType === "metadata";
  showEditModal.value = true;
}

const confirmDelete = async () => {
  if (!props.post?.postId || !canDelete.value) return;
  isDeleting.value = true;
  try {
    await client.delete(`posts/${props.post.postId}`);
    window.location.href = "/";
  } catch (err) {
    console.error("Delete failed:", err);
    showDeleteConfirm.value = false;
  } finally {
    isDeleting.value = false;
  }
};

watch(isLoggedIn, (loggedIn) => {
  if (!loggedIn) {
    props.post.myVote = 0;
  }
});
</script>

<template>
  <div class="d-flex flex-wrap align-items-center gap-2 w-100">
    <!-- Vote Buttons -->
    <div class="action-group vote-group d-flex align-items-center gap-1">
      <button
        class="vote-btn-up pi pi-chevron-up"
        :class="{ active: Number(post.myVote) === 1, 'is-voting': isVoting }"
        @click="handleVote('up')"
        title="Upvote"
      ></button>

      <span
        class="vote-count px-2"
        :class="{
          upvoted: Number(post.myVote) === 1,
          downvoted: Number(post.myVote) === -1,
          'voting-bounce': isVoting,
        }"
      >
        {{ post.totalScore ?? 0 }}
      </span>

      <button
        class="vote-btn-down pi pi-chevron-down"
        :class="{ active: Number(post.myVote) === -1, 'is-voting': isVoting }"
        @click="handleVote('down')"
        title="Downvote"
      ></button>
    </div>

    <!-- User Actions -->
    <div
      class="action-group user-actions d-flex align-items-center flex-wrap gap-2"
    >
      <button
        v-if="!isAuthor"
        class="text-action-btn d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2"
        :class="{ following: isFollowing }"
        @click="toggleFollow"
      >
        <i :class="isFollowing ? 'pi pi-heart-fill' : 'pi pi-heart'"></i>
        <span>{{ isFollowing ? "Following" : "Follow" }}</span>
      </button>

      <button
        v-if="canReport"
        class="text-action-btn report-btn d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2"
        @click="handleReport"
      >
        <i class="pi pi-flag"></i>
        <span>Report</span>
      </button>

      <div v-if="!isAuthor && isAdminOrMod" class="action-divider mx-1"></div>

      <button
        v-if="isAuthor"
        class="text-action-btn edit-btn d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2"
        @click="openRestrictedModal('edit')"
      >
        <i class="pi pi-pencil"></i>
        <span>Edit Post</span>
      </button>

      <button
        v-if="showMetadataButton"
        class="text-action-btn edit-btn d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2"
        @click="openRestrictedModal('metadata')"
      >
        <i class="pi pi-pencil"></i>
        <span>Edit</span>
      </button>

      <button
        v-if="canDelete"
        class="text-action-btn delete-btn d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2"
        @click="showDeleteConfirm = true"
      >
        <i class="pi pi-trash"></i>
        <span>Delete</span>
      </button>
    </div>

    <!-- Modals -->
    <CreatePostModal
      v-if="showEditModal"
      :show="showEditModal"
      :post-data="post"
      :is-restricted="isRestricted"
      @close="showEditModal = false"
    />

    <ReportingModal
      :isOpen="showReportModal"
      :targetId="post.postId"
      :targetTitle="post.title"
      type="post"
      @close="showReportModal = false"
    />

    <Teleport to="body">
      <Transition name="modal" appear>
        <div
          v-if="showDeleteConfirm"
          class="modal-mask d-flex align-items-center justify-content-center"
          @mousedown.self="showDeleteConfirm = false"
        >
          <div class="warning-card p-5 text-center rounded-4">
            <div class="warning-icon fs-1 mb-3">
              <i class="pi pi-exclamation-triangle"></i>
            </div>
            <h3 class="warning-title fs-5 mb-2">Delete this post?</h3>
            <p class="warning-body small m-0">
              This post will be removed from public view. This cannot be undone.
            </p>
            <div class="modal-actions d-flex gap-2 justify-content-center mt-4">
              <button
                class="cancel-btn rounded-3 px-4 py-2"
                @click="showDeleteConfirm = false"
                :disabled="isDeleting"
              >
                Cancel
              </button>
              <button
                class="delete-confirm-btn rounded-3 px-4 py-2"
                @click="confirmDelete"
                :disabled="isDeleting"
              >
                <span v-if="isDeleting"
                  ><span class="spinner-border spinner-border-sm me-1"></span
                  >Deleting…</span
                >
                <span v-else>Confirm Delete</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.action-divider {
  width: 1px;
  height: 20px;
  background: #cbd5e1;
}

.vote-btn-up,
.vote-btn-down {
  background: transparent;
  border: none;
  color: #bac7c4;
  font-size: 1.1rem;
  padding: 4px;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  display: flex;
  align-items: center;
  justify-content: center;
}

.vote-btn-up:hover {
  color: #1a3c34;
  transform: translateY(-1px);
  text-shadow: 0 4px 2px #04392791;
}

.vote-btn-down:hover {
  color: #5e2b2c;
  transform: translateY(1px);
  text-shadow: 0 -4px 2px #5e2b2c91;
}

.vote-btn-up.active {
  color: #043927;
  transform: scale(1.15);
}
.vote-btn-down.active {
  color: #5e2b2c;
  transform: scale(1.15);
}

.vote-count {
  font-weight: 800;
  font-size: 0.95rem;
  color: #1a1a1b;
  min-width: 24px;
  text-align: center;
}

.vote-count.upvoted {
  color: #043927;
}
.vote-count.downvoted {
  color: #5e2b2c;
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
  70% {
    transform: translateY(-1px);
  }
  85%,
  100% {
    transform: translateY(0);
  }
}

.voting-bounce {
  animation: count-bounce 0.8s infinite ease-in-out;
  display: inline-block;
  opacity: 0.8;
}

.text-action-btn {
  position: relative;
  background: transparent;
  border: none;
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px 6px;
  transition: color 0.2s ease;
  text-decoration: none;
}

.text-action-btn::after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 6px;
  right: 6px;
  height: 2px;
  border-radius: 20px;
  background: currentColor;
  transform: scaleX(0);
  transform-origin: left center;
  transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.text-action-btn:hover::after {
  transform: scaleX(1);
}

.text-action-btn i {
  font-size: 0.88rem;
  transition: transform 0.2s ease;
}

/* Follow */
.text-action-btn:not(.report-btn):not(.edit-btn):not(.delete-btn):hover {
  color: #b91657;
}
.text-action-btn:not(.report-btn):not(.edit-btn):not(.delete-btn):hover i {
  transform: scale(1.2);
}
.text-action-btn.following {
  color: #b91657;
}
.text-action-btn.following::after {
  transform: scaleX(1);
  opacity: 0.5;
}

/* Report */
.report-btn:hover {
  color: #c0392b;
}
.report-btn:hover i {
  transform: rotate(-8deg) scale(1.1);
}

/* Edit */
.edit-btn:hover {
  color: #1e4d38;
}
.edit-btn:hover i {
  transform: translateY(-1px) rotate(-6deg);
}

/* Delete */
.delete-btn:hover {
  color: #c0392b;
}

@keyframes trash-shake {
  0%,
  100% {
    transform: rotate(0deg);
  }
  20% {
    transform: rotate(-10deg);
  }
  40% {
    transform: rotate(10deg);
  }
  60% {
    transform: rotate(-6deg);
  }
  80% {
    transform: rotate(6deg);
  }
}
.delete-btn:hover i {
  animation: trash-shake 0.4s ease-in-out;
}

.modal-mask {
  position: fixed;
  z-index: 9998;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(2px);
}

.warning-card {
  background: #fff;
  max-width: 380px;
  width: 90%;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.warning-icon {
  color: #c0392b;
}
.warning-title {
  font-weight: 800;
  color: #1a1a1b;
}
.warning-body {
  color: #64748b;
  line-height: 1.5;
}

.cancel-btn {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.15s;
}
.cancel-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.delete-confirm-btn {
  background: #9f3323;
  color: #fff;
  border: none;
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
  transition: background 0.15s;
}
.delete-confirm-btn:hover:not(:disabled) {
  background: #7d281b;
}

.cancel-btn:disabled,
.delete-confirm-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-active .warning-card {
  transition: transform 0.2s ease;
}
.modal-enter-from .warning-card {
  transform: scale(0.95) translateY(8px);
}
</style>
