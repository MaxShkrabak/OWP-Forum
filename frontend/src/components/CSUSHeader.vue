<template>
  <header class="header">
    <h1>{{ title }}</h1>
    <nav>
      <a href="#">Home</a>
      <a href="#">About</a>
      <a href="#">Contact</a>
    </nav>
  </header>
</template>

<script setup>
const title = 'My Awesome Vue App'
</script>

<style scoped>
.header {
  background-color: #42b983;
  color: white;
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

nav a {
  color: white;
  text-decoration: none;
  margin-left: 1rem;
}

nav a:hover {
  text-decoration: underline;
}
</style>
