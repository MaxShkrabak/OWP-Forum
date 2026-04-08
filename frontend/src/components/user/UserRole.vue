<script setup>
import { computed } from "vue";

const props = defineProps({
  role: {
    type: String,
    default: "User",
  },
});

const roleClass = computed(() => {
  return (props.role || "user").toLowerCase();
});
</script>

<template>
  <span :class="['role-pill', roleClass]">
    {{ role }}
  </span>
</template>

<style scoped>
.role-pill {
  display: inline-block;
  font-size: 0.6rem;
  font-weight: 800;
  padding: 2px 10px;
  border-radius: 50px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border: 1px solid transparent;
}

.admin {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fecaca;
  border-width: 2px;
}

.moderator {
  background: #fef3c7;
  color: #d97706;
  border-color: #fde68a;
  border-width: 2px;
}

.student {
  background: #e0f2fe;
  color: #0284c7;
  border-color: #bae6fd;
  border-width: 2px;
}

.user,
.guest {
  background: #e8f5e9;
  color: #2e6c44;
  border-color: #c8e6c9;
  border-width: 2px;
}
</style>
