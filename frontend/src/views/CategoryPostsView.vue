<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { timeAgo } from "@/utils/timeAgo";

import UserCard from '@/components/UserCard.vue';
import CreatePostButton from '@/components/CreatePostButton.vue';
import ViewReportsButton from '@/components/ViewReportsButton.vue';
import { isLoggedIn, checkAuth } from '@/api/auth';

import userPlaceholder from '@/assets/img/user-pfps-premade/pfp-0.png';

const API = import.meta.env.VITE_API_BASE || 'http://localhost:8080';

const currentPage = ref(1);
const totalPages = ref(1);
const totalPosts = ref(0);

const route = useRoute();
const router = useRouter();

const categoryName = ref('Category');
const posts = ref([]);
const loading = ref(true);
const error = ref(null);

// post limit select
const limit = ref(5);
const limitOptions = [5, 10, 20, 50];

// sort select
const sort = ref('latest');
const sortOptions = [
  { value: 'latest', label: 'Latest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'title', label: 'Title A–Z' },
];

function goBack() {
  router.back();
}

function formatDate(d) {
  if (!d) return '';
  return new Date(d).toLocaleString();
}

function getVoteCount(post) {
  return post.votes ?? 0;
}
function getCommentCount(post) {
  return post.commentCount ?? 0;
}
function getTags(post) {
  return post.tags ?? [];
}
function goToPage(page) {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
}
function flagPost(postId) {
  alert("Flag clicked for post " + postId);
  // TODO: implement flagging functionality
}
function getAvatarSrc(post) {
  const file = post.authorAvatar;

  if (!file) {
    return userPlaceholder;
  }

  try {
    return new URL(`../assets/img/user-pfps-premade/${file}`, import.meta.url).href;
  } catch (e) {
    return userPlaceholder;
  }
}

function getAuthorRole(post) {
  return post.authorRole || 'User';
}

async function loadCategoryPosts() {
  loading.value = true;
  error.value = null;

  const id = route.params.categoryId;

  try {
    const url =
      `${API}/api/categories/${id}/posts` +
      `?limit=${limit.value}&sort=${sort.value}&page=${currentPage.value}`;

    const res = await fetch(url, {
      credentials: 'include', // fine to keep; endpoint is public now
    });

    // 404: category doesn't exist
    if (res.status === 404) {
      let data = {};
      try {
        data = await res.json();
      } catch (_) {}
      error.value = data.error || 'Category not found.';
      posts.value = [];
      loading.value = false;
      return;
    }

    // Any other non-OK (including 401/403 which we don't expect now)
    if (!res.ok) {
      let data = {};
      try {
        data = await res.json();
      } catch (_) {}
      const msg = data.error || `Failed to load posts (${res.status})`;
      throw new Error(msg);
    }

    const data = await res.json();

    categoryName.value = data.categoryName || 'Category';
    posts.value = data.posts || [];

    if (data.meta) {
      totalPages.value = data.meta.totalPages ?? 1;
      totalPosts.value = data.meta.totalPosts ?? 0;
      currentPage.value = data.meta.page ?? currentPage.value;
    }
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

// when limit or sort change reset to page 1 and reload
watch([limit, sort], () => {
  currentPage.value = 1;
  loadCategoryPosts();
});

// when page changes reload
watch(currentPage, () => {
  loadCategoryPosts();
});

onMounted(async () => {
  try {
    await checkAuth();  // sets isLoggedIn if there IS a valid session
  } catch (e) {
    console.warn('Not logged in, continuing as guest');
    // make sure your isLoggedIn flag is false if you control it here
  }
  await loadCategoryPosts();
});


</script>

<template>
  <div class="category-page bg-light">
    <div class="container-fluid forum-body py-4">
      <div class="row justify-content-center">
        <!-- LEFT COLUMN: same feel as Home page -->
        <div class="col-sm-3 col-md-4 col-lg-3 col-xxl-3 text-center justify-content-center">
          <UserCard />

          <!-- Buttons only when logged in -->
          <div class="row text-center justify-content-center" v-show="isLoggedIn">
            <div class="col-auto col-md-12 col-xxl-6">
              <CreatePostButton />
            </div>
            <div class="col-auto col-md-12 col-xxl-6">
              <ViewReportsButton />
            </div>
          </div>

          <!-- Filter By Tags card -->
          <div class="card tag-filter mt-4">
            <div class="card-header py-2">
              <strong>Filter By Tags</strong>
            </div>
            <div class="card-body py-3">
              <!-- placeholder tags for now -->
              <div class="tag-pill mb-2">Education</div>
              <div class="tag-pill mb-2">Informational</div>
              <div class="tag-pill mb-2">SCADA</div>
              <div class="tag-pill mb-2">Research</div>
              <div class="tag-pill mb-2">Data Analysis</div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: header bar + posts + pagination -->
        <div class="col-sm-7 col-md-8 col-lg-9 col-xxl-9">
          <!-- Green header bar with back button, title, limit, sort -->
          <div class="category-header-bar d-flex align-items-center justify-content-between mb-3 px-3 py-2">
            <div class="d-flex align-items-center gap-2">
              <button class="back-circle-btn" @click="goBack">
                ←
              </button>
              <h2 class="mb-0 category-title">{{ categoryName }}</h2>
            </div>

            <div class="d-flex align-items-center gap-4">
              <!-- Post limit -->
              <div class="d-flex align-items-center">
                <span class="header-label me-2">Post Limit:</span>
                <select v-model.number="limit" class="header-select">
                  <option v-for="opt in limitOptions" :key="opt" :value="opt">
                    {{ opt }}
                  </option>
                </select>
              </div>

              <!-- Sort by -->
              <div class="d-flex align-items-center">
                <span class="header-label me-2">Sort by:</span>
                <select v-model="sort" class="header-select">
                  <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Error / loading / posts -->
          <div v-if="error" class="alert alert-danger py-2">
            Error: {{ error }}
          </div>

          <div v-else-if="loading" class="text-muted py-2">
            Loading posts...
          </div>

          <div v-else-if="posts.length === 0" class="text-muted py-2">
            No posts in this category yet.
          </div>

          <div v-else class="post-list">
            <!-- Each post row -->
            <article
              v-for="post in posts"
              :key="post.postId"
              class="post-row d-flex align-items-stretch mb-2"
            >
              <!-- Votes column -->
              <div class="post-vote d-flex flex-column align-items-center justify-content-center px-3 py-2">
                <button class="vote-btn vote-up" type="button">▲</button>
                <span class="vote-count my-1">{{ getVoteCount(post) }}</span>
                <button class="vote-btn vote-down" type="button">▼</button>
                </div>

              <!-- Main content -->
              <div class="post-main flex-grow-1 px-3 py-2">

                <!-- Title + time-ago row -->
                <div class="d-flex justify-content-between align-items-center mb-1">

                <!-- Left: Title + flag -->
                <div class="d-flex align-items-center">
                    <h3 class="post-title mb-0 me-2">
                    {{ post.title }}
                    </h3>

                    <!-- Flag button -->
                    <button
                    class="flag-btn"
                    @click.stop="flagPost(post.postId)"
                    title="Report this post"
                    >
                    🚩
                    </button>
                </div>

                <!-- Right: time ago -->
                <div class="post-time-ago text-muted ms-3">
                    ⏱ {{ timeAgo(post.createdAt) }}
                </div>

                </div>

                <!-- Tags -->
                <div class="post-tags mb-1">
                    <span
                    v-for="tag in getTags(post)"
                    :key="tag"
                    class="tag-pill tag-pill-green me-1 mb-1"
                    >
                    {{ tag }}
                    </span>
                </div>

                <!-- Centered comments row -->
                <div class="post-comments-center text-muted small text-center">
                {{ getCommentCount(post) }} comment<span v-if="getCommentCount(post) !== 1">s</span>
                </div>
            </div>

              <!-- Right user column -->
              <div class="post-user d-flex flex-column justify-content-center px-3 py-2">
                <div class="d-flex align-items-center mb-1">
                  <img :src="getAvatarSrc(post)" alt="User" class="user-avatar-img me-2" /> 
                  <div class="small text-start">
                    <div class="user-name">{{ post.authorName }}</div>
                    <span class="user-role-pill">{{ getAuthorRole(post) }}</span>
                  </div>
                </div>
              </div>
            </article>
            <div class="pagination-row text-center mt-3" v-if="totalPages > 1">
            <!-- Previous -->
            <span
                class="page-link"
                :class="{ disabled: currentPage === 1 }"
                @click="goToPage(currentPage - 1)"
            >
                Prev
            </span>

            <!-- Numbered pages -->
            <span
                v-for="p in totalPages"
                :key="p"
                class="page-link"
                :class="{ active: p === currentPage }"
                @click="goToPage(p)"
            >
                {{ p }}
            </span>

            <!-- Next -->
            <span
                class="page-link next-link"
                :class="{ disabled: currentPage === totalPages }"
                @click="goToPage(currentPage + 1)"
            >
                Next
            </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>



<style scoped>
.category-page {
  background-color: #e5e8ea;
  min-height: 100vh;
}

.tag-filter {
  border-radius: 16px;
  border: none;
}
.tag-filter .card-header {
  background-color: #f5f5f5;
  border-bottom: none;
}
.tag-pill {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  background-color: #f1f3f4;
  color: #333;
}
.tag-pill-green {
  background-color: #2e7d32;
  color: #fff;
}

.category-header-bar {
  background-color: #145a32;
  color: #fff;
  border-radius: 16px;
}
.category-title {
  font-size: 1.4rem;
  font-weight: 600;
}
.back-circle-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background-color: #f5f5f5;
  color: #145a32;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  cursor: pointer;
}
.back-circle-btn:hover {
  background-color: #e0e0e0;
}
.header-label {
  font-size: 0.85rem;
}
.header-select {
  border-radius: 8px;
  border: none;
  padding: 4px 8px;
  font-size: 0.85rem;
}

/* Posts */
.post-row {
  background-color: #ffffff;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.post-vote {
  border-right: 1px solid #eee;
  min-width: 60px;
}

.vote-btn {
  border: none;
  background: transparent;
  font-size: 0.9rem;
  line-height: 1;
  cursor: pointer;
  color: #777;
  padding: 0;
}

.vote-btn:hover {
  color: #145a32; /* green on hover */
}

.vote-count {
  font-weight: 600;
  font-size: 0.9rem;
}

.vote-icon {
  font-size: 1.1rem;
  margin-bottom: 4px;
}
.vote-count {
  font-weight: 600;
}
.post-title {
  font-size: 1rem;
  font-weight: 600;
}
.post-tags {
  display: flex;
  flex-wrap: wrap;
}
.post-user {
  border-left: 1px solid #eee;
  min-width: 140px;
}
.user-avatar-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.user-name {
  font-weight: 600;
  font-size: 0.85rem;
}
.user-role-pill {
  display: inline-block;
  margin-top: 2px;
  padding: 2px 8px;
  border-radius: 999px;
  background-color: #2e7d32;
  color: #fff;
  font-size: 0.65rem;
}
.pagination-row {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

.pagination-row .page-link {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.85rem;
  cursor: pointer;
  color: #555;
}

.pagination-row .page-link:hover {
  background-color: #e0e0e0;
}

.pagination-row .page-link.active {
  background-color: #145a32;
  color: #fff;
  font-weight: 600;
}

.pagination-row .page-link.disabled {
  opacity: 0.4;
  pointer-events: none;
}

.flag-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  padding: 0;
  opacity: 0.7;
  transition: opacity 0.15s ease;
}

.flag-btn:hover {
  opacity: 1;
}

.flag-btn:active {
  transform: scale(0.9);
}


</style>