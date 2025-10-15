<script setup>
import ForumHeader from '../components/ForumHeader.vue';
</script>

<template>
    <ForumHeader /> <!-- Display forum header -->
  <h1>HOME PAGE</h1>
    <router-link to="/login">login</router-link> | | |
    <router-link to="/register">register</router-link> | | |
    <router-link to="/profile">My Profile</router-link>
    <router-view></router-view>
</template>

<style scoped></style>