<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <RouterLink to="/create-post" class="btn"> <!-- Will show only if logged in -->
        <div class="row text-center fs-5 text-nowrap text-white">
      <div class="col-12">
        <span class="roboto-bold">CREATE<br /> </span>
      </div>
      <div class="col-12">
        <span class="roboto-bold">POST<br /> </span>
      </div>
    </div>
    </RouterLink>
</template>

<style scoped>
.btn {
  background-color: #007C8A;
  height: 84px;
  width: fit-content;
}
</style>