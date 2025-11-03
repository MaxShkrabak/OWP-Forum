<script setup>
import { computed, ref, onMounted } from 'vue';


// Import all images from the 'src/assets/img/user-pfps-premade/' folder
const allImages = import.meta.glob('../assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)', { eager: true });


// Extract the image paths for use in the template
const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
});

const selectedAvatar = ref('');

// Load current avatar
onMounted(() => {
  const savedAvatar = localStorage.getItem('userAvatar');
  if (savedAvatar) {
    selectedAvatar.value = savedAvatar;
  } else {
    // Default to pfp-4.png (index 3) if available
    selectedAvatar.value = images.value[3] || images.value[0] || '';
  }
});

const selectAvatar = (imagePath) => {
  selectedAvatar.value = imagePath;
};

const saveAvatar = () => {
  if (selectedAvatar.value) {
    localStorage.setItem('userAvatar', selectedAvatar.value);
    
    // Close modal using Bootstrap
    const modalElement = document.getElementById('pfpChange');
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
    
    // Trigger event to update avatar display in UserProfile
    window.dispatchEvent(new CustomEvent('settingsUpdated'));
  }
};
</script>
<template>
    <div class="modal fade" id="pfpChange" tabindex="-1" aria-labelledby="pfpChangeModal" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h1 class="modal-title fs-5" id="pfpChangeModal">Change your Profile Picture</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="current-avatar-container mb-3">
              <p class="text-muted small mb-2">Current selection:</p>
              <img v-if="selectedAvatar" :src="selectedAvatar" class="current-avatar-preview" alt="Selected avatar">
            </div>
            <div class="row">
                <img 
                  v-for="(image, index) in images" 
                  :key="index" 
                  :src="image" 
                  class="pfp"
                  :class="{ 'pfp-selected': selectedAvatar === image }"
                  @click="selectAvatar(image)"
                  alt="Avatar option"
                >
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" @click="saveAvatar">Save changes</button>
          </div>
        </div>
      </div>
    </div>
</template>
<style scoped> 
.current-avatar-container {
  text-align: center;
}

.current-avatar-preview {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  border: 3px solid #48773C;
  object-fit: cover;
}

img.pfp{
    padding: 0;
    width: 128px;
    margin: 1em;
    border-radius: 20%;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    border: 3px solid transparent;
}
img.pfp:hover {
  border: 3px solid rgb(45, 149, 209);
  transform: scale(1.05);
}
img.pfp.pfp-selected {
  border: 4px solid #48773C;
  box-shadow: 0 0 0 2px rgba(72, 119, 60, 0.3);
}
.row {
    padding: 1em;
}

.btn-primary {
  background-color: #48773C;
  border-color: #48773C;
}

.btn-primary:hover {
  background-color: #3a6130;
  border-color: #3a6130;
}
</style>