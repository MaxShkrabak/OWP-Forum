<script setup>
import { AuthStatus } from '@/api/auth';

const Auth = AuthStatus

</script>

<template>
    <header class="forum-header">
        <!-- Main forum title -->
        <h1 class = "title">Forum</h1>
        <!-- Description under title -->
        <p class = "subtitle">Learn, ask, and share with the OWP community.</p>
        <RouterLink v-if="!Auth" to="/register" class="btn">
                <span class="roboto-medium">Sign up to participate!</span>
        </RouterLink>
        
    </header>
</template>

<style>
/* Removes default browser margin and padding */
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
  height: 34px; /* slightly smaller button to match inputs */
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
    margin-top: .7em;              /* margin from top header */
    padding-left: 20%;             /* space between left edge and text */
    padding-top: 1em;              /* space between top edge and text */
    padding-bottom: 1em;           /* space between bottom edge and text */
    text-align: left;
    font-family: 'Lato';           
    background-color: #40625A;   /* header background color */
}

/* Main forum title */
.title {
    margin-top: 0em;       /* removes margin above title */
    margin-bottom: 0.1em;  /* small spacing between title and description */
    color: #FFFFFF;      /* white text color */
    font-size: 2em;
    font-weight: 400;      /* regular weight */
}

/* Description under title */
.subtitle {
    margin-top: 0em;       /* removes margin above description */
    font-size: 1.4em;       
    color:#FFFFFF;       /* white text color */
    font-weight: 200;      /* light weight */
}
</style>