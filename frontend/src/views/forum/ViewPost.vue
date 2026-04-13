<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getPost } from "@/api/posts";
import { formatPostTimestamp } from "@/utils/time";

import UserRole from "@/components/user/UserRole.vue";
import CommentSection from "@/components/forum/CommentSection.vue";
import PostModerationSidebar from "@/components/admin/PostModerationSidebar.vue";

const route = useRoute();
const router = useRouter();
const postId = route.params.id;

const post = ref(null);
const loading = ref(true);
const error = ref(null);

const linkCopiedVisible = ref(false);
let linkCopiedTimeout = null;

async function copyPostUrlToClipboard() {
  try {
    await navigator.clipboard.writeText(window.location.href);
    linkCopiedVisible.value = true;
    if (linkCopiedTimeout) clearTimeout(linkCopiedTimeout);
    linkCopiedTimeout = setTimeout(() => {
      linkCopiedVisible.value = false;
    }, 2200);
  } catch (e) {
    console.error("Copy link failed:", e);
  }
}

const postTimestamp = computed(() =>
  formatPostTimestamp(post.value?.createdAt, post.value?.updatedAt)
);

function getAvatarSrc(file) {
  if (!file) return "";
  return new URL(`../../assets/img/user-pfps-premade/${file}`, import.meta.url)
    .href;
}

function goBack() {
  if (window.history.length > 1) router.back();
  else router.push("/");
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
  <div class="page py-4 px-3 pb-5">
    <div class="page-inner mx-auto d-flex flex-column gap-3">
      <div
        v-if="loading"
        class="loader d-flex flex-column align-items-center gap-3 py-5 my-5"
      >
        <div class="spinner-border" role="status"></div>
        <span>Loading post…</span>
      </div>

      <div v-else-if="error" class="empty-state p-5 text-center rounded-4">
        <i class="pi pi-exclamation-circle empty-icon fs-1 mb-3"></i>
        <p>This post has been deleted or does not exist.</p>
      </div>

      <div v-else-if="post" class="post-layout d-flex flex-column gap-1">
        <div
          v-if="linkCopiedVisible"
          class="link-copied-toast"
          role="status"
          aria-live="polite"
        >
          Link copied
        </div>

        <article class="post-card rounded-4 overflow-hidden">
          <div class="post-topbar d-flex align-items-center p-3 px-md-4 gap-3">
            <div class="flex-shrink-0 d-flex">
              <button
                class="back-btn d-inline-flex align-items-center justify-content-center p-2 rounded-3"
                @click="goBack"
                title="Back to forum"
              >
                <i class="pi pi-arrow-left"></i>
              </button>
            </div>

            <div class="topbar-divider flex-shrink-0"></div>

            <div class="topbar-meta d-flex flex-column gap-1">
              <div
                class="category-label d-flex align-items-center gap-2 text-uppercase"
              >
                <i class="pi pi-folder-open category-icon"></i>
                <span>{{ post.categoryName ?? "General" }}</span>
              </div>

              <div
                class="tags d-flex flex-wrap gap-1"
                v-if="post.tags && post.tags.length"
              >
                <span
                  v-for="t in post.tags"
                  :key="t.tagId"
                  class="post-tag rounded-4 text-uppercase px-2 py-1"
                  :class="{ 'post-tag-official': t.name === 'Official' }"
                  >{{ t.name }}</span
                >
              </div>
            </div>

            <div class="ms-auto flex-shrink-0">
              <button
                type="button"
                class="share-btn d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3"
                title="Copy link to this post"
                @click="copyPostUrlToClipboard"
              >
                <i class="pi pi-share-alt" aria-hidden="true"></i>
                <span class="share-btn-label d-none d-sm-block">Share</span>
              </button>
            </div>
          </div>

          <div class="post-header d-flex flex-column gap-3 gap-md-4 p-3 p-md-4">
            <div class="author-info d-flex align-items-center">
              <div class="avatar-box flex-shrink-0">
                <img
                  :src="getAvatarSrc(post.authorAvatar)"
                  class="avatar-img"
                  alt="user avatar"
                />
              </div>
              <div class="author-details d-flex flex-column">
                <RouterLink
                  style="text-decoration: none"
                  :to="`/profile?id=${post.authorId}`"
                >
                  <div class="d-flex align-items-center gap-2">
                    <span class="author-name">{{ post.authorName }}</span>
                    <UserRole :role="post.authorRole" />
                  </div>
                </RouterLink>
                <div class="post-timestamp">
                  <span>{{ postTimestamp }}</span>
                </div>
              </div>
            </div>
            <h1 class="post-title fs-2 m-0 text-break">{{ post.title }}</h1>
          </div>

          <div class="content-body px-3 px-md-4 pt-3 pt-md-4" v-html="post.content"></div>

          <section class="post-footer">
            <div
              class="post-footer-row d-flex align-items-center gap-3 px-4 py-4"
            >
              <PostModerationSidebar :post="post" />
              <p
                v-if="post.viewCount != null"
                class="post-view-count m-0 d-flex align-items-center gap-1 flex-shrink-0"
                :aria-label="`${Number(post.viewCount).toLocaleString()} ${post.viewCount === 1 ? 'view' : 'views'}`"
              >
                <i class="pi pi-eye" aria-hidden="true"></i>
                <span class="view-count-figures">{{
                  Number(post.viewCount).toLocaleString()
                }}</span>
                <span class="view-count-word">{{
                  post.viewCount === 1 ? "view" : "views"
                }}</span>
              </p>
            </div>
          </section>
        </article>

        <section
          class="post-card comments-section mt-3 rounded-4 overflow-hidden"
        >
          <CommentSection
            :post-id="postId"
            :comments-disabled="post?.isCommentsDisabled ?? false"
          />
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page {
  background-color: #c8d8d0;
  min-height: 90vh;
}

.page-inner {
  max-width: 900px;
}

.go-back-btn {
  background: #fff;
  border: 1px solid #b0c9bc;
  font-size: 0.82rem;
  font-weight: 700;
  color: #1e4d38;
  cursor: pointer;
  align-self: flex-start;
  box-shadow: 0 1px 4px rgba(30, 77, 56, 0.08);
  transition:
    background 0.15s,
    box-shadow 0.15s;
}

.go-back-btn:hover {
  background: #eaf2ec;
  box-shadow: 0 2px 8px rgba(30, 77, 56, 0.14);
}

.loader {
  color: #4a7a62;
  font-weight: 600;
}

.empty-state {
  background: rgba(255, 255, 255, 0.8);
  border: 1px dashed #8aab97;
  color: #4a7a62;
}

.empty-icon {
  color: #8aab97;
  display: block;
}

.post-card {
  background: #fff;
  border: 1px solid #b0c9bc;
  box-shadow: 0 2px 10px rgba(30, 77, 56, 0.1);
}

.post-topbar {
  background: linear-gradient(135deg, #004b33 0%, #003d4c 100%);
  color: #fff;
  min-height: 60px;
}

.category-wrapper {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  width: 100%;
}

.category-label {
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #a8d5be;
}

.category-icon {
  font-size: 0.8rem;
  color: #7abfa0;
}

.back-btn {
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  cursor: pointer;
  transition: all 0.3s ease;
}

.back-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateX(-4px);
}

.share-btn {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.28);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition:
    background 0.2s ease,
    border-color 0.2s ease,
    transform 0.2s ease;
}

