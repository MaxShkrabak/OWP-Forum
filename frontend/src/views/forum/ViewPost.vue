<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getPost } from "@/api/posts";

import UserRole from "@/components/user/UserRole.vue";
import CommentSection from "@/components/forum/CommentSection.vue";
import ViewPostContent from "@/components/forum/ViewPostContent.vue";
import PostModerationSidebar from "@/components/admin/PostModerationSidebar.vue";

const route = useRoute();
const router = useRouter();
const postId = route.params.id;

const post = ref(null);
const loading = ref(true);
const error = ref(null);

const getLocalDate = (input) => {
  if (!input) return null;
  const dateStr = input.trim().replace(" ", "T") + "Z";
  return new Date(dateStr);
};

const dateSource = computed(() => {
  return post.value?.updatedAt || post.value?.createdAt;
});

const dateLabel = computed(() => {
  return post.value?.updatedAt ? "Edited" : "Posted";
});

const dateText = computed(() => {
  const d = getLocalDate(dateSource.value);
  return d
    ? d.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
      })
    : "";
});

const timeText = computed(() => {
  const d = getLocalDate(dateSource.value);
  return d
    ? d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" })
    : "";
});

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
        <article class="post-card rounded-4 overflow-hidden">
          <div
            class="post-topbar d-flex align-items-center p-3 px-md-4 gap-3"
          >
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
                  :key="t"
                  class="post-tag rounded-4 text-uppercase px-2 py-1"
                  >{{ t.Name }}</span
                >
              </div>
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
                <div class="d-flex align-items-center gap-2">
                  <span class="author-name">{{ post.authorName }}</span>
                  <UserRole :role="post.authorRole" />
                </div>
                <div class="post-timestamp">
                  <span>{{ dateLabel }} {{ dateText }} at {{ timeText }}</span>
                </div>
              </div>
            </div>
            <h1 class="post-title fs-2 m-0 text-break">{{ post.title }}</h1>
          </div>

          <ViewPostContent
            :content="post.content"
            class="px-3 px-md-4 pt-3 pt-md-4"
          />

          <section class="post-footer">
            <PostModerationSidebar :post="post" class="px-4 py-4" />
          </section>
        </article>

        <section
          class="post-card comments-section mt-3 rounded-4 overflow-hidden"
        >
          <CommentSection :post-id="postId" />
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
