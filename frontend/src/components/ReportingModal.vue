<script setup>
import { ref } from 'vue'

const props = defineProps({
  isPost: {
    type: Boolean,
    required: true
  },
  useID: {
    type: Number,
    required: true
  }
})

const reportOpts = ["Inappropriate Behavior", "Misinformation", "Spam", "Other"];
const showThanks = ref(false);

function resetSelections() {
  document.getElementById("buttonOptions")?.reset();
}

function hideModalById(id) {
  const modalEl = document.getElementById(id);
  if (!modalEl) return;
  // Use Bootstrap 5 Modal API if available
  const BSModal = window.bootstrap?.Modal;
  if (BSModal) {
    const instance = BSModal.getInstance(modalEl) || new BSModal(modalEl);
    instance.hide();
  } else {
    // Fallback: click a dismiss button if the API isn't available
    modalEl.querySelector('[data-bs-dismiss="modal"]')?.click();
  }
}

function submitReport() {
  // TODO: send the selected option to your backend here

  hideModalById('reports');
  resetSelections();

  showThanks.value = true;
  // Auto-hide the message after a bit
  setTimeout(() => { showThanks.value = false; }, 3000);
}
</script>

<template>
  <div class="modal fade" id="reports" tabindex="-1" aria-labelledby="reportsModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-4" id="reportsModal">
            Submit a report for a
            <span class="fw-bold" v-if="props.isPost">Post by {{ props.useID }}</span>
            <span class="fw-bold" v-else>Comment by {{ props.useID }}</span>
          </h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form class="modal-body" id="buttonOptions">
          <div class="row">
            <div class="col-auto" v-for="(opt, idx) in reportOpts" :key="idx">
              <input
                type="radio"
                class="btn-check"
                name="reportOptions"
                :id="`report-opt-${idx}`"
                autocomplete="off"
              />
              <label class="btn btn-success btn-outline-warning badge fs-6" :for="`report-opt-${idx}`">
                {{ opt }}
              </label>
            </div>
          </div>
        </form>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="resetSelections">
            Cancel
          </button>
          <button type="button" class="btn btn-primary" @click="submitReport">
            Submit
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Success message -->
  <transition name="fade">
    <div
      v-if="showThanks"
      class="alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 shadow"
      role="alert"
      style="z-index: 2000;"
      aria-live="polite"
    >
      Thank you for your report
      <button type="button" class="btn-close" @click="showThanks = false" aria-label="Close"></button>
    </div>
  </transition>
</template>

<style scoped>
.row { padding: 1em; }
.modal-backdrop {
  background-color: #000;
  opacity: 0.2;
}
</style>
