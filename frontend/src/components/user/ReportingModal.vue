<script setup>
import { ref, watch } from "vue";
import {
  getReportTags,
  submitReport as apiSubmitReport,
} from "@/api/reports.js";

const props = defineProps({
  isOpen: Boolean,
  targetId: [String, Number],
  targetTitle: String,
  type: { type: String, default: "content" },
});

const emit = defineEmits(["close", "submit"]);

const reportTags = ref([]);
const selectedTagID = ref(null);
const isLoading = ref(false);
const isSubmitted = ref(false);
const errorMessage = ref("");
const isShaking = ref(false);

const loadReportTags = async () => {
  if (reportTags.value.length > 0) return;
  isLoading.value = true;
  try {
    const tags = await getReportTags();
    reportTags.value = tags;
  } catch (error) {
    console.error("Failed to load report tags:", error);
  } finally {
    isLoading.value = false;
  }
};

const toggleTagSelection = (id) => {
  selectedTagID.value = selectedTagID.value === id ? null : id;
};

const closeModal = () => {
  selectedTagID.value = null;
  isSubmitted.value = false;
  errorMessage.value = "";
  emit("close");
};

const submitReport = async () => {
  if (!selectedTagID.value) return;

  // Shake if already reported, then stop
  if (errorMessage.value.includes("already reported")) {
    isShaking.value = true;
    setTimeout(() => {
      isShaking.value = false;
    }, 300);
    return;
  }

  errorMessage.value = "";
  isLoading.value = true;

  const payload = {
    tagID: selectedTagID.value,
    id: props.targetId,
    type: props.type,
  };

  const result = await apiSubmitReport(payload);
  if (result.ok) {
    isSubmitted.value = true;
  } else {
    errorMessage.value = result.error;
  }
  isLoading.value = false;
};

watch(
  () => props.isOpen,
  (isVisible) => {
    if (isVisible) {
      isSubmitted.value = false;
      errorMessage.value = "";
      loadReportTags();
    }
  },
);
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="isOpen"
        class="custom-modal-overlay d-flex justify-content-center align-items-center"
        @click.self="closeModal"
      >
        <Transition name="modal-pop">
          <div v-if="isOpen" class="report-body">
            <!-- Report header -->
            <header
              class="report-header d-flex align-items-center justify-content-between p-1"
              v-if="!isSubmitted"
            >
              <div
                class="header-content d-flex gap-3 align-items-center m-3 flex-grow-1"
              >
                <div
                  class="header-icon rounded-2 d-none d-sm-flex align-items-center justify-content-center flex-shrink-0"
                >
                  <i class="pi pi-flag-fill fs-5"></i>
                </div>

                <div
                  class="d-flex flex-column flex-grow-1"
                  style="min-width: 0"
                >
                  <h3 class="report-title m-0">Report {{ type }}</h3>
                  <p
                    v-if="targetTitle"
                    class="target-subtitle text-truncate m-0 pt-1"
                  >
                    "{{ targetTitle }}"
                  </p>
                </div>
              </div>

              <button
                class="icon-close d-flex align-items-center justify-content-center me-3 flex-shrink-0"
                @click="closeModal"
              >
                <i class="pi pi-times"></i>
              </button>
            </header>

            <div class="report-content">
              <div
                v-if="isLoading || isSubmitted"
                class="d-flex flex-column align-items-center text-center p-3 success-fade"
              >
                <!-- Fetching report options-->
                <template v-if="isLoading && reportTags.length === 0">
                  <div class="spinner-wrapper">
                    <i class="pi pi-spin pi-spinner spinner-icon fs-2"></i>
                  </div>
                  <p class="state-text mt-2">Loading options...</p>
                </template>

                <!-- Submitting report load -->
                <template v-else-if="isLoading">
                  <div class="custom-loader mb-3"></div>
                  <h4 class="fw-bold">Submitting Report</h4>
                  <p class="state-text">
                    Please wait while we process your request...
                  </p>
                </template>

                <!-- Report confirmation -->
                <template v-else-if="isSubmitted">
                  <div class="d-flex align-items-center mb-2">
                    <div
                      class="success-icon-bg d-flex align-items-center justify-content-center me-2"
                    >
                      <i class="pi pi-check"></i>
                    </div>
                    <h4 class="success-title m-0">Thank You</h4>
                  </div>
                  <p class="state-text pt-4">
                    We've received your report for this
                    <strong>{{ type }}</strong
                    >. Our moderation team will review it shortly.
                  </p>
                  <button
                    class="btn-primary-action mt-3 w-50"
                    @click="closeModal"
                  >
                    Got it, thanks!
                  </button>
                </template>
              </div>

              <div v-else class="selection-view">
                <p class="helper-text">
                  Why are you reporting this {{ type }}?
                </p>

                <!-- Error popup -->
                <div
                  v-if="errorMessage"
                  class="error-banner d-flex align-items-center fs-6 gap-2 mb-3 p-2 rounded-2"
                  :class="{ 'shake-err': isShaking }"
                >
                  <i class="pi pi-exclamation-triangle"></i>
                  <span>{{ errorMessage }}</span>
                </div>

                <!-- Report tag list -->
                <div class="reason-list d-flex flex-column gap-3">
                  <button
                    v-for="tag in reportTags"
                    :key="tag.tagId"
                    class="reason-card d-flex align-items-center justify-content-left justify-content-between p-3 rounded-3"
                    :class="{ 'is-selected': selectedTagID === tag.tagId }"
                    @click="toggleTagSelection(tag.tagId)"
                  >
                    <span class="reason-label">{{ tag.name }}</span>
                    <div
                      class="report-radio d-flex justify-content-center align-items-center"
                    >
                      <div class="radio-dot"></div>
                    </div>
                  </button>
                </div>
                <div class="report-footer-inline d-flex flex-column mt-4 gap-2">
                  <button
                    class="btn-primary-action"
                    @click="submitReport"
                    :disabled="!selectedTagID || isLoading"
                  >
                    <span
                      v-if="isLoading"
                      class="pi pi-spin pi-spinner me-2"
                    ></span>
                    Submit Report
                  </button>
                  <button class="btn-cancel" @click="closeModal">
                    I changed my mind
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.custom-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(8px);
  z-index: 9999;
  padding: 1.5rem;
}

