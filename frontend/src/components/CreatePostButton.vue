<script setup>
import { isLoggedIn } from '@/api/auth';
import { RouterLink } from 'vue-router';
</script>

<template>
    <RouterLink v-show="!isLoggedIn" to="/create-post" class="btn"> <!-- Will show only if logged in -->
        <span class="roboto-bold">CREATE<br>POST</br></span>
    </RouterLink>
</template>

<style scoped>
.btn {
  display: flex;
  height: 5em;
  width: fit-content;
  background: #007C8A;
  padding: 0 4em; /* 0 padding top-bottom, 4em on sides */
  border: none;
  border-radius: 10px;
  cursor: pointer;
  span { /* Styling for text*/
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
  }
}
</style>