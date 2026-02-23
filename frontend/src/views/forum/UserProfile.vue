<script setup>
import ForumHeader from '@/components/layout/ForumHeader.vue';
import pfpModal from '@/components/user/UserPfpModal.vue';
import UserSettings from '@/components/user/UserSettings.vue';
import { useRouter, useRoute } from 'vue-router';
import { getPaginationRange } from '@/utils/pagination';
import { onMounted, ref, watch, computed } from 'vue';
import { uid } from '@/stores/userStore';
import { fetchPosts as apiGetPosts } from "@/api/posts";
import { fetchUser } from "@/api/users";
import PostCard from '@/components/forum/PostCard.vue';
import UserCard from '@/components/user/UserCard.vue';

const activeTab = ref('yourPosts');
const isExistingUser = ref(true); // Used to check if the user exists when visiting other user's profile

const route = useRoute();
const router = useRouter();
const posts = ref([]);
const loading = ref(true);
const error = ref(null);

const currentPage = ref(1);
const totalPages = ref(1);
const limit = ref(Number(localStorage.getItem('category_limit')) || 5);
const sort = ref(localStorage.getItem('category_sort') || 'latest');

// Used for user card if it's not the current user
const setAvatar = ref(null);
const setFullName = ref(null);
const setRole = ref(null);

function getUrlParams() {
  const id = route.query.id || uid.value || false;
  return id;
}

function checkIfCurrUser() {
  const urlUserId = getUrlParams();
  return urlUserId === String(uid.value);
}

async function checkAndSetUser() {
  const userId = getUrlParams();
  
  if (userId && userId !== String(uid.value)) {
    try {
      const data = await fetchUser(userId);
      if(data.ok) {
        setAvatar.value = data.user.Avatar || 'pfp-0.png';
        setFullName.value = data.user.FirstName + " " + data.user.LastName;
        setRole.value = data.user.RoleName || 'User';
      }
    } catch (e) {
      isExistingUser.value = false;
      console.error("User fetch error:", e);
    }
  }
}

async function getPosts() {
  loading.value = true;
  error.value = null;

  try {
    totalPages.value = 1;
    if (activeTab.value == 'yourPosts'){
      const data = await apiGetPosts({ 
        limit: limit.value,
        sort: sort.value,
        page: currentPage.value,
        userId: getUrlParams()
      });

      posts.value = data.posts || [];

      if (data.meta) {
        totalPages.value = data.meta.totalPages || 1;
      }

    } else {
      const data = await apiGetPosts({ 
        limit: limit.value,
        sort: sort.value,
        page: currentPage.value,
        userId: 2
      });

      posts.value = data.posts || [];
      
      if (data.meta) {
        totalPages.value = data.meta.totalPages || 1;
      }
    }
      
  } catch (e) {
    console.error("Fetch error:", e);
    error.value = e.message;
    posts.value = [];
  } finally {
    loading.value = false;
  }
}


watch([limit, sort], () => {
  currentPage.value = 1;
  getPosts();

  localStorage.setItem('category_limit', limit.value);
  localStorage.setItem('category_sort', sort.value);
});

const displayedPages = computed(() => {
  return getPaginationRange(currentPage.value, totalPages.value, 2);
});

watch([activeTab, currentPage], getPosts);

watch(() => route.query.id, (newId, oldId) => {
  if (newId !== oldId) {
    isExistingUser.value = true;
    currentPage.value = 1;
    activeTab.value = 'yourPosts';
    checkAndSetUser();
    getPosts();
  }
});

onMounted(() => {
  checkAndSetUser();
  getPosts();
});
</script>

