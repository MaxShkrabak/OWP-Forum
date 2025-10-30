<script setup>
import ForumHeader from '../components/ForumHeader.vue';
import PostIcon from '../assets/img/svg/posts-icon.svg';
import LikeIcon from '../assets/img/svg/like-icon.svg';
import CommIcon from '../assets/img/svg/comment-icon.svg';

import { ref, onMounted } from 'vue';
import { getName } from '@/api/auth';

const role = "Admin";
const fullName = ref(localStorage.getItem('fullName'));
const postsCount = 0;
const likesCount = 0;
const commentsCount = 0;
const activeTab = ref('yourPosts');

onMounted(async () => {
  if (!fullName.value) {
    fullName.value = await getName();
    localStorage.setItem('fullName', fullName.value);
  }
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
      <div class="container-fluid text-center">
        <div class="row">
          <div class="col-2"> <!--User Card-->
            <img src="@\assets\img\svg\fb-logo-525252.svg" class="w-80 object-fit-fill" alt="yes"><br>
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
            <div class="row text-center">
              <div class="col-1"></div> <!--Filler to help align icons-->
              <div class="col-3 text-end">
                <span><img :src=PostIcon class="icon" alt="Post icon"></span> <br>
                <span><img :src=LikeIcon class="icon" alt="Like icon"></span> <br>
                <span><img :src=CommIcon class="icon" alt="comment icon"></span> <br>
              </div>
              <div class="col-md-auto text-start roboto-medium"> 
                Posts: <br> 
                Likes: <br> 
                Comments: </div>
              <div class="col-md-auto text-start">
                 {{ postsCount }} <br>
                 {{ likesCount }} <br>
                 {{ commentsCount }}
              </div>
              <br>
            </div>
            <div class="container text-center align-center"> <br>
              <button class="btn text-center"> <!--Edit Profile button-->
                <span class="roboto-medium text-center">
                  Edit Profile
                </span>
              </button>
            </div>
          </div>
          <div class="col-1"></div><!--Filler between User Card and Posts/Content-->
          <div class="col-9 text-center">
            <div class="row filter"> <!--Filter bar-->
              <button class="col" @click="activeTab = 'yourPosts'">
                <h3>Your Posts</h3>
              </button>
              <button class="col" @click="activeTab = 'followedPosts'">
                <h3>Followed Posts</h3>
              </button>
              <button class="col" @click="activeTab = 'likedPosts'">
                <h3>Liked Posts</h3>
              </button>
              <h4 class="col-1 text-end">Sort By:</h4>
              <div class="col-md-auto text-start">
                <select class="form-select" aria-label="Sort By Selector">
                  <option selected>Latest</option>
                  <option value="1">Likes</option>
                  <option value="2">Comments</option>
                </select>
              </div>
              <div class="col-1">thing</div> <!--Sort by in ASC or DESC-->
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
.filter {
  background-color: white;
  border-radius: 10px;
  padding-top: 1%;
}
.filter button {
  background-color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
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
.col-2{
  background-color: white;
  border-radius: 10px;
  padding: 1%;
}
</style>