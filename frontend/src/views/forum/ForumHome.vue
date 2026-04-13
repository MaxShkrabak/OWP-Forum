<script setup>
import CreatePostButton from "@/components/forum/CreatePostButton.vue";
import { ref, onMounted, computed, watch } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "@/components/layout/ForumHeader.vue";
import {
  fetchPosts as apiGetPosts,
  fetchPinnedPosts as apiGetPinnedPosts,
  searchPosts as apiSearchPosts,
} from "@/api/posts";
import { isLoggedIn } from "@/stores/userStore";
import UserCard from "@/components/user/UserCard.vue";
import ViewReportsButton from "@/components/admin/ViewReportsButton.vue";
import PostCard from "@/components/forum/PostCard.vue";
import AdminPanelButton from "@/components/admin/AdminPanelButton.vue";
import { getPaginationRange } from "@/utils/pagination";

const postsByCategory = ref([]);
const pinnedPosts = ref([]);
const totalPosts = ref(0);

const loading = ref(true);
const error = ref(null);

const globalPinMessage = ref("");
const globalPinMessageType = ref("success");
let globalPinMessageTimeout = null;

const searchQuery = ref("");
const activeSearchQuery = ref("");
const categorySearch = ref("");
const selectedCategories = ref([]);
const sort = ref("latest");

const INITIAL_LIMIT = 5;
const SEARCH_LIMIT = 10;

const searchResults = ref([]);
const searchMeta = ref({
  page: 1,
  limit: SEARCH_LIMIT,
  totalPosts: 0,
  totalPages: 1,
  hasNextPage: false,
  hasPrevPage: false,
});

const isSearchMode = computed(() => activeSearchQuery.value !== "");

async function fetchHomepageData() {
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

    pinnedPosts.value = pinnedData?.posts || [];
  } catch (e) {
    console.error("Error fetching homepage posts:", e);
    error.value = e.message || "Failed to load posts.";
  } finally {
    loading.value = false;
  }
}

function resetSearchState() {
  searchResults.value = [];
  searchMeta.value = {
    page: 1,
    limit: SEARCH_LIMIT,
    totalPosts: 0,
    totalPages: 1,
    hasNextPage: false,
    hasPrevPage: false,
  };
}

async function fetchSearchResults(page = 1) {
  const q = activeSearchQuery.value.trim();

  if (!q) {
    resetSearchState();
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const data = await apiSearchPosts({
      q,
      page,
      limit: SEARCH_LIMIT,
      sort: sort.value,
      categoryIds: selectedCategories.value,
    });

    searchResults.value = data?.posts || [];
    searchMeta.value = {
      page: data?.meta?.page ?? 1,
      limit: data?.meta?.limit ?? SEARCH_LIMIT,
      totalPosts: data?.meta?.totalPosts ?? 0,
      totalPages: data?.meta?.totalPages ?? 1,
      hasNextPage: data?.meta?.hasNextPage ?? false,
      hasPrevPage: data?.meta?.hasPrevPage ?? false,
    };
  } catch (e) {
    console.error("Error searching posts:", e);
    error.value = e.message || "Failed to search posts.";
    searchResults.value = [];
  } finally {
    loading.value = false;
  }
}

async function handleSearchSubmit() {
  activeSearchQuery.value = searchQuery.value.trim();

  if (activeSearchQuery.value) {
    await fetchSearchResults(1);
  } else {
    resetSearchState();
    await fetchHomepageData();
  }
}

async function clearSearch() {
  searchQuery.value = "";
  activeSearchQuery.value = "";
  resetSearchState();
  await fetchHomepageData();
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
  if (isSearchMode.value) {
    await fetchSearchResults(searchMeta.value.page || 1);
  } else {
    await fetchHomepageData();
  }

  if (payload?.pinMessage) {
    showGlobalPinMessage(payload.pinMessage, payload.pinMessageType || "success");
  }
}

function normalize(str) {
  return (str ?? "").toString().trim().toLowerCase();
}

