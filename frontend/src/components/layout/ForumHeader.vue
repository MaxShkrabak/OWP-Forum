<script setup>
import { isLoggedIn } from "@/stores/userStore";
</script>

<template>
    <header class="forum-header">
        <!-- Main forum title -->
        <h1 class = "title">Forum</h1>
        <!-- Description under title -->
        <p class = "subtitle">Learn, ask, and share with the OWP community.</p>
        <!-- Sign up button only shows if user is not signed in -->
        <RouterLink v-if="!isLoggedIn" to="/register" class="btn">
                <span class="roboto-medium">Sign up to participate!</span>
        </RouterLink>
        
    </header>
</template>

<style>
html, body {
    margin: 0;
    padding: 0;
}
</style>

<style scoped>
RouterLink {
    text-decoration: none;
}
.btn {
    display: flex;
  margin-top: 18px;
  height: 34px;
  width: fit-content;
  background: #48773C;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-weight: 600;
  padding: 0 16px;
  cursor: pointer;
  span {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
  }
}

/* Container for forum header */
.forum-header {
    margin-top: .7em;
    padding-left: 10%;
    padding-top: 1rem;
    padding-bottom: 1rem;
    text-align: left;
    font-family: 'Lato';           
    background-color: #40625A;
}

/* Main forum title */
.title {
    margin-top: 0rem;
    margin-bottom: 0.1rem;
    color: #FFFFFF;
    font-size: 2rem;
    font-weight: 400;
}

.subtitle {
    margin-top: 0rem;
    font-size: 1.4em;       
    color:#FFFFFF;
    font-weight: 200;
}
</style>