<script setup>
import CSUSHeader from './components/CSUSHeader.vue'
import OWPHeader from './components/OWPHeader.vue';
import Footer from './components/Footer.vue';
// import Category from './components/Category.vue';
</script>

<template>
  <CSUSHeader />
  <OWPHeader />
  <router-view />
  <Footer />
  <!-- <Category/> -->
</template>

<style scoped>

/* Used to calculate the footer so it sticks to the bottom.
   WILL NEED TO FIX LATER after we sort out the headers. */
body {
  min-height: calc(100vh - 400px);
}
</style>
