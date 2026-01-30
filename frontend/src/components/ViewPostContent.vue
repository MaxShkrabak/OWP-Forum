<script setup>
const props = defineProps({
  content: {
    type: Object,
    required: true
  },
});

</script>


<template>
    <div class="post-box text-wrap">
        <div class="content-itself text-start text-wrap p-1 pt-2" v-html="content">
        </div>
    </div>
</template>


<style scoped>
/* Box */
.post-box {
    background-color: white;
    border: 3px solid black;
    border-radius: 5px;
    min-width: 250px;
    min-height: 300px;
    padding: .75rem;
}

/* Text box */
.content-itself {
  overflow-wrap: break-word;
  word-break: break-word; 
  white-space: pre-wrap; 
  max-width: auto;
}

</style>