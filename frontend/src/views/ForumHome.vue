<script setup>
import CreatePostButton from "@/components/CreatePostButton.vue";
import { ref, onMounted, computed, reactive, watch } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "../components/ForumHeader.vue";
import { fetchPosts as apiGetPosts } from "@/api/auth";
import { isLoggedIn } from "@/stores/userStore";
import UserCard from "@/components/UserCard.vue";
import ViewReportsButton from "@/components/ViewReportsButton.vue";
import PostCard from "@/components/PostCard.vue";
import { loadVotesForPosts } from "@/utils/voteService";

const postsByCategory = ref([]);
const totalPosts = ref(0);
const loading = ref(true);
const error = ref(null);   // error message

const categorySearch = ref(""); // todo: make this general search
const selectedCategories = ref([]);
const INITIAL_LIMIT = ref(5); // post limit per category

function loadMore() {
  INITIAL_LIMIT.value = Math.min(20, INITIAL_LIMIT.value + 5);
}

async function fetchPosts() {
  loading.value = true;
  error.value = null;

  try {
    const data = await apiGetPosts({ limit: 200 }); // big pull for home
    const allPosts = data.postsByCategory.flatMap(c => c.posts);
    await loadVotesForPosts(allPosts);

    postsByCategory.value = data.postsByCategory;
    totalPosts.value = data.totalPosts;
  } catch (e) {
    error.value = e?.message ?? "Failed to load posts";
  } finally {
    loading.value = false;
  }
}

// Handle category filtering via search and selection
const filteredCategories = computed(() => {
  return postsByCategory.value.filter(cat => {
    const matchesSearch = cat.categoryName.toLowerCase().includes(categorySearch.value.toLowerCase());
    const matchesSelection = selectedCategories.value.length === 0 || selectedCategories.value.includes(cat.categoryId);
    return matchesSearch && matchesSelection;
  });
});

// Category icon helper
function getCategoryIcon(categoryName) {
  const name = (categoryName || "").toLowerCase();

  if (name.includes("announcement")) return "pi pi-megaphone";
  if (name.includes("research")) return "pi pi-chart-line";
  if (name.includes("help")) return "pi pi-question-circle";

  return "pi pi-file";
}

onMounted(fetchPosts);
</script>

<template>
  <ForumHeader />
  
  <div class="forum-home py-4">
    <div class="container-xl">
      <div class="row g-4">
        <!-- Left container -->
        <div class="col-12 col-lg-3 order-1">
          <!-- User card and Action Buttons-->
          <div class="sticky-sidebar">
            
            <UserCard />
            
            <div class="action-buttons-container mt-3" v-if="isLoggedIn">
              <CreatePostButton @post-refresh="fetchPosts"/>
              <ViewReportsButton />
            </div>

            <!-- Category Filter -->
            <div class="card border-0 shadow-sm rounded-3 mt-4 d-none d-lg-block overflow-hidden">
              <div class="filter-header px-3 py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold small text-uppercase tracking-wider">Categories</span>
                <button v-if="selectedCategories.length > 0" @click="selectedCategories = []" class="clear-btn">Clear</button>
              </div>
              <div class="list-group list-group-flush">
                <label
                  v-for="cat in postsByCategory"
                  :key="cat.categoryId"
                  class="list-group-item list-group-item-action d-flex align-items-center justify-content-between border-0 py-2 px-3 clickable-label"
                  :class="{ 'active-category': selectedCategories.includes(cat.categoryId) }"
                >
                  <div class="d-flex align-items-center">
                    <input type="checkbox" class="form-check-input me-3 mt-0" :value="cat.categoryId" v-model="selectedCategories">
                    <i :class="getCategoryIcon(cat.categoryName)" class="me-2 text-muted"></i>
                    <span class="category-name-text">{{ cat.categoryName }}</span>
                  </div>
                  <span class="badge rounded-pill bg-light text-dark small border">{{ cat.postCount }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Search and Sorting -->
        <div class="col-12 col-lg-9 order-2">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 px-1 gap-3">
            <!-- Search Box -->
            <div class="category-search-wrap shadow-sm">
              <i class="pi pi-search ms-3 text-muted"></i>
              <input v-model="categorySearch" type="text" placeholder="Search..." class="category-search-input" />
            </div>
            <div class="d-flex align-items-center gap-4">
              <div class="small text-secondary fw-bold text-uppercase tracking-wider">{{ totalPosts }} posts</div>
              <!-- Sort Selection -->
              <select class="sort-select shadow-sm">
                <option>Recent</option>
                <option>Popular</option>
              </select>
            </div>
          </div>

          <div v-if="loading" class="text-center py-5"><div class="spinner-border text-success"></div></div>
          <div v-else-if="error" class="alert alert-danger border-0 shadow-sm">{{ error }}</div>
          
          <template v-else>
            <div v-for="category in filteredCategories" :key="category.categoryId" :id="`category-${category.categoryId}`" class="category-group mb-5">
              <!-- Category Banner -->
              <RouterLink :to="`/categories/${category.categoryId}`" >
                <div class="category-banner mb-3 shadow-sm">
                  <i :class="getCategoryIcon(category.categoryName) + ' me-2'"></i>
                  <span class="category-title">{{ category.categoryName }}</span>
                </div>
              </RouterLink>

              <!-- Post information card -->
              <PostCard 
                v-for="post in category.posts.slice(0, INITIAL_LIMIT)"
                :key="post.postId"
                :post="post"
              />
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.forum-home {
  background-color: #cbdad5;
  min-height: 100vh;
}

.action-buttons-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.filter-header {
  background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
  color: #ffffff;
}

/* Clear filters button */
.clear-btn {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(4px);
  font-size: 0.65rem;
  font-weight: 700;
  color: #FFFFFF;
  border-radius: 6px;
  text-transform: uppercase;
  cursor: pointer;
}
.clear-btn:hover {
  text-decoration: underline;
}

.clickable-label {
  cursor: pointer;
  transition: background 0.2s;
}
.clickable-label:hover {
  background-color: #e2e3e4;
}

.active-category {
  background-color: #e8f5e9 !important;
  border-left: 3px solid #2E6C44 !important;
}

.category-name-text {
  font-size: 0.85rem;
  font-weight: 500;
  color: #444;
}

.category-banner {
  background: linear-gradient(135deg, #004b33 0%, #003d4c 100%);
  color: white;
  padding: 6px 14px;
  border-radius: 6px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  position: relative;
  overflow: hidden;
}
.category-banner::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.1),
    transparent
  );
  transition: 0.5s;
}
.category-banner:hover {
  filter: brightness(1.1);
  transform: translateY(-2px); 
  box-shadow: 0 4px 12px rgba(6, 78, 59, 0.25) !important;
}
.category-banner:hover::after {
  left: 100%;
}

.category-title {
  text-transform: uppercase;
}

.category-search-wrap {
  background: white;
  border-radius: 50px;
  display: flex;
  align-items: center;
  flex-grow: 1;
  max-width: 350px;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.category-search-input {
  border: none;
  padding: 8px 15px;
  width: 100%;
  border-radius: 50px;
  font-size: 0.85rem;
  outline: none;
}

.sort-select {
  padding: 3px 8px 3px 8px;
  color: #201e0f;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 8px;
  outline: none;
}

@media (min-width: 992px) {
  .sticky-sidebar {
    position: sticky;
    top: 1.5rem;
    height: fit-content;
  }
}
</style>