const filteredCategories = computed(() => {
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
        }));

      const pinnedIds = new Set(
        pinnedForCategory.map((p) => Number(p.postId))
      );

      const homepagePosts = normalizedCategoryPosts
        .filter((p) => !pinnedIds.has(Number(p.postId)))
        .slice(0, Math.max(0, INITIAL_LIMIT - pinnedForCategory.length));

      return {
        ...cat,
        _homepagePosts: [...pinnedForCategory, ...homepagePosts],
      };
    });
});

const filtersActive = computed(
  () =>
    selectedCategories.value.length > 0 ||
    categorySearch.value.trim() !== "" ||
    activeSearchQuery.value !== "",
);

const noResults = computed(() => {
  if (loading.value || error.value) return false;

  if (activeSearchQuery.value) {
    return searchResults.value.length === 0;
  }

  return filteredCategories.value.length === 0;
});

function clearAllFilters() {
  selectedCategories.value = [];
  categorySearch.value = "";
  searchQuery.value = "";
  activeSearchQuery.value = "";
  resetSearchState();
  fetchHomepageData();
}

const categories = [
  { name: 'Announcements & News', icon: 'pi pi-megaphone' },
  { name: 'General', icon: 'pi pi-folder-open' },
  { name: 'Wastewater Collection', icon: 'bi-droplet-half' },
  { name: 'Wastewater Treatment', icon: 'bi-droplet-half' },
  { name: 'Water Distribution', icon: 'bi-droplet' },
  { name: 'Water Treatment', icon: 'bi-droplet' },
]

function getCategoryIcon(categoryName) {
  return (
    categories.find(
      (cat) => cat.name.toLowerCase() === categoryName.toLowerCase()
    )?.icon || "pi-folder-open"
  );
}

function goToSearchPage(page) {
  if (page < 1 || page > searchMeta.value.totalPages) return;
  fetchSearchResults(page);
}

const displayedPages = computed(() => {
  return getPaginationRange(searchMeta.value.page, searchMeta.value.totalPages, 2);
});

watch(sort, async () => {
  if (isSearchMode.value) {
    await fetchSearchResults(1);
  } else {
    await fetchHomepageData();
  }
});

watch(
  selectedCategories,
  async () => {
    if (isSearchMode.value) {
      await fetchSearchResults(1);
    }
  },
  { deep: true },
);

