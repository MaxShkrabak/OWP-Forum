<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { getPost } from "@/api/auth";
import ViewPostContent from "@/components/ViewPostContent.vue";


// Access the current route details
const route = useRoute();
const postId = route.params.id;


// Reactive state
const post = ref(null);
const loading = ref(true);
const error = ref(null);


// Fetch data when the component is mounted
onMounted(async () => {
  try {
    // We send the ID at the end of the URL
    post.value = await getPost(postId);
  } catch (err) {
    error.value = "Post could not be loaded.";
    console.error(err);
  } finally {
    loading.value = false;
  }
});
</script>


<template>
  <div class="container">
    <div v-if="loading" class="loader">Fetching post content...</div>


    <div v-else-if="error" class="error empty-state text-center py-5">
      <p class="fw-medium text-secondary">No posts found.</p>
    </div>


    <div v-else-if="post" class="page-container container-sm mt-5">
      <div class="center-container col text-center">
        <div class="post-header row mb-1">
          <div
            class="go-back pi pi-arrow-left col-2 text-white"
            style="font-size: 1.5rem"
          ></div>
          <div class="content-head col">
            <span class="text-white"> title </span>
          </div>
        </div>


        <div class="row p-2">
          <div class="post-content col-md-3 col-lg-2 text-white">sidebar</div>
          <div class="post-sidebar col-md-9 col-lg-10">
            <ViewPostContent :content = post.content /> 
          </div>
        </div>
        <div class="container-sm text-white">
          <div class="post-comments mt-4">comments</div>
        </div>
      </div>
    </div>
  </div>
</template>


<style scoped>
.page-container {
  background-color: black;
}
.center-container {
  background-color: blue;
}


.post-header {
  background-color: pink;
}
.go-back {
  background-color: gray;
}
.content-head {
  background-color: rgb(151, 151, 0);
}


.post-content {
  background-color: rgb(83, 0, 0);
}
.post-sidebar {
  background-color: green;
}


.post-comments {
  background-color: gray;
}


.empty-state {
  background: rgba(255, 255, 255, 0.6);
  border-radius: 20px;
  border: 2px dashed #7e9291;
  padding: 3rem;
}
</style>