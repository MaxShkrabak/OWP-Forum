<script setup>
import { ref } from 'vue';

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
.post-box {
    background-color: white;
    border: 3px solid black;
    border-radius: 5px;
    min-width: 250px;
    min-height: 300px;
}

.content-itself {
  color: #1a1a1b;
  text-decoration: none;
  font-weight: 700;
  font-size: 1rem;
  line-height: 1.2;
  white-space: nowrap; /* TODO: We can let it wrap if the title MAX LENGTH is reasonable */
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 0 1 auto;
  min-width: 0;
}

</style>