<template>
  <body>
    <ForumHeader />
    <pfpModal/>
    <UserSettings/>
      <div class="container-fluid text-center">
        
        <div v-if="!getUrlParams()" class="empty-state text-center py-5">
          Guest users do not have profiles. Please sign in to view your profile.
        </div>

        <div v-else-if="!isExistingUser" class="empty-state text-center py-5">
          User does not exist.
        </div>

        <div class="row" v-else>

          <UserCard
          is-profile 
          :is-curr-user="checkIfCurrUser()"
          :avatar="setAvatar"
          :new-full-name="setFullName"
          :new-role="setRole"
          class="col-md-3"></UserCard>

          <!--Filter header-->
          <div class="col-md-9 text-center">
            <header class="filter-header mb-4">
            <div class="header-main-content">
              <button class="back-btn" @click="router.back()" aria-label="Go Back">
                <i class="pi pi-arrow-left"></i>
              </button>
              
              <div class="v-divider"></div>
              
              <div>
                <div class="row justify-content-evenly pr-3 fs-4 gap-4" v-if="!checkIfCurrUser()">
                
                <!-- Filter Options -->  
                <button class="col-12 col-sm-12 col-lg-auto filter-options"
                :class="{ 'activeBox' : activeTab === 'yourPosts' }"
                @click="activeTab = 'yourPosts'">
                  <span class="activeText">{{ setFullName + "'s" }} Posts</span>
                  <div class="activeLine"></div>
                </button>
                
                </div>

                <div class="row justify-content-evenly pr-3 fs-4 gap-4" v-else>
                
                <!-- Filter Options -->  
                <button class="col-12 col-sm-12 col-lg-auto filter-options"
                :class="{ 'activeBox' : activeTab === 'yourPosts' }"
                @click="activeTab = 'yourPosts'">
                  <span class="activeText">Your Posts</span>
                  <div class="activeLine"></div>
                </button>

                <button class="col-12 col-sm-12 col-lg-auto filter-options"
                :class="{ 'activeBox' : activeTab === 'followedPosts' }"
                @click="activeTab = 'followedPosts'">
                  <span class="activeText">Followed Posts</span>
                  <div class="activeLine"></div>
                </button>

                <button class="col-12 col-sm-12 col-lg-auto filter-options"
                :class="{ 'activeBox' : activeTab === 'likedPosts' }"
                @click="activeTab = 'likedPosts'">
                  <span class="activeText">Liked Posts</span>
                  <div class="activeLine"></div>
                </button>

                </div>
              </div>
            </div>

            <!-- Post sorting -->
            <div class="header-sorting">
              <div class="sort-pill">
                <span class="sort-label">Limit</span>
                <span class="sort-label sort-label-long">Limit amount of posts</span>
                <select v-model="limit" class="sort-select">
                  <option v-for="n in [5, 10, 15, 20]" :key="n" :value="n">{{ n }}</option>
                </select>
              </div>
            
              <div class="sort-pill">
                <span class="sort-label">Sort</span>
                <span class="sort-label sort-label-long">Sort the posts by</span>
                <select v-model="sort" class="sort-select">
                  <option value="latest">Latest</option>
                  <option value="oldest">Oldest</option>
                  <option value="upvotes">Most Upvotes</option>
                  <option value="comments">Most Comments</option>
                </select>
              </div>
            </div>
          </header>
          <div v-if="loading" class="text-center py-5"><div class="spinner-border text-success"></div></div>
          
          <!-- If filter option doesn't have any posts-->
          <div v-else class="post-feed">
            <div v-if="posts.length === 0" class="empty-state text-center py-5">
              <div class="fw-medium text-secondary">
                <p v-show="activeTab === 'yourPosts'">You have no Posts yet!</p>
                <p v-show="activeTab === 'followedPosts'">You don't follow any Posts yet!</p>
                <p v-show="activeTab === 'likedPosts'">You haven't liked any Posts yet!</p>
              </div>
            
            </div>
            <PostCard v-for="post in posts" :key="post.postId" :post="post" class="mb-3" />
            
            <!-- Page navigation
             -- TODO: would be nice to add "Go to page" input box
             -- for larger number of pages and just make it look cleaner in general
             -->
            <nav v-if="totalPages > 1" class="page-nav-wraper mt-5">
              <button class="page-nav-btn" :disabled="currentPage === 1" @click="currentPage--">
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

              <button class="page-nav-btn" :disabled="currentPage === totalPages" @click="currentPage++">
                <i class="pi pi-chevron-right"></i>
              </button>
            </nav>
          </div>
          
          </div>
        </div>
      </div>
  </body>
</template>

<style scoped>
.filter-header {
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
  font-size: clamp(1.1rem, 3vw, 1.5rem);
  font-weight: 700;
  color: #fff8f8;
  line-height: 1.2;
  overflow-wrap: break-word;
  gap: 30px;
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
.sort-label-long {
  display: none;
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

@media (max-width: 1340px) {
  .header-sorting {
    width: 100%;
    justify-content: space-around;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1rem;
    margin-top: 1rem;
  }
  .sort-pill {
    flex: 1;
    width: 40%;
    justify-content: space-between;
  }
  .sort-label {
    display: none;
  }
  .sort-label-long {
    display: inline;
  }
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
  .sort-label {
    display: inline;
  }
  .sort-label-long {
    display: none;
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
  .sort-pill {
    padding: 4px 4px 4px 6px;
    gap: 3px;
  }
  .sort-select {
    font-size: 0.7rem;
    padding: 1px 2px;
  }
}


.filter-options {
  background: none;
  border: none;
  font-weight: bold;
  color: rgba(255, 255, 255, 0.658);
}

.activeBox {
  .activeText {
    color: white;
    text-shadow: 0 3px 1px rgba(255, 255, 255, 0.021);
  }
  .activeLine {
    background-color: #6dbe4b;
    width: 100%;
    height: 3px;
    border-radius: 10px;
    margin-top: 6px;
    box-shadow: 0 2px 8px #6ebe4b86;
  }
}

.user-pfp-btn {
  border: none;
  background-color: transparent;
}

.user-icon {
  width: 280px;
  border-radius: 50%;
  transition: border-radius 0.3s ease-out;
}
img.user-icon:hover {
  border-radius: 25%;
  border: 5px solid rgb(45, 149, 209);
  transition: border-radius 0.3s ease-in, border 0.2s ease-in-out;
}

.btn {
  height: 44px; /* slightly smaller button to match inputs */
  width: fit-content;
  background: #48773C;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-weight: 600;
  padding: 0 20%;
  cursor: pointer;
  span {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
  }
}
body {
  background-color:#DEE2E6;
}
.icon {
  width: 19px;
}
.container-fluid {
  padding-left: 3%;
  padding-right: 4%;
  padding-top: 3%;
  padding-bottom: 5%;
}
.user-card{
  background-color: rgb(255, 255, 255);
  border-radius: 10px;
  padding: 1%;
  max-width: 300px;
}

@media (max-width: 770px) {
  .user-icon {
  max-width: 50%;
}
}
</style>