.report-body {
  background: #ffffff;
  width: 100%;
  max-width: 420px;
  max-height: 85vh;
  border-radius: 2rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

/* Report header styling */
.report-header {
  background: #004750;
  color: white;
}
.header-content {
  min-width: 0;
}
.header-icon {
  background: rgba(255, 255, 255, 0.15);
  width: 36px;
  height: 36px;
}
.report-title {
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1;
}
.target-subtitle {
  max-width: 250px;
  color: white;
}
.icon-close {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s;
}
.icon-close:hover {
  background: rgba(255, 255, 255, 0.26);
}

.report-content {
  padding: 1.5rem;
  overflow-y: auto;
  flex-grow: 1;
}

.success-icon-bg {
  width: 32px;
  height: 32px;
  background: #007a4b3b;
  color: #007a4c;
  border-radius: 50%;
}

.helper-text {
  font-size: 0.9rem;
  color: #64748b;
  font-weight: 600;
  margin-bottom: 1.25rem;
}

/* Report options */
.reason-card {
  background: #9f342300;
  border: 2px solid #8397945e;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
}
.reason-card:hover {
  background: #9f342317;
  border-color: #9f3323;
  transform: translateY(-1px);
}
.reason-card:hover .report-radio {
  border-color: #9f3423dc;
  transition: all 0.2s;
}
.reason-card.is-selected {
  background: #9f342348;
  border-color: #9f3323;
}
.reason-label {
  font-weight: 500;
  color: #000000c4;
}
.report-radio {
  width: 20px;
  height: 20px;
  border: 2px solid #8397945e;
  border-radius: 50%;
}
.is-selected .report-radio {
  border-color: #9f3323;
  background: #9f3323;
}
.radio-dot {
  width: 6px;
  height: 6px;
  background: white;
  border-radius: 50%;
  transform: scale(0);
  transition: all 0.2s;
}
.is-selected .radio-dot {
  transform: scale(1);
}

/* Submit button */
.btn-primary-action {
  background: #004750;
  color: white;
  border: none;
  border-radius: 1rem;
  padding: 1rem;
  font-weight: 700;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: all 0.2s;
}
.btn-primary-action:not(:disabled):hover {
  background: #004750e0;
  transform: translateY(-1px);
}
.btn-primary-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-success {
  background: #004750;
}

.btn-cancel {
  background: transparent;
  border: none;
  color: #004750;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-cancel:hover {
  color: #004750a6;
}

.error-banner {
  background: #fef2f2;
  color: #9f3323;
}
@keyframes shake {
  10%,
  90% {
    transform: translate3d(-1px, 0, 0);
  }
  20%,
  80% {
    transform: translate3d(2px, 0, 0);
  }
  30%,
  50%,
  70% {
    transform: translate3d(-4px, 0, 0);
  }
  40%,
  60% {
    transform: translate3d(4px, 0, 0);
  }
}
.shake-err {
  animation: shake 0.3s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}
</style>
