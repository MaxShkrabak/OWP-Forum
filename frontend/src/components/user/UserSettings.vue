<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { updateUserAvatar } from "@/api/auth";
import { getNotificationSettings, saveNotificationSettings } from "@/api/users";
import { userAvatar } from "@/stores/userStore";
import {
  getNotificationPreferences,
  saveNotificationPreferences as saveNotificationPreferencesLocal,
} from "@/utils/notificationPreferences";

const allImages = import.meta.glob(
  "/src/assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)",
  { eager: true },
);

const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
});

const selectedAvatar = ref("");
const notificationPrefs = ref({
  emailNotifications: true,
  pushNotifications: true,
  postReplies: true,
  postLikes: true,
});

// Load saved settings from localStorage + server
const loadSettings = async () => {
  const savedAvatar =
    localStorage.getItem("userAvatar") || images.value[0] || "";

  selectedAvatar.value = savedAvatar;

  notificationPrefs.value = {
    ...notificationPrefs.value,
    ...getNotificationPreferences(),
  };

  try {
    const result = await getNotificationSettings();
    if (result.ok && result.settings) {
      notificationPrefs.value = {
        ...notificationPrefs.value,
        emailNotifications: !!result.settings.emailNotifications,
      };
      saveNotificationPreferencesLocal(notificationPrefs.value);
    }
  } catch (e) {
    console.error("Failed to load notification settings from server", e);
  }
};

// Save settings to localStorage and database
const saveSettings = async () => {
  try {
    const fullPath = selectedAvatar.value;
    const filename = fullPath.split("/").pop();
    const avatarResult = await updateUserAvatar(filename);

    if (!avatarResult.ok) {
      alert("Could not save your icon, please try again later.");
      return;
    }

    const notificationResult = await saveNotificationSettings({
      emailNotifications: notificationPrefs.value.emailNotifications,
      pushNotifications: notificationPrefs.value.pushNotifications,
      postLikes: notificationPrefs.value.postLikes,
      postReplies: notificationPrefs.value.postReplies,
    });

    if (!notificationResult.ok) {
      alert(
        "Could not save your notification preferences, please try again later.",
      );
      return;
    }

    localStorage.setItem("userAvatar", selectedAvatar.value);
    saveNotificationPreferencesLocal(notificationPrefs.value);
    userAvatar.value = selectedAvatar.value;

    const modalElement = document.getElementById("userSettingsModal");
    if (modalElement) {
      let modal = null;
      if (window.bootstrap && window.bootstrap.Modal) {
        modal = window.bootstrap.Modal.getInstance(modalElement);
      } else if (window.Bootstrap && window.Bootstrap.Modal) {
        modal = window.Bootstrap.Modal.getInstance(modalElement);
      }

      if (modal) {
        if (document.activeElement && modalElement.contains(document.activeElement)) {
          document.activeElement.blur();
        }
        modal.hide();
      } else {
        modalElement.classList.remove("show");
        modalElement.setAttribute("aria-hidden", "true");
        modalElement.style.display = "none";
        document.body.classList.remove("modal-open");
        const backdrop = document.querySelector(".modal-backdrop");
        if (backdrop) backdrop.remove();
      }
    }
  } catch (e) {
    const errorMsg = e.message || "An error occured.";
    alert(errorMsg);
  }
};

watch(userAvatar, (newAvatar) => {
  selectedAvatar.value = newAvatar;
});

onMounted(() => {
  loadSettings();
});

const selectAvatar = (imagePath) => {
  selectedAvatar.value = imagePath;
};
</script>

