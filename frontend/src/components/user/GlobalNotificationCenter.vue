<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { fetchNotifications, markNotificationsRead } from '@/api/users';
import { isLoggedIn } from '@/stores/userStore';
import { isNotificationEnabled } from '@/utils/notificationPreferences';

const router = useRouter();
const notifications = ref([]);
const seenIds = new Set();
let intervalId = null;
let isFetching = false;

function getMessage(item) {
  if (item.type === 'postLike') {
    return `Your post "${item.title}" received a like.`;
  }

  if (item.type === 'postReply') {
    return `Your post "${item.title}" received a reply.`;
  }

  return 'You have a new notification.';
}

async function markReadAndRemove(notificationId) {
  notifications.value = notifications.value.filter(n => n.notificationId !== notificationId);

  try {
    await markNotificationsRead([notificationId]);
  } catch (e) {
    console.error('Failed to mark notification as read', e);
  }
}

async function openNotification(notificationId, postId) {
  await markReadAndRemove(notificationId);

  if (postId) {
    router.push(`/posts/${postId}`);
  }
}

async function closeNotification(notificationId) {
  await markReadAndRemove(notificationId);
}

async function pollNotifications() {
  if (!isLoggedIn.value || isFetching) return;

  isFetching = true;

  try {
    const result = await fetchNotifications();
    if (!result?.ok || !Array.isArray(result.items)) return;

    const fresh = [];

    for (const item of result.items) {
      if (!isNotificationEnabled(item.type)) continue;
      if (seenIds.has(item.notificationId)) continue;

      seenIds.add(item.notificationId);
      fresh.push(item);
    }

    if (fresh.length > 0) {
      notifications.value = [...fresh, ...notifications.value].slice(0, 5);
    }
  } catch (e) {
    console.error('Failed to fetch notifications', e);
  } finally {
    isFetching = false;
  }
}

onMounted(() => {
  pollNotifications();
  intervalId = window.setInterval(pollNotifications, 8000);
});

onBeforeUnmount(() => {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
});
</script>

<template>
  <div class="global-notification-center">
    <transition-group name="popup">
      <div
        v-for="item in notifications"
        :key="item.notificationId"
        class="notification-popup"
      >
        <div class="notification-content">
          <div class="notification-title">
            {{ item.type === 'postLike' ? 'Post liked' : 'Post replied to' }}
          </div>
          <div class="notification-message">
            {{ getMessage(item) }}
          </div>
        </div>

        <div class="notification-actions">
          <button
            type="button"
            class="notification-btn notification-btn-secondary"
            @click="closeNotification(item.notificationId)"
          >
            Close
          </button>

          <button
            type="button"
            class="notification-btn notification-btn-primary"
            @click="openNotification(item.notificationId, item.postId)"
          >
            Open
          </button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.global-notification-center {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 12px;
  pointer-events: none;
}

.notification-popup {
  width: 320px;
  max-width: calc(100vw - 32px);
  background: #ffffff;
  border: 1px solid #dfe3e8;
  border-radius: 12px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
  padding: 14px 16px;
  pointer-events: auto;
}

.notification-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: #2f3a2f;
  margin-bottom: 6px;
}

.notification-message {
  font-size: 0.9rem;
  color: #495057;
  line-height: 1.4;
}

.notification-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
}

.notification-btn {
  border: none;
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 0.875rem;
  cursor: pointer;
}

.notification-btn-primary {
  background-color: #48773C;
  color: white;
}

.notification-btn-primary:hover {
  background-color: #3a6130;
}

.notification-btn-secondary {
  background-color: #e9ecef;
  color: #495057;
}

.notification-btn-secondary:hover {
  background-color: #dfe3e8;
}

.popup-enter-active,
.popup-leave-active {
  transition: all 0.22s ease;
}

.popup-enter-from,
.popup-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>