.share-btn:hover {
  background: rgba(255, 255, 255, 0.22);
  border-color: rgba(255, 255, 255, 0.45);
}

.share-btn:active {
  transform: scale(0.98);
}

.share-btn-label {
  line-height: 1;
}

.link-copied-toast {
  position: fixed;
  bottom: 1.25rem;
  right: 1.25rem;
  z-index: 1080;
  background: #0d3d2a;
  color: #e8f5ef;
  font-size: 0.85rem;
  font-weight: 700;
  padding: 10px 16px;
  border-radius: 10px;
  box-shadow: 0 10px 28px rgba(13, 43, 26, 0.35);
  border: 1px solid rgba(255, 255, 255, 0.12);
  animation: link-copied-in 0.22s ease-out;
}

@keyframes link-copied-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.topbar-divider {
  width: 1px;
  height: 14px;
  background: #4a7a62;
}

.topbar-divider {
  width: 1px;
  height: 36px;
  background: rgba(255, 255, 255, 0.25);
}

.post-tag {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.post-tag-official {
  background: rgba(210, 120, 30, 0.25);
  color: #ffd49a;
  border-color: rgba(210, 140, 50, 0.5);
  box-shadow: 0 0 6px rgba(210, 120, 30, 0.2);
}

.author-info {
  gap: 14px;
}

.post-header {
  border-bottom: 1px solid #b8d8ca;
}

.post-title {
  color: #0d2b1a;
  line-height: 1.25;
  letter-spacing: -0.02em;
}

.post-footer-row {
  flex-wrap: nowrap;
  justify-content: space-between;
  min-width: 0;
}

.post-view-count {
  font-size: 0.8rem;
  font-weight: 600;
  color: #5a7d6e;
  margin-inline-start: auto;
  flex-shrink: 0;
}

.view-count-figures {
  font-variant-numeric: tabular-nums;
}

@media (max-width: 415px) {
  .post-footer-row {
    gap: 0.5rem;
    padding-left: 1rem !important;
    padding-right: 1rem !important;
  }

  /* One horizontal row: actions scroll, view count stays visible on the right */
  .post-footer-row :deep(> div) {
    flex: 1 1 auto !important;
    min-width: 0;
    flex-wrap: nowrap !important;
    overflow-x: auto;
    overflow-y: hidden;
    gap: 0.35rem;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }

  .view-count-word {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }
}

.avatar-box {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #7e9291;
  background: #f0f4f2;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author-details {
  gap: 2px;
}

.author-name {
  font-weight: 700;
  font-size: 0.9rem;
  color: #1a2e22;
}
.author-name:hover {
  text-decoration: underline;
  color: #007a4c;
}

.post-timestamp {
  font-size: 0.7rem;
  font-weight: 600;
  color: #7a9a8a;
}

.topbar-meta {
  min-width: 0;
}

:deep(.role-pill) {
  border-radius: 4px !important;
  padding: 2px 3px 1px !important;
  font-size: 0.45rem !important;
  vertical-align: middle !important;
}

.content-body {
  min-height: 150px;
  color: #1a2e22;
  font-size: 1rem;
  line-height: 1.8;
}
.content-body :deep(> *:first-child) {
  margin-top: 0;
}
.content-body :deep(*) {
  white-space: pre-wrap !important;
  word-break: break-word !important;
  overflow-wrap: anywhere !important;
  max-width: 100%;
}

.post-footer {
  border-top: 1px solid #b8d8ca;
  background-color: #f0f4f2;
}

@media (max-width: 640px) {
  .topbar-divider {
    height: auto;
    align-self: stretch;
    margin: 10px 0;
  }
}
</style>