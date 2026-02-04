<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { getPost } from "@/api/posts";
import ViewPostContent from "@/components/forum/ViewPostContent.vue";

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
  <div class="page">
    <div class="container">
      <!-- Loads while it fetches -->
      <div v-if="loading" class="loader pt-5">
          <div class="spinner-border"></div>
      </div>

      <!-- Displays error if the Post doen't exist -->
      <div v-else-if="error" class="error empty-state text-center">
        <p class="fw-medium text-secondary">The post has been deleted or does not exist.</p>
      </div>

      <!-- If the Post is found, constructs page -->
      <div v-else-if="post" class="page-container">
        <div class="center-container col text-center">
          
          <!-- Header Part -->
          <div class="post-header row mb-1">
              
            <!-- Go Back arrow -->
            <div
              class="go-back pi pi-arrow-left col-1 text-white"
              style="font-size: 1.5rem"
            ></div>

            <!-- Header Card -->
            <div class="content-head col">
              <span class="text-white"> title </span>
            </div>
          </div>

          <!-- Sidebar and Content in a row -->
          <div class="row">

            <!-- Sidebar part -->
            <div class="post-sidebar col-md-3 col-lg-2 text-white mb-3 mb-md-0">sidebar</div>

            <!-- Content part -->
            <div class="post-content col-md-9 col-lg-10">
              <ViewPostContent :content = post.content /> 
            </div>

          </div>
          <!-- Comment Section -->
          <div class="row">
            <div class="post-comments mt-4">comments</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped> 
.page {
  background-color: #cbdad5;
  min-height: 90vh;
  padding-top: 5vh;
  padding-left: 1vh;
  padding-right: 1vh;
}
.loader {
    display: flex;
    justify-content: center;
    padding-top: 25%;
    padding-bottom: 25%;
}

/* Error when post not found */
.empty-state {
  background: rgba(255, 255, 255, 0.6);
  border-radius: 20px;
  border: 2px dashed #7e9291;
  padding: 3rem;
}

/* Central page layout */
.page-container {
  background-color: none;
}
.center-container {
  background-color: none;
}

/* Header */
.post-header {

}
.go-back {
  background-color: none;
  border: 2px black solid;
  border-radius: 3px;
}
.content-head {
  background-color: none;
  border: 2px black solid;
  border-radius: 3px;
}

/* Body */
.post-content {
  background-color: none;
}
.post-sidebar {
  background-color: none;
  border: 2px black solid;
  border-radius: 3px;
}

/* Comments */
.post-comments {
  background-color: none;
  border: 2px black solid;
  border-radius: 3px;
}
</style>