<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { fetchNotifications, markNotificationsRead } from '@/api/users';
import { isLoggedIn } from '@/stores/userStore';
import { isNotificationEnabled, getNotificationPreferences } from '@/utils/notificationPreferences';

const router = useRouter();

const notifications = ref([]);
const seenIds = new Set();
const shownTimestamps = ref([]);
let isFetching = false;

const AUTO_DISMISS_MS = 5000;
const MAX_VISIBLE = 3;
const MAX_PER_MINUTE = 3;
const RATE_WINDOW_MS = 60_000;
const FETCH_COOLDOWN_MS = 30_000;

let lastFetchedAt = 0;

function getMessage(item) {
  if (item.type === 'postLike') {
    return `Your post "${item.title}" received a like.`;
  }

  if (item.type === 'postReply') {
    return `Your post "${item.title}" received a reply.`;
  }

  return 'You have a new notification.';
}

function pruneRateWindow() {
  const now = Date.now();
  shownTimestamps.value = shownTimestamps.value.filter(
    (ts) => now - ts < RATE_WINDOW_MS
  );
}

function canShowAnotherNotification() {
  pruneRateWindow();
  return shownTimestamps.value.length < MAX_PER_MINUTE;
}

function rememberShownNow() {
  shownTimestamps.value.push(Date.now());
}

async function markRead(notificationId) {
  try {
    await markNotificationsRead([notificationId]);
  } catch (e) {
    console.error('Failed to mark notification as read', e);
  }
}

async function markManyRead(notificationIds) {
  if (!notificationIds.length) return;

  try {
    await markNotificationsRead(notificationIds);
  } catch (e) {
    console.error('Failed to mark notifications as read', e);
  }
}

async function removeNotification(notificationId, shouldMarkRead = true) {
  notifications.value = notifications.value.filter(
    (n) => n.notificationId !== notificationId
  );

  if (shouldMarkRead) {
    await markRead(notificationId);
  }
}

async function openNotification(notificationId, postId) {
  await removeNotification(notificationId, true);

  if (postId) {
    router.push(`/posts/${postId}`);
  }
}

async function closeNotification(notificationId) {
  await removeNotification(notificationId, true);
}

function scheduleAutoDismiss(notificationId) {
  window.setTimeout(() => {
    const exists = notifications.value.some((n) => n.notificationId === notificationId);
    if (exists) {
      removeNotification(notificationId, true);
    }
  }, AUTO_DISMISS_MS);
}

function tryDisplayNotification(item) {
  if (!canShowAnotherNotification()) {
    return false;
  }

  if (notifications.value.length >= MAX_VISIBLE) {
    return false;
  }

  notifications.value = [item, ...notifications.value];
  rememberShownNow();
  scheduleAutoDismiss(item.notificationId);
  return true;
}

async function loadNotifications() {
  if (!isLoggedIn.value || isFetching) return;
  if (!getNotificationPreferences().pushNotifications) return;
  if (Date.now() - lastFetchedAt < FETCH_COOLDOWN_MS) return;

  isFetching = true;
  lastFetchedAt = Date.now();

  try {
    const result = await fetchNotifications();
    if (!result?.ok || !Array.isArray(result.items)) return;

    const discardIds = [];

    for (const item of result.items) {
      if (!isNotificationEnabled(item.type)) {
        discardIds.push(item.notificationId);
        continue;
      }

      if (seenIds.has(item.notificationId)) {
        continue;
      }

      seenIds.add(item.notificationId);

      const displayed = tryDisplayNotification(item);

      if (!displayed) {
        discardIds.push(item.notificationId);
      }
    }

    if (discardIds.length > 0) {
      await markManyRead(discardIds);
    }
  } catch (e) {
    console.error('Failed to fetch notifications', e);
  } finally {
    isFetching = false;
  }
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible') {
    loadNotifications();
  }
}

onMounted(() => {
  loadNotifications();
  document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange);
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

          <button
            type="button"
            class="notification-close-x"
            @click="closeNotification(item.notificationId)"
            aria-label="Close notification"
          >
            ×
          </button>

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
  position: relative;
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
  padding-right: 24px;
}

.notification-close-x {
  position: absolute;
  top: 10px;
  right: 10px;
  border: none;
  background: transparent;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  color: #6c757d;
}

.notification-close-x:hover {
  color: #343a40;
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