<template>
  <div
    class="modal fade"
    id="userSettingsModal"
    tabindex="-1"
    aria-labelledby="userSettingsModalLabel"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content profile-modal-content">
        <div class="modal-header profile-modal-header">
          <h1 class="modal-title fs-5" id="userSettingsModalLabel">
            User Settings
          </h1>
          <button
            type="button"
            class="btn-close btn-close-white"
            data-bs-dismiss="modal"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body profile-modal-body">
          <div class="settings-section">
            <h5 class="settings-section-title">Profile Picture</h5>
            <div class="avatar-selection-container">
              <p class="settings-label mb-2">Choose avatar</p>
              <div class="avatar-grid">
                <img
                  v-for="(image, index) in images"
                  :key="index"
                  :src="image"
                  class="pfp-selector"
                  :class="{ 'pfp-selected': selectedAvatar === image }"
                  @click="selectAvatar(image)"
                  alt="Avatar option"
                />
              </div>
            </div>
          </div>

          <hr class="settings-divider" />

          <div class="settings-section">
            <h5 class="settings-section-title">Notification Preferences</h5>
            <div class="notification-options">
              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Comment Email Notifications</label>
                  <span class="notification-description"
                    >Receive notifications via email for comments on your posts</span
                  >
                </div>
                <div class="form-check form-switch">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="emailNotifications"
                    v-model="notificationPrefs.emailNotifications"
                  />
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Browser Push Notifications</label>
                  <span class="notification-description"
                    >Receive browser popup notifications in the app</span
                  >
                </div>
                <div class="form-check form-switch">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="pushNotifications"
                    v-model="notificationPrefs.pushNotifications"
                  />
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Post Comments</label>
                  <span class="notification-description"
                    >Browser popup when someone comments on your posts</span
                  >
                </div>
                <div class="form-check form-switch">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="postReplies"
                    v-model="notificationPrefs.postReplies"
                    :disabled="!notificationPrefs.pushNotifications"
                  />
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Post Likes</label>
                  <span class="notification-description"
                    >Browser popup when someone likes your posts</span
                  >
                </div>
                <div class="form-check form-switch">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="postLikes"
                    v-model="notificationPrefs.postLikes"
                    :disabled="!notificationPrefs.pushNotifications"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer profile-modal-footer">
          <button
            type="button"
            class="cancel-btn"
            data-bs-dismiss="modal"
          >
            Cancel
          </button>
          <button type="button" class="save-btn" @click="saveSettings">
            Save Changes
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-modal-content {
  border: 0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
}

.profile-modal-header {
  background: #004750;
  color: #ffffff;
  border-bottom: none;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.profile-modal-header .modal-title {
  margin: 0 !important;
  line-height: 1.2;
  color: #ffffff;
}

.profile-modal-body {
  padding: 1.25rem;
  background: #ffffff;
}

.profile-modal-footer {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  padding: 1rem 1.25rem;
}

.settings-section {
  margin-bottom: 1.5rem;
}

.settings-section:last-child {
  margin-bottom: 0;
}

.settings-section-title {
  font-weight: 700;
  margin-bottom: 1rem;
  color: #0f172a;
}

.settings-divider {
  margin: 1.5rem 0;
  opacity: 0.15;
}

.settings-label {
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.avatar-selection-container {
  margin-top: 0.25rem;
}

.avatar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(78px, 1fr));
  gap: 0.7rem;
}

.pfp-selector {
  width: 100%;
  max-width: 82px;
  justify-self: center;
  aspect-ratio: 1 / 1;
  border-radius: 18%;
  cursor: pointer;
  transition: all 0.15s ease-in-out;
  object-fit: cover;
  border: 3px solid transparent;
}

.pfp-selector:hover {
  border-color: #00a5b5;
  transform: translateY(-1px);
}

.pfp-selector.pfp-selected {
  border-color: #007a4c;
  box-shadow: 0 0 0 1px rgba(0, 122, 76, 0.22);
}

.notification-options {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.notification-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  transition: background-color 0.2s, border-color 0.2s;
}

.notification-item:hover {
  background-color: #f1f5f9;
  border-color: #cbd5e1;
}

.notification-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.notification-label {
  font-weight: 600;
  margin: 0;
  color: #0f172a;
  cursor: pointer;
}

.notification-description {
  font-size: 0.875rem;
  color: #64748b;
}

.form-check-input {
  width: 3rem;
  height: 1.5rem;
  cursor: pointer;
}

.form-check-input:checked {
  background-color: #007a4c;
  border-color: #007a4c;
}

.cancel-btn,
.save-btn {
  padding: 0.75em 1.6em;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: inherit;
  font-size: 0.95rem;
  border: 2px solid transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  outline: none;
}

.save-btn {
  background: #007a4c;
  color: white;
  border: none;
}

.save-btn:disabled {
  background: #94a3b8;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.save-btn:hover:not(:disabled) {
  background: #008f57;
  box-shadow: 0 4px 12px rgba(0, 122, 76, 0.25);
  transform: translateY(-1px);
}

.cancel-btn {
  background: white;
  color: #475569;
  border: 2px solid #cbd5e1;
}

.cancel-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #94a3b8;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

@media (max-width: 768px) {
  .avatar-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.6rem;
  }

  .pfp-selector {
    max-width: 72px;
  }
}
</style>
