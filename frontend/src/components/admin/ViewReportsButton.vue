<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { userRole } from "@/stores/userStore";
import { fetchReports, resolveReport } from "@/api/reports";

const router = useRouter();
const reports = ref([]);
const loading = ref(false);
const error = ref(null);

const totalReports = ref(0);

async function loadReports() {
  loading.value = true;
  error.value = null;
  try {
    const data = await fetchReports();
    if (data?.ok && Array.isArray(data.reports)) {
      reports.value = data.reports;
      totalReports.value = data.reports.length;
    } else {
      reports.value = [];
      totalReports.value = 0;
    }
  } catch (e) {
    console.error("Failed to load reports:", e);
    error.value = e.message || "Failed to load reports";
    reports.value = [];
    totalReports.value = 0;
  } finally {
    loading.value = false;
  }
}

function goToPost(postId) {
  if (!postId) return;
  const modalEl = document.getElementById("viewReports");
  if (modalEl && window.bootstrap?.Modal) {
    const instance = window.bootstrap.Modal.getInstance(modalEl);
    if (instance) instance.hide();
  }
  router.push(`/posts/${postId}`);
}

async function handleResolve(reportId) {
  try {
    const data = await resolveReport(reportId);
    if (data?.ok) {
      reports.value = reports.value.filter((r) => r.reportId !== reportId);
      totalReports.value = reports.value.length;
    }
  } catch (e) {
    console.error("Failed to resolve report:", e);
  }
}

function onModalShown() {
  loadReports();
}

onMounted(() => {
  if (userRole.value === "moderator" || userRole.value === "admin") {
    loadReports();
  }
  const modalEl = document.getElementById("viewReports");
  if (modalEl) {
    modalEl.addEventListener("shown.bs.modal", onModalShown);
  }
});

onUnmounted(() => {
  const modalEl = document.getElementById("viewReports");
  if (modalEl) {
    modalEl.removeEventListener("shown.bs.modal", onModalShown);
  }
});
</script>

<template>
  <div class="action-container" v-show="userRole === 'moderator' || userRole === 'admin'">
    
    <button
      class="btn-reports shadow-sm"
      data-bs-toggle="modal"
      data-bs-target="#viewReports"
      :disabled="totalReports == 0"
    >
    
      <div class="btn-content">
        <div class="label-group">
          <div class="icon-wrap">
            <i class="pi pi-flag-fill"></i>
          </div>
          <span class="btn-text">View Reports</span>
        </div>

        <span v-if="totalReports > 0" class="report-count">
          {{ totalReports }}
        </span>
      </div>
    </button>

    <Teleport to="body">
      <div
        class="modal fade"
        id="viewReports"
        tabindex="-1"
        aria-labelledby="viewReportsModal"
        aria-hidden="true"
      >
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <h1 class="modal-title fs-5" id="viewReportsModal">
                Reports submitted by users
              </h1>
              <button
                type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"
              ></button>
            </div>
            <div class="modal-body">
              <div v-if="loading" class="text-center py-4">
                <div class="spinner-border text-danger"></div>
              </div>
              <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
              <div v-else-if="reports.length === 0" class="text-muted text-center py-4">
                No reports at this time.
              </div>
              <ul v-else class="list-group list-group-flush report-list">
                <li
                  v-for="r in reports"
                  :key="r.reportId"
                  class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2"
                >
                  <div class="report-details">
                    <span class="report-source">{{ r.source }}</span>
                    <span class="report-reason">{{ r.reason }}</span>
                    <span class="report-date text-muted small">{{ r.createdAt }}</span>
                  </div>
                  <div class="d-flex gap-2 flex-shrink-0">
                    <button
                      v-if="r.postId"
                      type="button"
                      class="btn btn-sm btn-outline-primary"
                      @click="goToPost(r.postId)"
                    >
                      Go to Post
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-success"
                      @click="handleResolve(r.reportId)"
                    >
                      Resolve
                    </button>
                  </div>
                </li>
              </ul>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                data-bs-dismiss="modal"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.action-container {
  width: 100%;
}

.btn-reports {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 80px;
  padding: 10px 15px;

  background: linear-gradient(135deg, #9a3324 0%, #5d2a2c 100%);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(154, 51, 36, 0.2);

  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s ease;
}
.btn-reports i {
  font-size: 1.3rem;
}
.btn-reports::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
  transition: 0.6s;
}
.btn-reports:hover:not(:disabled) {
  transform: translateY(-2px);
  filter: brightness(1.1);
  box-shadow: 0 8px 20px -5px rgba(154, 51, 36, 0.4) !important;
}
.btn-reports:hover:not(:disabled)::after {
  left: 100%;
}
.btn-reports:disabled {
  background: #d5d8db;
  color: #90979e;
  cursor: not-allowed;
  transform: none;
}

.btn-content,
.label-group,
.icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-content {
  flex-direction: column;
  gap: 4px;
}
.label-group {
  flex-direction: column;
  gap: 2px;
}
.icon-wrap {
  line-height: 1;
}

.btn-text {
  font-weight: 700;
  font-family: 'Roboto', sans-serif;
  text-transform: uppercase;
  font-size: 1rem;
  letter-spacing: 0.5px;
  text-align: center;
  line-height: 1.2;
}
.report-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  padding: 1px 10px;

  background-color: #ee5656;
  color: white;
  font-size: 0.85rem;
  font-weight: 800;
  border-radius: 50px;
  border: 1.5px solid rgba(255, 255, 255, 0.4);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.report-list {
  border-radius: 0;
}
.report-details {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
}
.report-source {
  font-weight: 700;
  text-transform: capitalize;
}
.report-reason {
  padding: 0.2rem 0.5rem;
  background: rgba(154, 51, 36, 0.12);
  border-radius: 6px;
  font-size: 0.9rem;
}
.report-date {
  font-size: 0.8rem;
}

@media (min-width: 432px) {
  .label-group {
    flex-direction: row;
    gap: 8px;
  }
  .btn-content {
    flex-direction: row;
    gap: 10px;
  }
}
</style>