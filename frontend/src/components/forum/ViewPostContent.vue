<script setup>
const props = defineProps({
  content: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <div class="content-body" v-html="content"></div>
</template>

<style scoped>
.content-body {
  min-height: 150px;
  color: #1a2e22;
  font-size: 1rem;
  line-height: 1.8;
}
.content-body :deep(> *:first-child) {
  margin-top: 0;
}
.content-body :deep(*) {
  white-space: pre-wrap !important;
  word-break: break-word !important;
  overflow-wrap: anywhere !important;
  max-width: 100%;
}
</style>
