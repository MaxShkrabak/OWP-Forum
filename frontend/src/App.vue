<script setup>
import CSUSHeader from './components/layout/CSUSHeader.vue'
import OWPHeader from './components/layout/OWPHeader.vue';
import Footer from './components/layout/Footer.vue';
import { isLoggedIn, isBanned, banType, bannedUntil } from '@/stores/userStore';
import { formatBannedUntilDateTime } from '@/utils/banDate';
// import Category from './components/Category.vue';
</script>

<template>
  <CSUSHeader />
  <OWPHeader />
  <div v-if="isLoggedIn && isBanned" class="banned-banner" role="alert">
    <span class="banned-icon" aria-hidden="true">⚠</span>
    <template v-if="banType === 'temporary' && bannedUntil">
      <strong>Your account is temporarily banned until {{ formatBannedUntilDateTime(bannedUntil, { dateStyle: 'long', timeStyle: 'short' }) }}.</strong>
      You cannot create posts or comments until then. If you believe this is an error, please contact an administrator.
    </template>
    <template v-else>
      <strong>Your account is permanently banned.</strong>
      You cannot create posts or comments. If you believe this is an error, please contact an administrator.
    </template>
  </div>
  <router-view />
  <Footer />
  <!-- <Category/> -->
</template>

<style scoped>
template {
  background-color: #DEE2E6;
}
</style>

<style>
.banned-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 14px 20px;
  background: linear-gradient(90deg, #7f1d1d 0%, #991b1b 50%, #b91c1c 100%);
  color: #fff;
  font-size: 1rem;
  font-weight: 500;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  border-bottom: 3px solid #fef2f2;
}
.banned-banner .banned-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}
.banned-banner strong {
  font-weight: 700;
}
</style>
