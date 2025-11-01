<script setup>
import { computed } from 'vue';


// Import all images from the 'src/assets/img/user-pfps-premade/' folder
const allImages = import.meta.glob('../assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)', { eager: true });


// Extract the image paths for use in the template
const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
})
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
            <div class="row">
              <button class="img-btn" v-for="(image, index) in images" :key="index" >
                <img :src="image" class="pfp" alt="Image from folder"></button>
               
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Save changes</button>
          </div>
        </div>
      </div>
    </div>
</template>
<style scoped>
.img-btn {
  padding: 0;
  width: fit-content;
  border: none;
  background-color: transparent;
  border-radius: 20%;
  margin: 1em;
}
img.pfp{
    padding: 0;
    width: 128px;
    margin: 0;
    border-radius: 20%;
}
img.pfp:hover {
  border: 3px solid rgb(45, 149, 209);
}
.row {
    padding: 1em;
}
</style>