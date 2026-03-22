<script setup>
import CreatePostButton from "@/components/forum/CreatePostButton.vue";
import { ref, onMounted, computed } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "@/components/layout/ForumHeader.vue";
import { fetchPosts as apiGetPosts, fetchPinnedPosts as apiGetPinnedPosts } from "@/api/posts";
import { isLoggedIn } from "@/stores/userStore";
import UserCard from "@/components/user/UserCard.vue";
import ViewReportsButton from "@/components/admin/ViewReportsButton.vue";
import PostCard from "@/components/forum/PostCard.vue";
import AdminPanelButton from "@/components/admin/AdminPanelButton.vue";

const postsByCategory = ref([]);
const pinnedPosts = ref([]);
const totalPosts = ref(0);
const loading = ref(true);
const error = ref(null);

const globalPinMessage = ref("");
const globalPinMessageType = ref("success");
let globalPinMessageTimeout = null;

const searchQuery = ref("");
const categorySearch = ref("");
const selectedCategories = ref([]);
const sort = ref("latest");

const INITIAL_LIMIT = 5;

async function fetchPosts() {
  loading.value = true;
  error.value = null;
  try {
    const [postsData, pinnedData] = await Promise.all([
      apiGetPosts({ sort: sort.value }),
      apiGetPinnedPosts(),
    ]);

    if (postsData) {
      postsByCategory.value = postsData.postsByCategory || [];
      totalPosts.value = postsData.totalPosts || 0;
    }

    if (pinnedData?.posts) {
      pinnedPosts.value = pinnedData.posts.map((post) => ({
        ...post,
        isPinned: true,
      }));
    } else {
      pinnedPosts.value = [];
    }
  } catch (e) {
    console.error("Error fetching posts:", e);
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

function showGlobalPinMessage(message, type = "success") {
  globalPinMessage.value = message;
  globalPinMessageType.value = type;

  if (globalPinMessageTimeout) {
    clearTimeout(globalPinMessageTimeout);
  }

  globalPinMessageTimeout = setTimeout(() => {
    globalPinMessage.value = "";
  }, 2200);
}

async function handlePostRefresh(payload = null) {
  await fetchPosts();

  if (payload?.pinMessage) {
    showGlobalPinMessage(payload.pinMessage, payload.pinMessageType || "success");
  }
}

function normalize(str) {
  return (str ?? "").toString().trim().toLowerCase();
}

function postMatchesGeneralSearch(post, categoryName, q) {
  if (!q) return true;
  const nq = normalize(q);

  const title = normalize(post?.title);
  const author = normalize(post?.authorName);
  const cat = normalize(categoryName);
  const tags = Array.isArray(post?.tags) ? post.tags : [];
  const authorRole = normalize(post?.authorRole);

  return (
    title.includes(nq) ||
    author.includes(nq) ||
    cat.includes(nq) ||
    authorRole.includes(nq) ||
    tags.some((t) => normalize(t).includes(nq))
  );
}


const filteredCategories = computed(() => {
  const q = normalize(searchQuery.value);
  const catQ = normalize(categorySearch.value);

  return postsByCategory.value
    .filter((cat) => {
      const matchesSelection =
        selectedCategories.value.length === 0 ||
        selectedCategories.value.includes(cat.categoryId);

      const matchesCatSearch = normalize(cat.categoryName).includes(catQ);

      return matchesSelection && matchesCatSearch;
    })
    .map((cat) => {
      const normalizedCategoryPosts = (cat.posts || []).map((p) => ({
        ...p,
        categoryName: cat.categoryName,
        categoryId: cat.categoryId,
      }));

      const pinnedForCategory = pinnedPosts.value
        .filter((p) => Number(p.categoryId) === Number(cat.categoryId))
        .map((p) => ({
          ...p,
          categoryName: cat.categoryName,
          categoryId: cat.categoryId,
        }))
        .filter((p) => postMatchesGeneralSearch(p, cat.categoryName, q));

      const pinnedIds = new Set(
        pinnedForCategory.map((p) => Number(p.PostID ?? p.postId))
      );

      const homepagePosts = normalizedCategoryPosts
        .filter((p) => !pinnedIds.has(Number(p.PostID ?? p.postId)))
        .slice(0, INITIAL_LIMIT)
        .filter((p) => postMatchesGeneralSearch(p, cat.categoryName, q));

      return {
        ...cat,
        _homepagePosts: [...pinnedForCategory, ...homepagePosts],
      };
    })
    .filter((cat) => {
      if (!q) return true;
      return (cat._homepagePosts?.length || 0) > 0;
    });
});

const filtersActive = computed(
  () =>
    selectedCategories.value.length > 0 ||
    categorySearch.value.trim() !== "" ||
    searchQuery.value.trim() !== "",
);

function clearAllFilters() {
  selectedCategories.value = [];
  categorySearch.value = "";
  searchQuery.value = "";
}

const noResults = computed(
  () => !loading.value && !error.value && filteredCategories.value.length === 0,
);

function getCategoryIcon(categoryName) {
  const name = (categoryName || "").toLowerCase();
  if (name.includes("announcement")) return "pi pi-megaphone";
  if (name.includes("research")) return "pi pi-chart-line";
  if (name.includes("help")) return "pi pi-question-circle";
  return "pi pi-folder-open";
}

onMounted(async () => {
  await fetchPosts();
});
</script>

<template>
  <ForumHeader />

  <div class="forum-home py-4">
    <div class="container-xl">
      <div class="row g-4">
        <div class="col-12 col-lg-3 order-1">
          <div class="sticky-sidebar">
            <UserCard />

            <div class="action-buttons-container mt-3" v-if="isLoggedIn">
              <AdminPanelButton />
              <CreatePostButton @post-refresh="fetchPosts" />
              <ViewReportsButton />
            </div>

            <div class="card border-0 shadow-sm rounded-3 mt-4 d-none d-lg-block overflow-hidden">
              <div class="filter-header px-3 py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold small text-uppercase tracking-wider">Categories</span>
                <button v-if="selectedCategories.length > 0" @click="selectedCategories = []" class="clear-btn">
                  Clear
                </button>
              </div>
              <div class="list-group list-group-flush">
                <label
                  v-for="cat in postsByCategory"
                  :key="cat.categoryId"
                  class="list-group-item list-group-item-action d-flex align-items-center justify-content-between border-0 py-2 px-3 clickable-label"
                  :class="{ 'active-category': selectedCategories.includes(cat.categoryId) }"
                >
                  <div class="d-flex align-items-center">
                    <input
                      type="checkbox"
                      class="form-check-input me-3 mt-0"
                      :value="cat.categoryId"
                      v-model="selectedCategories"
                    />
                    <i :class="getCategoryIcon(cat.categoryName)" class="me-2 text-muted"></i>
                    <span class="category-name-text">{{ cat.categoryName }}</span>
                  </div>
                  <span class="badge rounded-pill bg-light text-dark small border">{{ cat.postCount }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-9 order-2">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 px-1 gap-3">
            <div class="d-flex gap-2 align-items-center flex-grow-1">
              <div class="category-search-wrap shadow-sm">
                <i class="pi pi-search ms-3 text-muted"></i>
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search posts..."
                  class="category-search-input"
                />
                <button v-if="searchQuery" @click="searchQuery = ''" class="search-clear-btn">
                  ✕
                </button>
              </div>
            </div>

            <div class="d-flex align-items-center gap-4">
              <div class="small text-secondary fw-bold text-uppercase tracking-wider">
                {{ totalPosts }} posts
              </div>
              <select v-model="sort" @change="fetchPosts" class="sort-select shadow-sm">
                <option value="latest">Latest</option>
                <option value="oldest">Oldest</option>
                <option value="upvotes">Most Upvotes</option>
                <option value="comments">Most Comments</option>
              </select>
            </div>
          </div>

          <div
            v-if="globalPinMessage"
            class="global-pin-toast"
            :class="{ error: globalPinMessageType === 'error' }"
          >
            {{ globalPinMessage }}
          </div>

          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-success"></div>
          </div>

          <div v-else-if="error" class="alert alert-danger border-0 shadow-sm">
            {{ error }}
          </div>

          <template v-else>
            <div v-if="filtersActive" class="active-filter-banner mb-3">
              <div>
                <strong>Filters active:</strong>
                <span v-if="searchQuery"> Search "{{ searchQuery }}"</span>
                <span v-if="selectedCategories.length">
                  Categories ({{ selectedCategories.length }})
                </span>
              </div>
              <button @click="clearAllFilters">Clear all</button>
            </div>

            <div v-if="noResults" class="no-results-box">
              <i class="pi pi-search"></i>
              <h5>No posts match your search or filters</h5>
              <p>Try adjusting your search or clearing filters.</p>
              <button @click="clearAllFilters">Reset everything</button>
            </div>

            <div
              v-for="category in filteredCategories"
              :key="category.categoryId"
              :id="`category-${category.categoryId}`"
              class="category-group mb-5"
            >
              <RouterLink :to="`/categories/${category.categoryId}`">
                <div class="category-banner mb-3 shadow-sm">
                  <i :class="getCategoryIcon(category.categoryName) + ' me-2'"></i>
                  <span class="category-title">{{ category.categoryName }}</span>
                </div>
              </RouterLink>

              <PostCard v-for="post in category._homepagePosts" :key="post.postId" :post="post" />
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

.global-pin-toast {
  position: sticky;
  top: 12px;
  z-index: 30;
  margin-bottom: 14px;
  background: #1f7a45;
  color: white;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 10px 14px;
  border-radius: 10px;
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
  animation: global-pin-toast-in 0.2s ease-out;
}

.global-pin-toast.error {
  background: #b42318;
}

@keyframes global-pin-toast-in {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.active-filter-banner {
  background: #ffffff;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 10px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
}

.active-filter-banner button {
  border: none;
  background: #c62828;
  color: white;
  padding: 5px 10px;
  border-radius: 20px;
  font-weight: 600;
  cursor: pointer;
}

.active-filter-banner button:hover {
  background: #b71c1c;
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

.search-clear-btn {
  border: none;
  background: transparent;
  color: #666;
  font-size: 15px;
  margin-right: 12px;
  cursor: pointer;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition:
    background 0.15s ease,
    color 0.15s ease;
}

.search-clear-btn:hover {
  background: rgba(0, 0, 0, 0.08);
  color: #000;
}

.clear-btn {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(4px);
  font-size: 0.65rem;
  font-weight: 700;
  color: #ffffff;
  border-radius: 6px;
  text-transform: uppercase;
  cursor: pointer;
  opacity: 0.9;
}

.clear-btn:hover {
  text-decoration: underline;
}

.no-results-box {
  text-align: center;
  padding: 40px 20px;
  background: #ffffff;
  border-radius: 10px;
  border: 1px dashed #ccc;
  margin-bottom: 30px;
}

.no-results-box i {
  font-size: 28px;
  margin-bottom: 10px;
  color: #888;
}

.no-results-box h5 {
  margin: 8px 0 4px;
}

.no-results-box button {
  margin-top: 12px;
  background: #145a32;
  color: white;
  border: none;
  padding: 6px 14px;
  border-radius: 20px;
  font-weight: 600;
  cursor: pointer;
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
  border-left: 3px solid #2e6c44 !important;
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
  content: "";
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
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