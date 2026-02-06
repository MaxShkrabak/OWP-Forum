<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getPost } from "@/api/posts";
import ViewPostContent from "@/components/forum/ViewPostContent.vue";
import ViewPostHeader from "@/components/forum/ViewPostHeader.vue";

const route = useRoute();
const router = useRouter();
const postId = route.params.id;

const post = ref(null);
const loading = ref(true);
const error = ref(null);

function goBack() {
  if (window.history.length > 1) router.back();
  else router.push("/");
}

onMounted(async () => {
  try {
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
    <div class="container position-relative">

      <!-- FLOATING BACK ARROW (SEPARATE FROM HEADER) -->
      <div
        class="go-back-floating"
        role="button"
        tabindex="0"
        @click="goBack"
        @keydown.enter="goBack"
      >
        <span class="back-arrow">←</span>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="loader pt-5">
        <div class="spinner-border"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="error empty-state text-center">
        <p class="fw-medium text-secondary">
          The post has been deleted or does not exist.
        </p>
      </div>

      <!-- Page -->
      <div v-else-if="post" class="page-container">
        <div class="center-container col text-center">

          <!-- HEADER (ALIGNED WITH SIDEBAR + CONTENT EDGES) -->
          <div class="row gx-0">
            <div class="col-12 header-align mb-2">
              <ViewPostHeader />
            </div>
          </div>

          <!-- SIDEBAR + CONTENT (MATCH HEADER WIDTH) -->
          <div class="row gx-0">
            <!-- Sidebar -->
            <div class="post-sidebar col-md-3 col-lg-2 mb-3 mb-md-0">
              sidebar
            </div>

            <!-- Content -->
            <div class="post-content col-md-9 col-lg-10">
              <ViewPostContent :content="post.content" />
            </div>
          </div>

          <!-- COMMENTS (ALREADY FULL WIDTH) -->
          <div class="row">
            <div class="post-comments mt-4">comments</div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Page background */
.page {
  background-color: #cbdad5;
  min-height: 90vh;
  padding-top: 5vh;
  padding-left: 1vh;
  padding-right: 1vh;
}

/* Loader */
.loader {
  display: flex;
  justify-content: center;
  padding-top: 25%;
  padding-bottom: 25%;
}

/* Error */
.empty-state {
  background: rgba(255, 255, 255, 0.6);
  border-radius: 20px;
  border: 2px dashed #7e9291;
  padding: 3rem;
}

/* HEADER alignment */
.header-align {
  padding-left: 0;
  padding-right: 0;
  text-align: left;
}

/* FLOATING BACK ARROW */
.go-back-floating {
  position: absolute;
  left: -88px;   /* further left */
  top: 4px;      /* slightly higher */

  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 3px solid #000;
  background: #fff;

  display: flex;
  align-items: center;
  justify-content: center;

  cursor: pointer;
  z-index: 10;
}

.go-back-floating:hover {
  background: #f1f5f9;
}

.back-arrow {
  font-size: 26px;
  font-weight: 900;
  line-height: 1;
}

/* Sidebar */
.post-sidebar {
  border: 2px solid #000;
  border-radius: 3px;
}

/* Replace Bootstrap gutter between sidebar/content */
.post-content {
  padding-left: 16px;
}

/* Comments */
.post-comments {
  border: 2px solid #000;
  border-radius: 3px;
}

/* Mobile safety */
@media (max-width: 576px) {
  .go-back-floating {
    left: 0;
    top: -64px;
    width: 52px;
    height: 52px;
  }

  .back-arrow {
    font-size: 24px;
  }

  .post-content {
    padding-left: 0;
  }
}
</style>
