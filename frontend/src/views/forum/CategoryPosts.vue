<script setup>
import { ref, onMounted, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import ForumHeader from "@/components/layout/ForumHeader.vue";
import PostCard from "@/components/forum/PostCard.vue";
import UserCard from "@/components/user/UserCard.vue";
import CreatePostButton from "@/components/forum/CreatePostButton.vue";
import ViewReportsButton from "@/components/admin/ViewReportsButton.vue";
import { isLoggedIn } from "@/stores/userStore";
import {
  fetchPosts as apiGetPosts,
  fetchPinnedPosts as apiGetPinnedPosts,
  getFilterTags as apiGetTags,
} from "@/api/posts";
import { getPaginationRange } from "@/utils/pagination";
import AdminPanelButton from "@/components/admin/AdminPanelButton.vue";

const route = useRoute();
const router = useRouter();

const posts = ref([]);
const pinnedPosts = ref([]);
const categoryName = ref("Loading...");
const loading = ref(true);
const error = ref(null);

const currentPage = ref(1);
const totalPages = ref(1);
const totalPosts = ref(0);
const limit = ref(Number(localStorage.getItem("category_limit")) || 10);
const sort = ref(localStorage.getItem("category_sort") || "latest");

const allTags = ref([]);
const selectedTags = ref([]);

async function fetchTags() {
  try {
    allTags.value = await apiGetTags();
  } catch (e) {
    console.error("Error fetching tags:", e);
    allTags.value = [];
  }
}

async function loadCategoryPosts() {
  loading.value = true;
  error.value = null;

  try {
    const args = {
      categoryId: route.params.categoryId,
      sort: sort.value,
      page: currentPage.value,
      limit: limit.value,
      tags: selectedTags.value,
    };

    const [data, pinnedData] = await Promise.all([
      apiGetPosts(args),
      apiGetPinnedPosts(),
    ]);

    const categoryIdNum = Number(route.params.categoryId);

    const pinnedForCategory = (pinnedData?.posts || []).filter(
      (p) => Number(p.categoryId) === categoryIdNum,
    );

    const pinnedIds = new Set(
      pinnedForCategory.map((p) => Number(p.PostID ?? p.postId)),
    );

    const normalPosts = (data.posts || []).filter(
      (p) => !pinnedIds.has(Number(p.PostID ?? p.postId)),
    );

    pinnedPosts.value = pinnedForCategory;
    posts.value =
      currentPage.value === 1
        ? [...pinnedForCategory, ...normalPosts].slice(0, limit.value)
        : normalPosts;
    categoryName.value = data.categoryName || "Category";

    if (data.meta) {
      totalPages.value = data.meta.totalPages || 1;
      totalPosts.value = data.meta.totalPosts ?? 0;
    }
  } catch (e) {
    console.error("Fetch error:", e);
    error.value = e.message;
    posts.value = [];
    pinnedPosts.value = [];
  } finally {
    loading.value = false;
  }
}

function toggleTag(tagName) {
  const i = selectedTags.value.indexOf(tagName);
  if (i >= 0) selectedTags.value.splice(i, 1);
  else selectedTags.value.push(tagName);
}

function clearTags() {
  selectedTags.value = [];
}

watch(
  [sort, selectedTags, limit],
  ([newSort, newTags, newLimit]) => {
    currentPage.value = 1;
    localStorage.setItem("category_sort", newSort);
    localStorage.setItem("category_limit", newLimit);

    loadCategoryPosts();
  },
  { deep: true },
);

const displayedPages = computed(() => {
  return getPaginationRange(currentPage.value, totalPages.value, 2);
});

watch(currentPage, loadCategoryPosts);

watch(
  () => route.params.categoryId,
  () => {
    currentPage.value = 1;
    loadCategoryPosts();
  },
);

onMounted(async () => {
  await Promise.all([loadCategoryPosts(), fetchTags()]);
});
</script>

<template>
  <ForumHeader />

  <div class="category-page">
    <div class="container-xl py-4">
      <div class="row g-4">
        <!-- User card and buttons -->
        <aside class="col-12 col-lg-3 order-1 order-lg-1">
          <div class="sticky-sidebar">
            <UserCard />

            <div class="action-buttons-container mt-3" v-if="isLoggedIn">
              <AdminPanelButton />
              <CreatePostButton @post-refresh="loadCategoryPosts" />
              <ViewReportsButton />
            </div>

            <!-- Tag Filter -->
            <div class="card border-0 shadow-sm rounded-3 mt-4 overflow-hidden">
              <div
                class="filter-header px-3 py-2 d-flex justify-content-between align-items-center"
              >
                <span class="fw-bold small text-uppercase tracking-wider"
                  >Filter By Tags</span
                >
                <button
                  v-if="selectedTags.length > 0"
                  @click="clearTags"
                  class="clear-btn"
                  type="button"
                >
                  Clear
                </button>
              </div>

              <div class="p-3 d-flex flex-wrap gap-2">
                <button
                  v-for="tag in allTags"
                  :key="tag.tagId"
                  type="button"
                  @click="toggleTag(tag.name)"
                  class="tag-pill bi"
                  :class="{ active: selectedTags.includes(tag.name) }"
                >
                  <i
                    class="tag-pill-icon"
                    :class="{
                      'bi-check-circle-fill pe-1': selectedTags.includes(
                        tag.name,
                      ),
                    }"
                  ></i>
                  {{ tag.name }}
                </button>
              </div>
            </div>
          </div>
        </aside>

        <main class="col-12 col-lg-9 order-2 order-lg-2">
          <!-- Category header -->
          <header class="category-header mb-4">
            <div class="header-main-content">
              <button
                class="back-btn"
                @click="router.back()"
                aria-label="Go Back"
              >
                <i class="pi pi-arrow-left"></i>
              </button>

              <div class="v-divider"></div>
              <div>
                <span class="category-badge">Viewing Category</span>
                <h4 class="category-title">{{ categoryName }}</h4>
                <span class="category-post-count">{{ totalPosts }} posts</span>
              </div>
            </div>

            <!-- Post sorting -->
            <div class="header-sorting">
              <div class="sort-pill">
                <span class="sort-label">Limit</span>
                <select v-model="limit" class="sort-select">
                  <option v-for="n in [5, 10, 15, 20]" :key="n" :value="n">
                    {{ n }}
                  </option>
                </select>
              </div>

              <div class="sort-pill">
                <span class="sort-label">Sort</span>
                <select v-model="sort" class="sort-select">
                  <option value="latest">Latest</option>
                  <option value="oldest">Oldest</option>
                  <option value="upvotes">Most Upvotes</option>
                  <option value="comments">Most Comments</option>
                </select>
              </div>
            </div>
          </header>

          <!-- Loading status -->
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-success"></div>
          </div>

          <div v-else-if="error" class="alert alert-danger border-0 shadow-sm">
            {{ error }}
          </div>

          <div v-else class="post-feed">
            <div v-if="posts.length === 0" class="empty-state text-center py-5">
              <p class="fw-medium text-secondary">
                No posts found in this category.
              </p>
            </div>

            <PostCard
              v-for="post in posts"
              :key="post.postId ?? post.PostID"
              :post="post"
              class="mb-3"
              @post-refresh="loadCategoryPosts"
            />

            <!-- Page navigation -->
            <nav v-if="totalPages > 1" class="page-nav-wraper mt-5">
              <button
                class="page-nav-btn"
                :disabled="currentPage === 1"
                @click="currentPage--"
              >
                <i class="pi pi-chevron-left"></i>
              </button>

              <div class="page-pages d-none d-sm-flex">
                <template v-for="p in displayedPages" :key="p">
                  <button
                    v-if="typeof p === 'number'"
                    class="page-num"
                    :class="{ active: p === currentPage }"
                    @click="currentPage = p"
                  >
                    {{ p }}
                  </button>

                  <span v-else class="page-dots">
                    {{ p }}
                  </span>
                </template>
              </div>

              <div class="d-sm-none text-muted small fw-bold">
                {{ currentPage }} / {{ totalPages }}
              </div>

              <button
                class="page-nav-btn"
                :disabled="currentPage === totalPages"
                @click="currentPage++"
              >
                <i class="pi pi-chevron-right"></i>
              </button>
            </nav>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.category-page {
  background-color: #cbdad5;
  min-height: 80vh;
}

