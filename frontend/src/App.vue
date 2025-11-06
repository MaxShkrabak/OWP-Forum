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
template {
  background-color: #DEE2E6;
}
</style>
