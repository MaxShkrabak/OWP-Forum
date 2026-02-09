<script setup>
import CreatePostButton from '@/components/forum/CreatePostButton.vue';
import { ref, onMounted, computed } from 'vue';
import { RouterLink } from 'vue-router';
import ForumHeader from '@/components/layout/ForumHeader.vue';
import { fetchPosts as apiGetPosts } from '@/api/posts';
import { isLoggedIn } from '@/stores/userStore';
import UserCard from '@/components/user/UserCard.vue';
import ViewReportsButton from '@/components/admin/ViewReportsButton.vue';
import PostCard from '@/components/forum/PostCard.vue';

const postsByCategory = ref([]);
const totalPosts = ref(0);
const loading = ref(true);
const error = ref(null);

const categorySearch = ref('');
const selectedCategories = ref([]);
const sort = ref('latest');

const INITIAL_LIMIT = 5; // post limit per category

// Fetch posts and initialize category state
async function fetchPosts() {
  loading.value = true;
  error.value = null;
  try {
    const data = await apiGetPosts({ sort: sort.value });
    if (data) {
      postsByCategory.value = data.postsByCategory || [];
      totalPosts.value = data.totalPosts || 0;
    }
  } catch (e) {
    console.error('Error fetching posts:', e);
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

// Handle category filtering via search and selection
const filteredCategories = computed(() => {
  const search = categorySearch.value.toLowerCase();
  const selectedCats = selectedCategories.value;

  return postsByCategory.value
    .filter(cat => {
      const matchesSearch = cat.categoryName.toLowerCase().includes(search);
      const matchesSelection =
        selectedCats.length === 0 || selectedCats.includes(cat.categoryId);
      return matchesSearch && matchesSelection;
    });
});

// Detect if any filters are active
const filtersActive = computed(() =>
  selectedCategories.value.length > 0 ||
  categorySearch.value.trim() !== ""
);

// Clear ALL filters at once
function clearAllFilters() {
  selectedCategories.value = [];
  categorySearch.value = "";
}

// Show no-results message when filters return nothing
const noResults = computed(() =>
  !loading.value &&
  !error.value &&
  filteredCategories.value.length === 0
);

// Category icon helper
function getCategoryIcon(categoryName) {
  const name = (categoryName || '').toLowerCase();

  if (name.includes('announcement')) return 'pi pi-megaphone';
  if (name.includes('research')) return 'pi pi-chart-line';
  if (name.includes('help')) return 'pi pi-question-circle';

  return 'pi pi-file';
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
        <!-- Left container -->
        <div class="col-12 col-lg-3 order-1">
          <!-- User card and Action Buttons-->
          <div class="sticky-sidebar">
            <UserCard />

            <div class="action-buttons-container mt-3" v-if="isLoggedIn">
              <CreatePostButton @post-refresh="fetchPosts" />
              <ViewReportsButton />
            </div>

            <!-- Category Filter -->
            <div
              class="card border-0 shadow-sm rounded-3 mt-4 d-none d-lg-block overflow-hidden"
            >
              <div
                class="filter-header px-3 py-2 d-flex justify-content-between align-items-center"
              >
                <span class="fw-bold small text-uppercase tracking-wider"
                  >Categories</span
                >
                <button
                  v-if="selectedCategories.length > 0"
                  @click="selectedCategories = []"
                  class="clear-btn"
                >
                  Clear
                </button>
              </div>
              <div class="list-group list-group-flush">
                <label
                  v-for="cat in postsByCategory"
                  :key="cat.categoryId"
                  class="list-group-item list-group-item-action d-flex align-items-center justify-content-between border-0 py-2 px-3 clickable-label"
                  :class="{
                    'active-category': selectedCategories.includes(
                      cat.categoryId
                    ),
                  }"
                >
                  <div class="d-flex align-items-center">
                    <input
                      type="checkbox"
                      class="form-check-input me-3 mt-0"
                      :value="cat.categoryId"
                      v-model="selectedCategories"
                    />
                    <i
                      :class="getCategoryIcon(cat.categoryName)"
                      class="me-2 text-muted"
                    ></i>
                    <span class="category-name-text">{{
                      cat.categoryName
                    }}</span>
                  </div>
                  <span
                    class="badge rounded-pill bg-light text-dark small border"
                    >{{ cat.postCount }}</span
                  >
                </label>
              </div>
            </div>
              
          </div>
        </div>

        <!-- Search and Sorting -->
        <div class="col-12 col-lg-9 order-2">
          <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 px-1 gap-3"
          >
            <!-- Search Box -->
            <div class="category-search-wrap shadow-sm">
              <i class="pi pi-search ms-3 text-muted"></i>
              <input
                v-model="categorySearch"
                type="text"
                placeholder="Search..."
                class="category-search-input"
              />
              <button v-if="categorySearch" @click="categorySearch = ''" class="search-clear-btn" > ✕ </button>
            </div>
            <div class="d-flex align-items-center gap-4">
              <div
                class="small text-secondary fw-bold text-uppercase tracking-wider"
              >
                {{ totalPosts }} posts
              </div>
              <!-- Sort Selection -->
              <select
                v-model="sort"
                @change="fetchPosts"
                class="sort-select shadow-sm"
              >
                <option value="latest">Latest</option>
                <option value="oldest">Oldest</option>
              </select>
            </div>
          </div>

          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-success"></div>
          </div>
          <div v-else-if="error" class="alert alert-danger border-0 shadow-sm">
            {{ error }}
          </div>

          <template v-else>
            <div
              v-for="category in filteredCategories"
              :key="category.categoryId"
              :id="`category-${category.categoryId}`"
              class="category-group mb-5"
            >
            </div>

            <!-- Active Filter Banner -->
            <div v-if="filtersActive" class="active-filter-banner mb-3">
              <div>
                <strong>Filters active:</strong>
                <span v-if="categorySearch"> Search "{{ categorySearch }}"</span>
                <span v-if="selectedCategories.length"> Categories ({{ selectedCategories.length }})</span>
              </div>
              <button @click="clearAllFilters">Clear all</button>   
            </div>

            <!-- No Results Message -->
            <div v-if="noResults" class="no-results-box">
              <i class="pi pi-search"></i>
              <h5>No posts match your search or filters</h5>
              <p>Try adjusting your search or clearing filters.</p>
              <button @click="categorySearch = ''">Clear search</button>
            </div>

            <div v-for="category in filteredCategories" :key="category.categoryId" :id="`category-${category.categoryId}`" class="category-group mb-5">
              <!-- Category Banner -->
              <RouterLink :to="`/categories/${category.categoryId}`">
                <div class="category-banner mb-3 shadow-sm">
                  <i
                    :class="getCategoryIcon(category.categoryName) + ' me-2'"
                  ></i>
                  <span class="category-title">{{
                    category.categoryName
                  }}</span>
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

/* Active filter banner */
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
  transition: background 0.15s ease, color 0.15s ease;
}

.search-clear-btn:hover {
  background: rgba(0, 0, 0, 0.08);
  color: #000;
}

/* Clear filters button */
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