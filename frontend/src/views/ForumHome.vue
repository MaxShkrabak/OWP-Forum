<script setup>
import CreatePostButton from "@/components/CreatePostButton.vue";
import { ref, onMounted, watch } from "vue";
import { RouterLink } from "vue-router";
import ForumHeader from "../components/ForumHeader.vue";
import { isLoggedIn, checkAuth } from "@/api/auth";
import axios from "axios";
import UserCard from "@/components/UserCard.vue";
import ViewReportsButton from "@/components/ViewReportsButton.vue";

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
  <body>
    <ForumHeader />
  <!--Forum Home layout in responsive columns-->
  <div class="container-fluid p-5">
    <div class="row text-center">
      <!--First Column-->
      <div class="col-sm-3 col-md-4 col-lg-3 col-xxl-3 text-center justify-content-center">
        <UserCard />
        <!--This will go under the User cards because it's a new row-->
        <div class="row text-center justify-content-center" v-show="isLoggedIn">
          <div class="col-auto col-md-12 col-xxl-6">
            <CreatePostButton />
          </div>
          <div class="col-auto col-md-12 col-xxl-6">
            <ViewReportsButton />
          </div>
        </div>
      </div>
      <!--Second part of the Home page Layout, for the Content-->
      <div class="col-sm-7 col-md-8 col-lg-9 col-xxl-9 py-3 px-5">
        <!--Examples to show the rows in this column-->
        <div class="row bg-success-subtle">content</div>
        <div class="row bg-warning-subtle">content</div>
        <div class="row bg-danger-subtle">content</div>
        <!-- Temporary category buttons with slugs -->
      <div class="row my-2">
        <RouterLink
          class="btn btn-outline-success text-start"
          to="/categories/1/announcements-news"
        >
          Announcements & News
        </RouterLink>
      </div>

      <div class="row my-2">
        <RouterLink
          class="btn btn-outline-success text-start"
          to="/categories/4/help"
        >
          Help
        </RouterLink>
      </div>

      <div class="row my-2">
        <RouterLink
          class="btn btn-outline-success text-start"
          to="/categories/3/research-projects"
        >
          Research Projects
        </RouterLink>
      </div>

      <div class="row my-2">
        <RouterLink
          class="btn btn-outline-success text-start"
          to="/categories/2/training-courses"
        >
          Training Courses
        </RouterLink>
      </div>
      <!-- End temporary category buttons -->
      </div>
    </div>
  </div>
  </body>
</template>

<style scoped>
body {
  background-color: #DEE2E6;
}
</style>