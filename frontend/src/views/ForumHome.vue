<script setup>
import CreatePostButton from "@/components/CreatePostButton.vue";
import { ref, onMounted, watch } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "../components/ForumHeader.vue";
import { isLoggedIn, checkAuth } from "@/api/auth";
import axios from "axios";
import UserIcon from "@/assets/img/user-pfps-premade/pfp-0.png";

const role = ref("Admin");

const API = import.meta.env.VITE_API_BASE || "http://localhost:8080";
const user = ref(null);

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

// Watch for auth state changes
watch(isLoggedIn, async () => {
  await fetchUserData();
});

onMounted(async () => {
  await checkAuth();
  await fetchUserData();
});
</script>

<template>
  <ForumHeader />
  <h1>HOME PAGE</h1>
  <router-link to="/login">login</router-link> | | |
  <router-link to="/register">register</router-link> | | |
  <router-link to="/create-post">create post</router-link> | | |
  <router-link to="/profile">My Profile</router-link>
  <router-view></router-view>

  <!--Forum Home layout in responsive columns-->
  <div class="container-fluid">
    <div class="row text-center">
      <!--First Column-->
      <div class="col-sm-3 col-md-4 col-lg-3 col-xxl-2 text-center justify-content-center">
        <!--Signed in User Card-->
        <div
          class="user-cards border border-2 border-dark-subtle rounded-1 my-3"
          v-if="isLoggedIn"
        >
          <!--Puts Card into a row-->
          <div class="row p-3">
            <!--First part using 5 of the 12 Cols, for PFP and role under it-->
            <div class="col-5 avatar align-center">
              <!--PFP-->
              <h5 class="pfp">
                <img :src="UserIcon" alt="icon" class="img-fluid rounded-50" />
              </h5>
              <!--Conditional role badges based on var 'role'-->
              <h5 v-if="role === 'User'">
                <span class="badge text-bg-success">User</span>
              </h5>
              <h5 v-else-if="role === 'Student'">
                <span class="badge text-bg-primary">Student</span>
              </h5>
              <h5 v-else-if="role === 'Moderator'">
                <span class="badge text-bg-warning">Moderator</span>
              </h5>
              <h5 v-else-if="role === 'Admin'">
                <span class="badge text-bg-danger">Admin</span>
              </h5>
            </div>

            <!--Second part of User-Card using the last 7 cols-->
            <div class="col-7 text-start align-center">
              <!--Name-->
              <div class="row fw-bold"><h5>Username Username</h5></div>
              <!--Activity report-->
              <div class="row">
                <span
                  >Posts: 222 <br />
                  Likes: 222 <br />
                  Comments: 222</span
                >
              </div>
            </div>
          </div>
        </div>
        <!--NOT Signed in User-Card-->
        <div
          class="user-cards border border-2 border-dark-subtle rounded-1 my-3"
          v-else
        >
          <div class="row p-3">
            <!--PFP and role part-->
            <div class="col-5 avatar align-center">
              <h5 class="pfp">
                <img :src="UserIcon" alt="icon" class="img-fluid" />
              </h5>
              <h5><span class="badge text-bg-success">Guest</span></h5>
            </div>
            <!--Welcome part-->
            <div class="col-7">
              <div class="row pt-2 text-start fw-bold">
                <h5>Welcome!</h5>
                <br />
                <span
                  >Here, you will find your activity report, once you
                  <RouterLink to="/login" class="sign-in-link"
                    >sign in</RouterLink
                  >!</span
                >
              </div>
            </div>
          </div>
        </div>
        <!--This will go under the User cards because it's a new row-->
        <div class="row">
          <CreatePostButton />
        </div>
      </div>
      <!--Second part of the Home page Layout, for the Content-->
      <div class="col-sm-7 col-md-8 col-lg-9 col-xxl-10 py-3 px-5">
        <!--Examples to show the rows in this column-->
        <div class="row bg-success-subtle">content</div>
        <div class="row bg-warning-subtle">content</div>
        <div class="row bg-danger-subtle">content</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pfp img {
  border-radius: 50%;
}
.avatar img {
  width: 100px;
}

.sign-in-link {
  color: #087b0b;
  font-weight: 700;
  text-decoration: none;
}

.sign-in-link:hover {
  text-decoration: underline;
}
</style>