.action-buttons-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.category-header {
  background: linear-gradient(135deg, #004b33 0%, #003d4c 100%);
  padding: 1.25rem 1.75rem;
  border-radius: 16px;
  box-shadow: 0 10px 25px -5px rgba(0, 75, 51, 0.3);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}

.header-main-content {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  flex: 0 1 auto;
  min-width: 0;
}

.back-btn {
  padding: 0.7rem 0.7rem;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.back-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateX(-4px);
}

.v-divider {
  width: 1px;
  height: 32px;
  background: rgba(255, 255, 255, 0.2);
}

.category-badge {
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 800;
  color: #f1be48;
  letter-spacing: 1.5px;
}

.category-title {
  margin: 0;
  font-weight: 700;
  color: #ffffff;
  line-height: 1.2;
  overflow-wrap: break-word;
}

.category-post-count {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 0.25rem;
}

.header-sorting {
  display: flex;
  gap: 0.75rem;
  margin-left: auto;
  align-items: center;
}

.sort-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  padding: 6px 6px 6px 14px;
  border-radius: 10px;
  transition: all 0.2s ease;
}

.sort-label {
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.5);
  letter-spacing: 0.8px;
}

.sort-select {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid transparent;
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 700;
  outline: none;
  cursor: pointer;
  padding: 2px 8px;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.sort-select:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #f1be48;
}

