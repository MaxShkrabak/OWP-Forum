<script setup>
import { computed, ref, watch } from "vue";
import { updateUserAvatar } from "@/api/auth";
import { userAvatar } from "@/stores/userStore";

// Import all images from the 'src/assets/img/user-pfps-premade/' folder
const allImages = import.meta.glob(
  "/src/assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)",
  { eager: true },
);

// Extract the image paths for use in the template
const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
});

const selectedAvatar = ref(userAvatar.value); // set icon from the store

// Watch for changes in store
watch(userAvatar, (newAvatar) => {
  selectedAvatar.value = newAvatar;
});

const selectAvatar = (imagePath) => {
  selectedAvatar.value = imagePath;
};

const saveAvatar = async () => {
  try {
    const fullPath = selectedAvatar.value;
    const filename = fullPath.split("/").pop();
    const result = await updateUserAvatar(filename);

    if (!result.ok) {
      alert("Could not save your icon, please try again later.");
      return;
    }

    localStorage.setItem("userAvatar", selectedAvatar.value);
    userAvatar.value = selectedAvatar.value; // update the store

    // Close modal using Bootstrap
    const modalElement = document.getElementById("pfpChange");
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
        modalElement.classList.remove("show");
        modalElement.setAttribute("aria-hidden", "true");
        modalElement.style.display = "none";
        document.body.classList.remove("modal-open");
        const backdrop = document.querySelector(".modal-backdrop");
        if (backdrop) backdrop.remove();
      }
    }
  } catch (e) {
    // Something went wrong
    const errorMsg = e.message || "An error occured.";
    alert(errorMsg);
  }
};
</script>

<template>
  <div
    class="modal fade"
    id="pfpChange"
    tabindex="-1"
    aria-labelledby="pfpChangeModal"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content profile-modal-content">
        <div class="modal-header profile-modal-header">
          <h1 class="modal-title fs-5" id="pfpChangeModal">
            Change your Profile Picture
          </h1>
          <button
            type="button"
            class="btn-close btn-close-white"
            data-bs-dismiss="modal"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body profile-modal-body">
          <div class="current-avatar-container">
            <p class="current-selection-label">Current selection</p>
            <img
              v-if="selectedAvatar"
              :src="selectedAvatar"
              class="current-avatar-preview"
              alt="Selected avatar"
            />
          </div>
          <div class="avatar-grid" role="radiogroup" aria-label="Choose avatar">
            <img
              v-for="(image, index) in images"
              :key="index"
              :src="image"
              class="pfp"
              :class="{ 'pfp-selected': selectedAvatar === image }"
              @click="selectAvatar(image)"
              role="radio"
              :aria-checked="selectedAvatar === image"
              alt="Avatar option"
            />
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
          <button type="button" class="save-btn" @click="saveAvatar">
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

.current-avatar-container {
  background: #f8fafc;
  border: 1px solid #dbe3ef;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1.5rem;
  text-align: center;
}

.current-selection-label {
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
}

.current-avatar-preview {
  width: 92px;
  height: 92px;
  border-radius: 50%;
  border: 3px solid #007a4c;
  object-fit: cover;
}

.avatar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
  gap: 0.9rem;
}

img.pfp {
  width: 100%;
  max-width: 96px;
  justify-self: center;
  aspect-ratio: 1 / 1;
  border-radius: 20%;
  cursor: pointer;
  object-fit: cover;
  transition: all 0.2s ease-in-out;
  border: 3px solid transparent;
}
img.pfp:hover {
  border: 3px solid #00a5b5;
  transform: scale(1.05);
}
img.pfp.pfp-selected {
  border: 4px solid #007a4c;
  box-shadow: 0 0 0 2px rgba(0, 122, 76, 0.25);
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
  }

  img.pfp {
    max-width: 82px;
  }

  .current-avatar-preview {
    width: 80px;
    height: 80px;
  }
}
</style>
