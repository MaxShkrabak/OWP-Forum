<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { updateUserAvatar } from '@/api/auth';

// Import all images from the 'src/assets/images' folder
const allImages = import.meta.glob('../assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)', { eager: true });

// Extract the image paths for use in the template
const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
});

// Load saved settings from localStorage
const loadSettings = () => {
  const savedAvatar = localStorage.getItem('userAvatar') || images.value[0] || ''; // Default to pfp-0.png (index 0)
  const savedNotifications = localStorage.getItem('notificationPreferences');
  
  selectedAvatar.value = savedAvatar;
  
  if (savedNotifications) {
    try {
      const prefs = JSON.parse(savedNotifications);
      notificationPrefs.value = { ...notificationPrefs.value, ...prefs };
    } catch (e) {
      console.error('Failed to parse notification preferences', e);
    }
  }
};

// Save settings to localStorage and database
const saveSettings = async () => {
  try {
    const result = await updateUserAvatar(selectedAvatar.value);

    if (!result.ok) {
      alert('Could not save your icon, please try again later.');
      return;
    }

    // Store icon in localstorage
    localStorage.setItem('userAvatar', selectedAvatar.value);
    localStorage.setItem('notificationPreferences', JSON.stringify(notificationPrefs.value));
    
    // Close modal using Bootstrap
    const modalElement = document.getElementById('userSettingsModal');
    if (modalElement) {
      // Try different ways to access Bootstrap Modal
      let modal = null;
      if (window.bootstrap && window.bootstrap.Modal) {
        modal = window.bootstrap.Modal.getInstance(modalElement);
      } else if (window.Bootstrap && window.Bootstrap.Modal) {
        modal = window.Bootstrap.Modal.getInstance(modalElement);
      }
      
      if (modal) {
        modal.hide();
      } else {
        // Fallback: use jQuery/bootstrap event or just remove show class
        modalElement.classList.remove('show');
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.style.display = 'none';
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
      }
    }
    
    // Trigger a custom event to notify UserProfile to update
    window.dispatchEvent(new CustomEvent('settingsUpdated'));
  } catch (e) {
    // Something went wrong
    const errorMsg = e.message || 'An error occured.';
    alert(errorMsg);
  } 
};

const selectedAvatar = ref('');
const notificationPrefs = ref({
  emailNotifications: true,
  pushNotifications: true,
  postReplies: true,
  postLikes: true,
  newFollowers: true,
  mentions: true
});

onMounted(() => {
  loadSettings();
  window.addEventListener('settingsUpdated', loadSettings);
});

onUnmounted(() => {
  window.removeEventListener('settingsUpdated', loadSettings);
});

const selectAvatar = (imagePath) => {
  selectedAvatar.value = imagePath;
};
</script>

<template>
  <div class="modal fade" id="userSettingsModal" tabindex="-1" aria-labelledby="userSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="userSettingsModalLabel">User Settings</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Avatar Selection Section -->
          <div class="settings-section">
            <h5 class="settings-section-title">Profile Picture</h5>
            <div class="current-avatar-container">
              <p class="text-muted small mb-2">Current avatar:</p>
              <img v-if="selectedAvatar" :src="selectedAvatar" class="current-avatar-preview" alt="Current avatar">
            </div>
            <div class="avatar-selection-container">
              <p class="text-muted small mb-3">Choose a new avatar:</p>
              <div class="avatar-grid">
                <img 
                  v-for="(image, index) in images" 
                  :key="index" 
                  :src="image" 
                  class="pfp-selector"
                  :class="{ 'pfp-selected': selectedAvatar === image }"
                  @click="selectAvatar(image)"
                  alt="Avatar option"
                >
              </div>
            </div>
          </div>

          <hr class="settings-divider">

          <!-- Notification Preferences Section -->
          <div class="settings-section">
            <h5 class="settings-section-title">Notification Preferences</h5>
            <div class="notification-options">
              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Email Notifications</label>
                  <span class="notification-description">Receive notifications via email</span>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="emailNotifications"
                    v-model="notificationPrefs.emailNotifications"
                  >
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Push Notifications</label>
                  <span class="notification-description">Receive browser push notifications</span>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="pushNotifications"
                    v-model="notificationPrefs.pushNotifications"
                  >
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Post Replies</label>
                  <span class="notification-description">Notify when someone replies to your posts</span>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="postReplies"
                    v-model="notificationPrefs.postReplies"
                  >
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Post Likes</label>
                  <span class="notification-description">Notify when someone likes your posts</span>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="postLikes"
                    v-model="notificationPrefs.postLikes"
                  >
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">New Followers</label>
                  <span class="notification-description">Notify when someone follows you</span>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="newFollowers"
                    v-model="notificationPrefs.newFollowers"
                  >
                </div>
              </div>

              <div class="notification-item">
                <div class="notification-info">
                  <label class="notification-label">Mentions</label>
                  <span class="notification-description">Notify when someone mentions you</span>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="mentions"
                    v-model="notificationPrefs.mentions"
                  >
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="saveSettings">Save Changes</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-section {
  margin-bottom: 1.5rem;
}

.settings-section:last-child {
  margin-bottom: 0;
}

.settings-section-title {
  font-weight: 600;
  margin-bottom: 1rem;
  color: #333;
}

.settings-divider {
  margin: 1.5rem 0;
  opacity: 0.2;
}

.current-avatar-container {
  margin-bottom: 1.5rem;
}

.current-avatar-preview {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  border: 3px solid #48773C;
  object-fit: cover;
}

.avatar-selection-container {
  margin-top: 1rem;
}

.avatar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 1rem;
  padding: 0.5rem 0;
}

.pfp-selector {
  width: 100px;
  height: 100px;
  border-radius: 20%;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  object-fit: cover;
  border: 3px solid transparent;
}

.pfp-selector:hover {
  border: 3px solid rgb(45, 149, 209);
  transform: scale(1.05);
}

.pfp-selector.pfp-selected {
  border: 4px solid #48773C;
  box-shadow: 0 0 0 2px rgba(72, 119, 60, 0.3);
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
  background-color: #f8f9fa;
  border-radius: 8px;
  transition: background-color 0.2s;
}

.notification-item:hover {
  background-color: #e9ecef;
}

.notification-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.notification-label {
  font-weight: 500;
  margin: 0;
  color: #333;
  cursor: pointer;
}

.notification-description {
  font-size: 0.875rem;
  color: #6c757d;
}

.form-check-input {
  width: 3rem;
  height: 1.5rem;
  cursor: pointer;
}

.form-check-input:checked {
  background-color: #48773C;
  border-color: #48773C;
}

.modal-footer .btn-primary {
  background-color: #48773C;
  border-color: #48773C;
}

.modal-footer .btn-primary:hover {
  background-color: #3a6130;
  border-color: #3a6130;
}

@media (max-width: 768px) {
  .avatar-grid {
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  }
  
  .pfp-selector {
    width: 80px;
    height: 80px;
  }
  
  .current-avatar-preview {
    width: 80px;
    height: 80px;
  }
}
</style>