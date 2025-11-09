<script setup>
import CreatePostButton from "@/components/CreatePostButton.vue";
import { ref, onMounted, watch } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "../components/ForumHeader.vue";
import { isLoggedIn, checkAuth } from "@/api/auth";
import axios from "axios";
import UserCard from "@/components/UserCard.vue";

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
        <UserCard />
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
</style>