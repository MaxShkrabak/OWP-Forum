<script setup>
import { ref, watch, computed } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { timeAgo } from "@/utils/timeAgo";
import UserRole from "@/components/user/UserRole.vue";
import { votePost, togglePostPin } from "@/api/posts";
import { isLoggedIn, userRole } from "@/stores/userStore";
import ReportingModal from "@/components/user/ReportingModal.vue"

const props = defineProps({
  post: { type: Object, required: true },
});

const isVoting = ref(false);
const isPinning = ref(false);
const isReportModalOpen = ref(false);
const router = useRouter();

const isAdmin = computed(() => {
  return (userRole.value || "").trim().toLowerCase() === "admin";
});

const isAnnouncementNews = computed(() => {
  const category = (props.post?.categoryName || "").trim().toLowerCase();
  return category.includes("announcement") && category.includes("news");
});

const canShowPinIcon = computed(() => isAdmin.value && isAnnouncementNews.value);

async function handleVote(dir) {
  if (isVoting.value) return;

  const currentVote = Number(props.post.myVote ?? 0);
 
  let action = dir;
  if ((dir === "up" && currentVote === 1) || (dir === "down" && currentVote === -1)) {
    action = "clear";
  }

  isVoting.value = true;

  try {
    const data = await votePost(props.post.PostID, action);

    if (data.ok) {
      props.post.myVote = data.myVote;
      props.post.TotalScore = data.score;
    }
  } catch (err) {
    console.error("Vote error:", err);
  } finally {
    isVoting.value = false;
  }
}

async function handlePinToggle() {
  if (isPinning.value) return;

  isPinning.value = true;

  try {
    const data = await togglePostPin(props.post.PostID);

    if (data.ok) {
      props.post.isPinned = data.isPinned;
    }
  } catch (err) {
    console.error("Pin toggle error:", err);
  } finally {
    isPinning.value = false;
  }
}

function getAvatarSrc(file) {
  return new URL(`../../assets/img/user-pfps-premade/${file}`, import.meta.url).href;
}

function openReportModal() {
  if (!isLoggedIn.value) {
    router.push("/login");
    return;
  }
  isReportModalOpen.value = true;
}

function closeReportModal() {
  isReportModalOpen.value = false;
}

watch(isLoggedIn, (loggedIn) => {
  if (!loggedIn) {
    props.post.myVote = 0;
  }
});

function isOfficialTag(name){
  return name === 'Official';
}
</script>

