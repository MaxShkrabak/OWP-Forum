<script setup>
import ForumHeader from '../components/ForumHeader.vue';
import pfpModal from '@/components/UserPfpModal.vue';
import UserSettings from '@/components/UserSettings.vue';

import PostIcon from '../assets/img/svg/posts-icon.svg';
import LikeIcon from '../assets/img/svg/like-icon.svg';
import CommIcon from '../assets/img/svg/comment-icon.svg';

import { ref, onMounted, onUnmounted, computed } from 'vue';
  
// Import all images to get default avatar
const allImages = import.meta.glob('../assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)', { eager: true });
const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
});

const role = "Admin";
const fullName = ref(localStorage.getItem('fullName'));
const postsCount = 0;
const likesCount = 0;
const commentsCount = 0;
const activeTab = ref('yourPosts');

// Load avatar from localStorage or use default
const currentAvatar = ref('');

const loadAvatar = () => {
  const savedAvatar = localStorage.getItem('userAvatar');
  if (savedAvatar) {
    currentAvatar.value = savedAvatar;
  } else {
    // Default to pfp-0.png (index 0) if available
    currentAvatar.value = images.value[0] || '';
  }
};

onMounted(async () => {
  loadAvatar();
  
  // Listen for settings updates
  window.addEventListener('settingsUpdated', loadAvatar);
});

// Clean up event listener
onUnmounted(() => {
  window.removeEventListener('settingsUpdated', loadAvatar);
});
</script>

<template>
  <body>
    <ForumHeader />
    <h1>PROFILE PAGE</h1>
    <router-link to="/">Home</router-link> | | |
    <router-link to="/login">login</router-link> | | |
    <router-link to="/register">register</router-link>
    <router-view></router-view>

    <pfpModal/>
    <UserSettings/>
    
      <div class="container-fluid text-center">
        
        <div class="row">
          <div class="col-md-2 user-card"> <!--User Card-->
            <button class="user-pfp-btn" data-bs-toggle="modal" data-bs-target="#pfpChange">
              <div class="user-icon-cont">
                <img v-if="currentAvatar" :src="currentAvatar" class="img-fluid user-icon" alt="User avatar">
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
              <div class="col-md-auto">
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
            <div class="row justify-content-evenly filter"> <!--Filter bar-->
              <button class="col-md-auto active" @click="activeTab = 'yourPosts'">
                <h4>Your Posts</h4>
                <div class="activeLine" v-show="activeTab === 'yourPosts'"></div>
              </button>
              <button class="col-md-auto active" @click="activeTab = 'followedPosts'">
                <h4>Followed Posts</h4>
                <div class="activeLine" v-show="activeTab === 'followedPosts'"></div>
              </button>
              <button class="col-md-auto active" @click="activeTab = 'likedPosts'">
                <h4>Liked Posts</h4>
                <div class="activeLine align-top" v-show="activeTab === 'likedPosts'"></div>
              </button>
              <div class="col-md-auto">
                <div class="filter-divider"></div>
              </div>
              <div class="col-md-auto"><div class="row"><div class="col-md-auto text-center">
                <h5>Sort By:</h5>
              </div>
              <div class="col-md-auto text-center">
                <select class="form-select" aria-label="Sort By Selector">
                  <option selected>Latest</option>
                  <option value="1">Likes</option>
                  <option value="2">Comments</option>
                </select>
              </div>
              <!-- <div class="col-md-auto">thing</div>   Sort by in ASC or DESC-->
            </div>
          </div>
            </div>
            <div class="row"> <!--Shows the content for the selected filter-->
              <div v-show="activeTab === 'yourPosts'">
                show your posts
              </div>
              <div v-show="activeTab === 'followedPosts'">
                show your followed posts
              </div>
              <div v-show="activeTab === 'likedPosts'">
                show your liked posts
              </div>
            </div>
          </div>
        </div>
      </div>
  </body>
</template>

<style scoped>
.activeLine {
  background-color: blue;
  width: 100%;
  height: 3px;
  border-radius: 30%;
}

.user-pfp-btn {
  border: none;
  background-color: transparent;
}

.user-icon {
  border-radius: 50%;
  transition: border-radius 0.3s ease-out;
}
img.user-icon:hover {
  border-radius: 25%;
  border: 5px solid rgb(45, 149, 209);
  transition: border-radius 0.3s ease-in, border 0.2s ease-in-out;
}
.filter {
  background-color: white;
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
}

@media (max-width: 770px) {
  .user-icon {
  max-width: 50%;
}
}
</style>