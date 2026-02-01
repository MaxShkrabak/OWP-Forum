<script setup>
import ForumHeader from '@/components/layout/ForumHeader.vue';
import pfpModal from '@/components/user/UserPfpModal.vue';
import UserSettings from '@/components/user/UserSettings.vue';
import { useRoute, useRouter } from 'vue-router';
import { getPaginationRange } from '@/utils/pagination';
import { onMounted, ref, watch, computed } from 'vue';
import { fullName, userAvatar, uid } from '@/stores/userStore';
import { fetchPosts as apiGetPosts } from "@/api/posts";
import PostCard from '@/components/forum/PostCard.vue';

import PostIcon from '@/assets/img/svg/posts-icon.svg';
import LikeIcon from '@/assets/img/svg/like-icon.svg';
import CommIcon from '@/assets/img/svg/comment-icon.svg';



const authorId = uid.value;
const role = "Admin";
const postsCount = 0;
const likesCount = 0;
const commentsCount = 0;
const activeTab = ref('yourPosts');

const route = useRoute();
const router = useRouter();
const posts = ref([]);
const loading = ref(true);
const error = ref(null);

const currentPage = ref(1);
const totalPages = ref(1);
const limit = ref(Number(localStorage.getItem('category_limit')) || 5);
const sort = ref(localStorage.getItem('category_sort') || 'latest');

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
        userId: authorId
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

watch(currentPage, getPosts);
watch(activeTab, getPosts);

 onMounted(getPosts);
</script>

<template>
  <body>
    <ForumHeader />
    <pfpModal/>
    <UserSettings/>
    
      <div class="container-fluid text-center">
        
        <div class="row">
          <div class="col-md-2 user-card"> <!--User Card-->
            <button class="user-pfp-btn" data-bs-toggle="modal" data-bs-target="#pfpChange">
              <div class="user-icon-cont">
                <img v-if="userAvatar" :src="userAvatar" class="img-fluid user-icon" alt="User avatar">
                <img v-else src="@\assets\img\user-pfps-premade\pfp-0.png" class="img-fluid user-icon" alt="Default avatar">
            </div>
            </button> <br><br>
            <Role v-if="role === 'Admin'">
              <h5 class="badge text-bg-danger roboto-medium">Admin</h5>
            </Role>
            <Role v-else-if="role === 'Moderator'">
              <h5 class="badge text-bg-warning roboto-medium">Moderator</h5>
            </Role>
            <Role v-else-if="role === 'Student'">
              <h5 class="badge text-bg-info roboto-medium">Student</h5>
            </Role>
            <Role v-else-if="role === 'User'">
              <h5 class="badge text-bg-success roboto-medium">User</h5>
            </Role>
            <br><br>
            <h2 class="roboto-medium">{{ fullName }}</h2><br>
            <div class="row text-start justify-content-evenly roboto-medium">
              <div class="col-1"></div> <!--Filler to help align icons-->
              <div class="col-md-auto">
                <span><img :src=PostIcon class="icon" alt="Post icon"> Posts: </span> <br>
                <span><img :src=LikeIcon class="icon" alt="Like icon"> Likes:</span> <br>
                <span><img :src=CommIcon class="icon" alt="comment icon"> Comments:</span>
              </div>
              <div class="col-md-auto fw-bold">
                 {{ postsCount }} <br>
                 {{ likesCount }} <br>
                 {{ commentsCount }}
              </div>
              <br>
            </div>
            <div class="container text-center align-center"> <br>
              <button class="btn text-center" data-bs-toggle="modal" data-bs-target="#userSettingsModal"> <!--Edit Profile button-->
                <span class="roboto-medium text-center">
                  Edit Profile
                </span>
              </button>
            </div>
          </div>
          <div class="col-md-1"></div><!--Filler between User Card and Posts/Content-->
          <div class="col-md-9 text-center">
            <header class="category-header mb-4">
            <div class="header-main-content">
              <button class="back-btn" @click="router.back()" aria-label="Go Back">
                <i class="pi pi-arrow-left"></i>
              </button>
              
              <div class="v-divider"></div>
              
              <div>
                <span class="category-badge">Looking at</span>
                <h4 class="category-title">
                  <div class="row justify-content-evenly filter"> <!--Filter bar-->
              
              <button class="col-md-auto active text-white" @click="activeTab = 'yourPosts'">
                <h4>Your Posts</h4>
                <div class="activeLine" v-show="activeTab === 'yourPosts'"></div>
              </button>
              <button class="col-md-auto active text-white" @click="activeTab = 'followedPosts'">
                <h4>Followed Posts</h4>
                <div class="activeLine" v-show="activeTab === 'followedPosts'"></div>
              </button>
              <button class="col-md-auto active text-white" @click="activeTab = 'likedPosts'">
                <h4>Liked Posts</h4>
                <div class="activeLine align-top" v-show="activeTab === 'likedPosts'"></div>
              </button>
              </div>
            </h4>
              </div>
            </div>

            <!-- Post sorting -->
            <div class="header-sorting">
              <div class="sort-pill">
                <span class="sort-label">Limit</span>
                <select v-model="limit" class="sort-select">
                  <option v-for="n in [5, 10, 15, 20]" :key="n" :value="n">{{ n }}</option>
                </select>
              </div>
            
              <div class="sort-pill">
                <span class="sort-label">Sort</span>
                <select v-model="sort" class="sort-select">
                  <option value="latest">Latest</option>
                  <option value="oldest">Oldest</option>
                </select>
              </div>
            </div>
          </header>
          <div v-if="loading" class="text-center py-5"><div class="spinner-border text-success"></div></div>
          
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
  font-size: clamp(1.1rem, 3vw, 1.5rem);
  font-weight: 700;
  color: #ffffff;
  line-height: 1.2;
  overflow-wrap: break-word;
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

.activeLine {
  background-color: rgb(139, 139, 253);
  width: 100%;
  height: 3px;
  border-radius: 30%;
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
.filter {
  border-radius: 10px;
  padding-top: 1%;
  padding-bottom: 1%;
  margin-top: 10px;
}
.filter button {
  background-color: transparent;
  border: transparent;
  border-radius: 10px;
  cursor: pointer;
}
.filter-divider {
  width: 1px;
  background-color: black;
  height: 85%;
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