<script setup>
import CSUSHeader from './components/CSUSHeader.vue'
import Footer from './components/Footer.vue';
</script>

<template>
  <CSUSHeader />

  <body>
    <h1>OWP FORUM</h1>
    <router-view></router-view>
  </body>
  
  <Footer />
</template>

<style scoped>

/* Used to calculate the footer so it sticks to the bottom.
   WILL NEED TO FIX LATER after we sort out the headers. */
body {
  min-height: calc(100vh - 450px);
}
</style>
