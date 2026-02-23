<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getPost, votePost } from "@/api/posts";
import ViewPostContent from "@/components/forum/ViewPostContent.vue";
import ViewPostHeader from "@/components/forum/ViewPostHeader.vue";
import { isLoggedIn, userRole, userRoleId, uid } from "@/stores/userStore";
import CreatePostModal from "@/components/forum/CreatePostModal.vue";
import PostModerationSidebar from "@/components/admin/PostModerationSidebar.vue";
import CommentSection from "@/components/forum/CommentSection.vue";

const route = useRoute();
const router = useRouter();
const postId = route.params.id;

const post = ref(null);
const loading = ref(true);
const error = ref(null);

const showEditModal = ref(false);
const isRestricted = ref(false);

// Follow & Vote toggle states
const isFollowing = ref(false);
const isVoting = ref(false);

async function handleVote(dir) {
  if (isVoting.value || !post.value) return;

  const currentVote = Number(post.value.myVote ?? 0);
  let action = dir;
  if (
    (dir === "up" && currentVote === 1) ||
    (dir === "down" && currentVote === -1)
  ) {
    action = "clear";
  }

  isVoting.value = true;
  try {
    const data = await votePost(post.value.PostID, action);
    if (data?.ok) {
      post.value.myVote = data.myVote;
      post.value.TotalScore = data.score;
    }
  } catch (err) {
    console.error("Vote error:", err);
  } finally {
    isVoting.value = false;
  }
}

const toggleFollow = () => (isFollowing.value = !isFollowing.value);

const canReport = computed(() => {
  if (!isLoggedIn.value) return true;
  const role = (userRole?.value || "").toLowerCase();
  return !(role === "admin" || role === "moderator");
});

// Admin and Mod only
const isAdminOrMod = computed(() => Number(userRoleId.value) >= 3);

// Relies on backend sending post.authorId
const isAuthor = computed(() => {
  if (!post.value) return false;
  return Number(post.value.authorId) === Number(uid.value);
});

function goBack() {
  if (window.history.length > 1) router.back();
  else router.push("/");
}

// modalType: 'edit' (author/full) OR 'metadata' (restricted)
function openRestrictedModal(modalType) {
  isRestricted.value = modalType === "metadata";
  showEditModal.value = true;
}