.sort-select option {
  background-color: #004b33;
  color: #ffffff;
  font-weight: 600;
  padding: 10px;
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

.post-feed {
  display: flex;
  flex-direction: column;
}

.empty-state {
  background: rgba(255, 255, 255, 0.6);
  border-radius: 20px;
  border: 2px dashed #7e9291;
  padding: 3rem;
}

@media (min-width: 992px) {
  .sticky-sidebar {
    position: sticky;
    top: 2rem;
  }
}

@media (max-width: 768px) {
  .category-header {
    padding: 1rem;
    border-radius: 12px;
    gap: 0.5rem 1.25rem;
  }

  .header-sorting {
    width: 100%;
    justify-content: space-between;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1rem;
  }

  .sort-pill {
    flex: 1;
    justify-content: space-between;
  }
}

@media (max-width: 350px) {
  .header-main-content {
    gap: 0.75rem;
    align-items: flex-start;
  }

  .back-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    flex-shrink: 0;
  }

  .category-title {
    font-size: 1.25rem !important;
  }

  .sort-pill {
    padding: 4px 4px 4px 6px;
    gap: 3px;
  }

  .sort-select {
    font-size: 0.7rem;
    padding: 1px 2px;
  }
}

.filter-header {
  background: linear-gradient(135deg, #0b5f43 0%, #0a4f3b 100%);
  color: #ffffff;
}

.clear-btn {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.45);
  color: #ffffff;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  cursor: pointer;
}

.clear-btn:hover {
  text-decoration: underline;
  background: rgba(255, 255, 255, 0.25);
}

.tag-pill {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 5px;
  padding: 4px 10px;
  font-size: 0.7rem;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
  transition: all 0.12s ease;
}

.tag-pill-icon {
  color: green;
}

.tag-pill:hover {
  background: #ececec;
}

.tag-pill.active {
  background: #007a4b34;
  color: #000000;
  border-color: #007a4c;
}

.tag-pill.active:hover {
  background: #007a4b5b;
  color: #000000;
}
</style>