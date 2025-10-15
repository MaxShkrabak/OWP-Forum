<script setup>
</script>

<template>
    <header class="forum-header">
        <!-- Main forum title -->
        <h1 class = "title">Forum</h1>
        <!-- Description under title -->
        <p class = "subtitle">Learn, ask, and share with the OWP community.</p>
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
/* Container for forum header */
.forum-header {
    padding-left: 1em;             /* space between left edge and text */
    padding-top: 1em;              /* space between top edge and text */
    padding-bottom: 1em;           /* space between bottom edge and text */
    text-align: left;
    font-family: 'Lato';           
    background-color: #40625A;   /* header background color */
}

/* Main forum title */
.title {
    margin-top: 0em;       /* removes margin above title */
    margin-bottom: 0.2em;  /* small spacing between title and description */
    color: #FFFFFF;      /* white text color */
    font-size: 2em;
    font-weight: 400;      /* regular weight */
}

/* Description under title */
.subtitle {
    margin-top: 0em;       /* removes margin above description */
    font-size: 1em;       
    color:#FFFFFF;       /* white text color */
    font-weight: 200;      /* light weight */
}
</style>