onMounted(async () => {
  await fetchHomepageData();
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
              <CreatePostButton @post-refresh="handlePostRefresh" />
              <ViewReportsButton />
            </div>

            <div class="card border-0 shadow-sm rounded-3 mt-4 overflow-hidden">
              <div class="filter-header px-3 py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold small text-uppercase tracking-wider">Categories</span>
                <button v-if="selectedCategories.length > 0" @click="selectedCategories = []" class="clear-btn">
                  Clear
                </button>
              </div>
              <div class="list-group list-group-flush">
                <label v-for="cat in postsByCategory" :key="cat.categoryId"
                  class="list-group-item list-group-item-action d-flex align-items-center justify-content-between border-0 py-2 px-3 clickable-label"
                  :class="{ 'active-category': selectedCategories.includes(cat.categoryId) }">
                  <div class="d-flex align-items-center">
                    <input type="checkbox" class="form-check-input me-3 mt-0" :value="cat.categoryId"
                      v-model="selectedCategories" />
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
                <input v-model="searchQuery" @keyup.enter="handleSearchSubmit" type="text"
                  placeholder="Search all posts..." class="category-search-input" />
                <button v-if="searchQuery" @click="clearSearch" class="search-clear-btn">
                  ✕
                </button>
              </div>
            </div>

            <div class="d-flex align-items-center gap-4">
              <div class="small text-secondary fw-bold text-uppercase tracking-wider">
                <template v-if="isSearchMode">
                  {{ searchMeta.totalPosts }} results
                </template>
                <template v-else>
                  {{ totalPosts }} posts
                </template>
              </div>

                <select v-model="sort" class="sort-select">
                  <option value="latest">Latest</option>
                  <option value="oldest">Oldest</option>
                  <option value="upvotes">Most Upvotes</option>
                  <option value="comments">Most Comments</option>
                </select>
            </div>
          </div>

          <div v-if="globalPinMessage" class="global-pin-toast" :class="{ error: globalPinMessageType === 'error' }">
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
                <span v-if="activeSearchQuery"> Search "{{ activeSearchQuery }}"</span>
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

            <template v-else-if="isSearchMode">
              <div class="search-results-box mb-4">
                <div class="search-results-header">
                  <h5 class="mb-1">Search Results</h5>
                  <p class="mb-0 search-results-count">
                    {{ searchMeta.totalPosts }} results
                  </p>
                </div>

                <PostCard v-for="post in searchResults" :key="post.postId" :post="post"
                  @post-refresh="handlePostRefresh" />

                <nav v-if="searchMeta.totalPages > 1" class="page-nav-wraper mt-5">
                  <button class="page-nav-btn" :disabled="searchMeta.page === 1"
                    @click="goToSearchPage(searchMeta.page - 1)">
                    <i class="pi pi-chevron-left"></i>
                  </button>

                  <div class="page-pages d-none d-sm-flex">
                    <template v-for="p in displayedPages" :key="p">
                      <button v-if="typeof p === 'number'" class="page-num" :class="{ active: p === searchMeta.page }"
                        @click="goToSearchPage(p)">
                        {{ p }}
                      </button>

                      <span v-else class="page-dots">
                        {{ p }}
                      </span>
                    </template>
                  </div>

                  <div class="d-sm-none text-muted small fw-bold">
                    {{ searchMeta.page }} / {{ searchMeta.totalPages }}
                  </div>

                  <button class="page-nav-btn" :disabled="searchMeta.page === searchMeta.totalPages"
                    @click="goToSearchPage(searchMeta.page + 1)">
                    <i class="pi pi-chevron-right"></i>
                  </button>
                </nav>
              </div>
            </template>

            <template v-else>
              <div v-for="category in filteredCategories" :key="category.categoryId"
                :id="`category-${category.categoryId}`" class="category-group mb-5">
                <RouterLink :to="`/categories/${category.categoryId}`">
                  <div class="category-banner mb-3 shadow-sm">
                    <i :class="getCategoryIcon(category.categoryName) + ' me-2'"></i>
                    <span class="category-title">{{ category.categoryName }}</span>
                  </div>
                </RouterLink>

                <PostCard v-for="post in category._homepagePosts" :key="post.postId" :post="post"
                  @post-refresh="handlePostRefresh" />
              </div>
            </template>
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
  border-radius: 8px;
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
  background: white;
  border: 1px solid transparent;
  color: #081424;
  font-size: 0.90rem;
  font-weight: 700;
  outline: none;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 8px;
  transition: all 0.2s ease;
}
.sort-select option {
  background-color: #ffffff;
  color: #000000;
  font-weight: 600;
  padding: 10px;
}

@media (min-width: 992px) {
  .sticky-sidebar {
    position: sticky;
    top: 1.5rem;
    height: fit-content;
  }
}

.search-results-box {
  background: linear-gradient(165deg, #00475085 0%, #008a7825 70%);
  border-radius: 8px;
  padding: 18px;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.search-results-header {
  margin-bottom: 14px;
  padding-bottom: 10px;
  color: #ffffff;
}

.search-results-count {
  color: white;
  font-size: 0.75rem;
  text-transform: uppercase;
  font-weight: 900;
  letter-spacing: 1.5px;
}

.page-nav-wraper {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding: 0 0 2rem;
}

.page-dots {
  color: rgba(255, 255, 255, 0.85);
  align-self: center;
}

.page-pages {
  display: flex;
  gap: 8px;
  background: #7e9291;
  padding: 6px;
  border-radius: 14px;
}

.page-num {
  width: 42px;
  height: 42px;
  border: none;
  background: transparent;
  border-radius: 10px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.85);
  cursor: pointer;
  transition: all 0.2s ease;
}

.page-num:hover {
  background: rgba(255, 255, 255, 0.226);
  color: #ffffff;
}

.page-num.active {
  background: #035157;
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(3, 81, 87, 0.35);
}

.page-nav-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 2px solid #7e9291;
  background: #ffffff;
  color: #004b33;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.page-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  filter: grayscale(1);
}
</style>