<script setup>
import CreatePostButton from "@/components/CreatePostButton.vue";
import { ref, onMounted, watch, useId } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "../components/ForumHeader.vue";
import { isLoggedIn, checkAuth } from "@/api/auth";
import axios from "axios";
import UserCard from "@/components/UserCard.vue";
import ViewReportsButton from "@/components/ViewReportsButton.vue";
import { timeAgo } from "@/utils/timeAgo";
import userPlaceholder from "@/assets/img/user-pfps-premade/pfp-0.png";
import ReportingModal from "@/components/ReportingModal.vue";

const API = import.meta.env.VITE_API_BASE || "http://localhost:8080";
const user = ref(null);
const posts = ref([]);
const postsByCategory = ref([]);
const totalPosts = ref(0);
const loading = ref(true);
const error = ref(null);

// Fetch user data if logged in
async function fetchUserData() {
  if (isLoggedIn.value) {
    try {
      const res = await axios.get(`${API}/api/me`, { withCredentials: true });
      if (res.data.ok && res.data.user) {
        user.value = res.data.user;
      }
    } catch (e) {
      console.error("Error fetching user data:", e);
      user.value = null;
    }
  } else {
    user.value = null;
  }
}

// Fetch all posts
async function fetchPosts() {
  loading.value = true;
  error.value = null;
  try {
    const res = await axios.get(`${API}/api/posts`, { withCredentials: true });
    if (res.data) {
      posts.value = res.data.posts || [];
      postsByCategory.value = res.data.postsByCategory || [];
      totalPosts.value = res.data.totalPosts || 0;
    }
  } catch (e) {
    console.error("Error fetching posts:", e);
    error.value = e.message;
    posts.value = [];
    postsByCategory.value = [];
  } finally {
    loading.value = false;
  }
}

// Get avatar source
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

// Get role badge class
function getRoleClass(role) {
  const roleLower = (role || "user").toLowerCase();
  if (roleLower === "admin") return "admin";
  if (roleLower === "moderator") return "moderator";
  if (roleLower === "student") return "student";
  if (roleLower === "teacher") return "teacher";
  return "user";
}

// Get category icon class
function getCategoryIcon(categoryName) {
  const name = (categoryName || "").toLowerCase();
  if (name.includes("announcement") || name.includes("news") || name.includes("official")) {
    return "pi pi-megaphone";
  }
  if (name.includes("design") || name.includes("operation")) {
    return "pi pi-book";
  }
  if (name.includes("research")) {
    return "pi pi-chart-line";
  }
  if (name.includes("question") || name.includes("help")) {
    return "pi pi-question-circle";
  }
  return "pi pi-file";
}

// Watch for auth state changes
watch(isLoggedIn, async () => {
  await fetchUserData();
});

onMounted(async () => {
  await checkAuth();
  await fetchUserData();
  await fetchPosts();
});

const isPost = ref(true);
const useID = ref(0);

function setReportValues(isPost, useID){
  this.isPost = isPost;
  this.useID = useID;
}

</script>

