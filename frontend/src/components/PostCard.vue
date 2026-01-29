<script setup>
import { RouterLink } from "vue-router";
import { timeAgo } from "@/utils/timeAgo";
import UserRole from "@/components/UserRole.vue";

const props = defineProps({
  post: {
    type: Object,
    required: true
  },
});

function getAvatarSrc(file) {
  return new URL(`../assets/img/user-pfps-premade/${file}`, import.meta.url).href;
}
</script>

<template>
  <div class="post-card shadow-sm mb-3">
    <div class="responsive-container">
      <div class="main-content-area">
        <!-- Voting Section-->
        <div class="vote-container">
          <button class="vote-btn up"><i class="pi pi-chevron-up"></i></button>
          <span class="vote-count">{{ post.likeCount }}</span>
          <button class="vote-btn down"><i class="pi pi-chevron-down"></i></button>
        </div>

        <div class="title-and-meta-column">
          <!-- Author section for smaller devices -->
          <div class="mobile-author-header">
            <div class="author-info-wrap-v2">
              <div class="avatar-box-v2">
                <img :src="getAvatarSrc(post.authorAvatar)" class="avatar-img" alt="user" />
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

          <!-- Title of the post -->
          <div class="title-row">
            <RouterLink :to="`/posts/${post.postId}`" class="post-title-link">
              {{ post.title }}
            </RouterLink>
          </div>
          <!-- Tags section -->
          <div class="d-flex flex-wrap gap-2 mb-2">
            <span v-for="tag in post.tags" :key="tag" class="post-tag">{{ tag }}</span>
          </div>

          <!-- Comments and Report -->
          <div class="meta-footer">
            <div class="meta-item">
              <i class="pi pi-comment me-1"></i>
              {{ post.commentCount }} comments
            </div>
            <button class="report-btn">
              <i class="pi pi-flag me-1"></i> Report
            </button>
          </div>
        </div>
      </div>

      <!-- Author section for larger devices -->
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
            <img :src="getAvatarSrc(post.authorAvatar)" class="avatar-img" alt="user" />
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
  border-color: #2E6C44;
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
  color: #2E6C44;
  text-decoration: underline;
}

.vote-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 32px;
  flex-shrink: 0;
}

.vote-btn {
  background: none;
  border: none;
  color: #adb5bd;
  font-size: 1rem;
  padding: 0;
  cursor: pointer;
}

.vote-count {
  font-weight: 800;
  font-size: 0.85rem;
  color: #1a1a1b;
  margin: -2px 0;
}

.post-tag {
  background: #2E6C44;
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 4px;
  white-space: nowrap;
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