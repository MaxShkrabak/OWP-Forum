<template>
  <header class="header">
    <img id="csus-logo" title="CSUS Logo" src="/csus-logo-horizontal.png"></img>
    <div>
      <input type="Search...">
    <font-awesome-icon icon="fa-solid fa-search" />
 </div>
  </header>
</template>

<script setup>
const title = 'My Awesome Vue App'
</script>

<style scoped>
.header {
  background-color: red;
  color: black;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

#csus-logo {
  width: 180px;
  margin: 15px;
}
</style>