<template>
  <ForumHeader />

  <div class="forum-home py-3">
    <div class="container-xl">
      <div class="row g-4">
        <!-- Left Sidebar -->
        <div class="col-12 col-lg-3 order-2 order-lg-1">
          <UserCard />

          <div class="row text-center justify-content-center" v-show="isLoggedIn">
            <div class="col-auto col-md-12 col-xxl-6">
              <CreatePostButton />
            </div>
            <div class="col-auto col-md-12 col-xxl-6">
              <ViewReportsButton />
            </div>
          </div>

          <div class="card border-0 shadow-sm rounded-2 mt-3">
            <div class="card-body">
              <div class="fw-semibold mb-2">Filter By Tags</div>
              <div class="d-flex flex-column gap-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tagEducation">
                  <label class="form-check-label" for="tagEducation"><span class="badge rounded-pill tag-badge">Education</span></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tagInformational">
                  <label class="form-check-label" for="tagInformational"><span class="badge rounded-pill tag-badge">Informational</span></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tagEvents">
                  <label class="form-check-label" for="tagEvents"><span class="badge rounded-pill tag-badge">Events</span></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tagQuestion">
                  <label class="form-check-label" for="tagQuestion"><span class="badge rounded-pill tag-badge">Question</span></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tagData">
                  <label class="form-check-label" for="tagData"><span class="badge rounded-pill tag-badge">Data Analysis</span></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="tagResearch">
                  <label class="form-check-label" for="tagResearch"><span class="badge rounded-pill tag-badge">Research</span></label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="col-12 col-lg-9 order-1 order-lg-2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small text-secondary">{{ totalPosts }} post{{ totalPosts !== 1 ? 's' : '' }}</div>
            <div class="d-flex align-items-center gap-2 small">
              <span class="text-secondary">Sort by:</span>
              <div class="sort-pill">Latest</div>
            </div>
          </div>

          <!-- Loading state -->
          <div v-if="loading" class="text-muted py-4 text-center">
            Loading posts...
          </div>

          <!-- Error state -->
          <div v-else-if="error" class="alert alert-danger">
            Error: {{ error }}
          </div>

          <!-- No posts -->
          <div v-else-if="postsByCategory.length === 0" class="text-muted py-4 text-center">
            No posts yet.
          </div>

          <!-- Posts grouped by category -->
          <template v-else>
            <div
              v-for="category in postsByCategory"
              :key="category.categoryId"
              class="section mb-4"
            >
              <div class="section-title">{{ category.categoryName }}</div>
              <div class="list-group list-group-flush">
                <div
                  v-for="post in category.posts"
                  :key="post.postId"
                  class="list-group-item px-0 py-3"
                >
                  <div class="d-flex align-items-start gap-3">
                    <div class="like-pill">
                      <i class="pi pi-thumbs-up me-1"></i>{{ post.likeCount }}
                    </div>
                    <i :class="getCategoryIcon(category.categoryName) + ' text-secondary fs-5'"></i>
                    <div class="flex-grow-1">
                      <div class="d-flex flex-wrap align-items-center gap-2">
                        <RouterLink :to="`/categories/${post.categoryId}`" class="post-link">
                          {{ post.title }}
                        </RouterLink>
                      </div>
                      <div class="d-flex flex-wrap gap-1 mt-2">
                        <span
                          v-for="tag in post.tags"
                          :key="tag"
                          class="badge rounded-pill tag-badge"
                        >
                          {{ tag }}
                        </span>
                      </div>
                      <div class="small text-secondary mt-1">
                        <span v-if="post.commentCount === 0">No comments</span>
                        <span v-else>{{ post.commentCount }} comment{{ post.commentCount !== 1 ? 's' : '' }}</span>
                      </div>
                    </div>
                    <div class="text-nowrap text-end small text-secondary">
                      <div class="mb-1">{{ timeAgo(post.createdAt) }}</div>
                      <div class="d-flex align-items-center gap-2 justify-content-end">
                        <i class="pi pi-user"></i>
                        <span>{{ post.authorName }}</span>
                        <span :class="'badge role-badge ' + getRoleClass(post.authorRole)">
                          {{ post.authorRole }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.forum-home {
  background: #F0F2F3;
}

.tag-badge {
  background: #E7EFE5;
  color: #2E6C44;
  font-weight: 600;
}

.sort-pill {
  background: #E7EFE5;
  color: #2E6C44;
  padding: 4px 10px;
  border-radius: 12px;
  font-weight: 600;
}

.section-title {
  display: inline-block;
  background: #2E6C44;
  color: #fff;
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.95rem;
  margin-bottom: 0.5rem;
}

.post-link {
  color: #2B2B2B;
  text-decoration: none;
  font-weight: 600;
}

.post-link:hover {
  text-decoration: underline;
}

.list-group-item {
  border: 0;
  border-bottom: 1px solid #E0E0E0;
  background: transparent;
}

/* Role badges */
.role-badge {
  border-radius: 12px;
  padding: 2px 8px;
  font-weight: 700;
  font-size: 0.70rem;
}

.role-badge.student {
  background: #E6F2FF;
  color: #1E6FDB;
}

.role-badge.admin {
  background: #FFE8E8;
  color: #C03B3B;
}

.role-badge.moderator {
  background: #FFF5DB;
  color: #A87400;
}

.role-badge.user {
  background: #EDEDED;
  color: #555;
}

.role-badge.teacher {
  background: #EAF8EE;
  color: #2E6C44;
}

/* Likes */
.like-pill {
  background: #F6F7F8;
  color: #666;
  border: 1px solid #E0E0E0;
  border-radius: 10px;
  padding: 2px 8px;
  font-weight: 700;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  min-width: fit-content;
}
</style>