onMounted(async () => {
  try {
    post.value = await getPost(postId);
  } catch (err) {
    error.value = "Post could not be loaded.";
    console.error(err);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="page">
    <div class="container position-relative">
      <div class="go-back-floating" role="button" tabindex="0" @click="goBack" @keydown.enter="goBack">
        <span class="back-arrow">←</span>
      </div>

      <div v-if="loading" class="loader pt-5">
        <div class="spinner-border"></div>
      </div>

      <div v-else-if="error" class="error empty-state text-center">
        <p class="fw-medium text-secondary">
          The post has been deleted or does not exist.
        </p>
      </div>

      <div v-else-if="post" class="page-container">
        <div class="center-container col text-center">
          <div class="row gx-0">
            <div class="col-12 header-align mb-2">
              <ViewPostHeader :post="post" />
            </div>
          </div>

          <div class="row gx-0">
            <div class="post-sidebar col-md-3 col-lg-2 text-white mb-3 mb-md-0">
              <div class="sidebar-actions">
                <div class="voteFol">
                  <div class="vote-container vote-container--sidebar">
                    <button
                      class="vote-btn-up pi pi-chevron-up mb-1"
                      :class="{ active: Number(post.myVote) === 1 }"
                      :disabled="isVoting"
                      @click="handleVote('up')"
                    ></button>

                    <span class="vote-count" :class="{
                      upvoted: Number(post.myVote) === 1,
                      downvoted: Number(post.myVote) === -1,
                    }">
                      {{ post.TotalScore ?? 0 }}
                    </span>

                    <button
                      class="vote-btn-down pi pi-chevron-down mt-1"
                      :class="{ active: Number(post.myVote) === -1 }"
                      :disabled="isVoting"
                      @click="handleVote('down')"
                    ></button>
                  </div>

                  <button class="follow-btn" :class="{ following: isFollowing }" type="button" @click="toggleFollow">
                    {{ isFollowing ? "❤︎" : "Follow ❤︎" }}
                  </button>
                </div>

                <button v-if="canReport" class="report-btn" type="button">
                  <i class="pi pi-flag report-icon"></i>
                  <span>Report</span>
                </button>

                <PostModerationSidebar
                  v-if="isAuthor || isAdminOrMod"
                  :post="post"
                  :user="{ RoleID: Number(userRoleId) }"
                  :is-author="isAuthor"
                  @open-modal="openRestrictedModal"
                />

                <CreatePostModal
                  v-if="showEditModal"
                  :show="showEditModal"
                  :post-data="post"
                  :is-restricted="isRestricted"
                  @close="showEditModal = false"
                />
              </div>
            </div>

            <div class="post-content col-md-9 col-lg-10">
              <ViewPostContent :content="post.content" />
            </div>
          </div>

          <div class="row gx-0">
            <div class="post-comments mt-2 mb-4">
              <CommentSection />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Page background */
.page {
  background-color: #cbdad5;
  min-height: 90vh;
  padding-top: 5vh;
  padding-left: 1vh;
  padding-right: 1vh;
}

/* Loader */
.loader {
  display: flex;
  justify-content: center;
  padding-top: 25%;
  padding-bottom: 25%;
}

/* Error */
.empty-state {
  background: rgba(255, 255, 255, 0.6);
  border-radius: 20px;
  border: 2px dashed #7e9291;
  padding: 3rem;
}

/* Header */
.go-back {
  border: 2px black solid;
  border-radius: 3px;
}

.content-head {
  border: 2px black solid;
  border-radius: 3px;
}

/* Content */
.post-content {
  background-color: none;
}

/* Sidebar*/
.post-sidebar {
  background-color: #ffffff;
  border-radius: 12px;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.sidebar-actions {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
}

.voteFol {
  height: 100%;
}

/* Voting base */
.vote-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.vote-container--sidebar {
  min-width: 60px;
}

.vote-btn-up,
.vote-btn-down {
  background: none;
  border: none;
  color: #bac7c4;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  font-size: 1.8rem;
  padding: 0;
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

.vote-btn-up.active,
.vote-btn-down.active {
  scale: 115%;
}

.vote-btn-up.active {
  color: #043927;
}

.vote-btn-down.active {
  color: #5e2b2c;
}

.vote-btn-up:disabled,
.vote-btn-down:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.vote-count {
  font-weight: 900;
  font-size: 1.35rem;
  margin: -6px 0;
  color: #1a1a1b;
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
    transform: translateY(-5px);
  }
  50% {
    transform: translateY(3px);
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

/* Follow */
.follow-btn {
  width: 130px;
  height: 40px;
  border-radius: 8px;
  border-style: none;
  color: #ffffff;
  background-color: #004750;
  font-weight: 800;
  font-size: 1rem;
  cursor: pointer;
  transition: 0.3s;
}

.follow-btn:hover {
  background-color: #007c8a;
}

.follow-btn.following {
  background-color: #b91657;
  color: #ffffff;
  width: 45px;
  font-size: 1.5rem;
}

.follow-btn.following:hover {
  background-color: #737373;
}

/* Report */
.report-btn {
  background: none;
  border: none;
  color: #adb5bd;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  font-size: 0.9rem;
  font-weight: 700;
}

.report-icon {
  font-size: 1.6rem;
}

.report-btn:hover {
  color: #dc3545;
}

.post-comments {
  border: 2px black solid;
  border-radius: 3px;
}

/* box of votes and follow */
.voteFol {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

/* HEADER alignment */
.header-align {
  padding-left: 0;
  padding-right: 0;
  text-align: left;
}

/* FLOATING BACK ARROW */
.go-back-floating {
  position: absolute;
  left: -88px;
  top: 4px;

  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 3px solid #000;
  background: #fff;

  display: flex;
  align-items: center;
  justify-content: center;

  cursor: pointer;
  z-index: 10;
}

.go-back-floating:hover {
  background: #f1f5f9;
}

.back-arrow {
  font-size: 26px;
  font-weight: 900;
  line-height: 1;
}

/* Sidebar */
.post-sidebar {
  border: 2px solid #000;
  border-radius: 3px;
}

/* Replace Bootstrap gutter between sidebar/content */
.post-content {
  padding-left: 16px;
}

/* Comments */
.post-comments {
  border: 2px solid #000;
  border-radius: 3px;
}

/* Mobile safety */
@media (max-width: 576px) {
  .go-back-floating {
    left: 0;
    top: -64px;
    width: 52px;
    height: 52px;
  }

  .back-arrow {
    font-size: 24px;
  }

  .post-content {
    padding-left: 0;
  }
}
</style>