<template>
  <div class="post-card shadow-sm mb-3">
    <div class="responsive-container">
      <div class="main-content-area">
        <div class="vote-container">
          <!-- Upvote -->
          <button
            class="vote-btn-up pi pi-chevron-up mb-1"
            :class="{ active: Number(post.myVote) === 1, 'is-voting': isVoting }"
            @click="isLoggedIn ? handleVote('up') : router.push('/login')">
          </button>

          <!-- Vote count -->
          <span class="vote-count"
                :class="{
                        'upvoted': Number(post.myVote) === 1,
                        'downvoted': Number(post.myVote) === -1, 'voting-bounce': isVoting }">
            {{ post.TotalScore ?? 0 }}
          </span>

          <!-- Downvote -->
          <button
            class="vote-btn-down pi pi-chevron-down mt-1"
            :class="{ active: Number(post.myVote) === -1, 'is-voting': isVoting }"
            @click="isLoggedIn ? handleVote('down') : router.push('/login')">
          </button>
        </div>

        <div class="title-and-meta-column">
          <div class="mobile-author-header">
            <div class="author-info-wrap-v2">
              <div class="avatar-box-v2">
                <img
                  :src="getAvatarSrc(post.authorAvatar)"
                  class="avatar-img"
                  alt="user"
                />
              </div>
              <div class="d-flex flex-column">
                <span class="author-name-v2">{{ post.authorName }}</span>
                <UserRole :role="post.authorRole" />
              </div>
            </div>
            <div class="text-secondary date">
              {{ timeAgo(post.createdAt) }}
            </div>
          </div>

          <div class="title-row">
            <RouterLink :to="`/posts/${post.PostID}`" class="post-title-link">
              {{ post.title }}
            </RouterLink>

            <button
              v-if="canShowPinIcon"
              class="pin-btn"
              type="button"
              :disabled="isPinning"
              @click="handlePinToggle"
              :title="post.isPinned ? 'Unpin announcement' : 'Pin announcement'"
            >
              <i
                class="pi pi-thumbtack pin-icon"
                :class="{ pinned: !!post.isPinned, 'is-pinning': isPinning }"
              ></i>
            </button>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-2">
            <span v-for="tag in post.tags" :key="tag" :class="isOfficialTag(tag) ? 'post-tag-mod-admin' : 'post-tag'">
              {{ tag }}</span>
          </div>

          <div class="meta-footer">
            <div class="meta-item">
              <i class="pi pi-comment me-1"></i>
              {{ post.commentCount }} comments
            </div>
            <button class="report-btn" @click="openReportModal">
              <i class="pi pi-flag me-1"></i> Report
            </button>
          </div>
        </div>

        <ReportingModal 
          :isOpen="isReportModalOpen"
          :targetId="post.PostID"
          :targetTitle="post.title"
          type="post" 
          @close="closeReportModal"
        />
      </div>

      <div class="author-block desktop-only-author">
        <div class="text-secondary date">
          {{ timeAgo(post.createdAt) }}
        </div>
        <div class="v-divider"></div>
        <div class="author-info-wrap">
          <div class="text-end d-flex flex-column align-items-end">
            <span class="author-name text-truncate">{{ post.authorName }}</span>
            <UserRole :role="post.authorRole" />
          </div>
          <div class="avatar-box shadow-sm">
            <RouterLink :to="`/profile?id=${post.authorId}`">
              <img
                :src="getAvatarSrc(post.authorAvatar)"
                class="avatar-img"
                alt="user"
              />
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.post-card {
  background: white;
  border-radius: 10px;
  padding: 10px 14px;
  border: 1px solid rgba(0, 0, 0, 0.03);
  transition: all 0.2s ease-in-out;
}
.post-card:hover {
  transform: translateY(-3px);
  border-color: #2e6c44;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

.responsive-container {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

/* Main content of the post (excluding author section) */
.main-content-area {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
  min-width: 0;
}

.title-and-meta-column {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  border-left: 1px solid #dee2e6;
  padding-left: 1rem;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  width: 100%;
}

.pin-btn {
  background: none;
  border: none;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.pin-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.pin-icon {
  color: #c2680a;
  font-size: 0.9rem;
  flex-shrink: 0;
  transition: transform 0.2s ease-in-out, opacity 0.2s ease-in-out;
}

.pin-icon.pinned {
  transform: rotate(25deg);
}

.pin-icon.is-pinning {
  opacity: 0.6;
}

.mobile-author-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f1f1f1;
  width: 100%;
  padding-bottom: 8px;
  margin-bottom: 10px;
}

.author-info-wrap-v2 {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar-box-v2 {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  background: #eee;
  flex-shrink: 0;
}

.author-name-v2 {
  font-weight: 700;
  font-size: 0.8rem;
  color: #1a1a1b;
  line-height: 1;
}

.role-pill {
  font-size: 0.5rem;
  font-weight: 800;
  padding: 1px 4px;
  border-radius: 3px;
  text-transform: uppercase;
  width: max-content;
}

.desktop-only-author {
  display: none;
}

.post-title-link {
  color: #1a1a1b;
  text-decoration: none;
  font-weight: 700;
  font-size: 1rem;
  line-height: 1.2;
  white-space: nowrap; /* TODO: We can let it wrap if the title MAX LENGTH is reasonable */
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 0 1 auto;
  min-width: 0;
}
.post-title-link:hover {
  color: #2e6c44;
  text-decoration: underline;
}

.vote-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 32px;
  flex-shrink: 0;
}

.vote-btn-up,
.vote-btn-down {
  background: none;
  border: none;
  color: #bac7c4;
  font-size: 1rem;
  padding: 0;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
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
  font-weight: 800;
  font-size: 0.85rem;
  color: #1a1a1b;
  margin: -2px 0;
}

.vote-count.upvoted {
  color: #043927;
}

.vote-count.downvoted {
  color: #5e2b2c;
}

/* Loading bounce effect */
@keyframes count-bounce {
  0%  { transform: translateY(0); }
  25% { transform: translateY(-5px); }
  50% { transform: translateY(3px); }
  70% { transform: translateY(-1px); }
  85%, 100% { transform: translateY(0); }
}

.voting-bounce {
  animation: count-bounce 0.8s infinite ease-in-out;
  display: inline-block;
  opacity: 0.8;
}
.post-tag-mod-admin,
.post-tag {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 4px;
  white-space: nowrap;
}
.post-tag-mod-admin {
  background: linear-gradient(135deg, #c2680a 0%, #9a4e08 100%);
  color: #ffecd1;
}
.post-tag {
  background: #2e6c44;
  color: white;
}

.meta-footer {
  display: flex;
  align-items: center;
  gap: 1em;
  font-size: 0.7rem;
  color: #6c757d;
  font-weight: 500;
}

.report-btn {
  background: none;
  border: none;
  color: #adb5bd;
  padding: 0;
  font-size: 0.7rem;
  font-weight: 600;
  cursor: pointer;
}
.report-btn:hover {
  color: #dc3545;
}

.date {
  font-size: 0.7rem;
  font-weight: 600;
}

.v-divider {
  width: 1px;
  height: 20px;
  background: #e2e8f0;
}

.author-name {
  font-weight: 700;
  font-size: 0.75rem;
  color: #1a1a1b;
  max-width: 120px;
}

.avatar-box {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  background: #eee;
  flex-shrink: 0;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author-info-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-direction: row;
}

@media (min-width: 768px) {
  .responsive-container {
    gap: 20px;
  }
  .mobile-author-header {
    display: none;
  }
  .desktop-only-author {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-shrink: 0;
  }
}
</style>