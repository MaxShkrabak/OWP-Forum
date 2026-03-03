<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { userRole } from "@/stores/userStore";
import { fetchReports, resolveReport } from "@/api/reports";

const router = useRouter();
const reports = ref([]);
const loading = ref(false);
const error = ref(null);
const refreshCooldown = ref(false);
const refreshCooldownSpinner = ref(false);

const toastEl = ref(null);
const showToast = () => {
  if (toastEl.value) {
    const toast = new bootstrap.Toast(toastEl.value);
    toast.show();
  }
};

const mostRecentTicket = ref(null);

const sources = ref([
    { name: 'Posts', icon: 'bi-file-earmark-post-fill' },
    { name: 'Comments', icon: 'bi-chat-left-dots-fill' }
])

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

function refreshReports() {
  if (refreshCooldown.value) return;
  refreshCooldown.value = true;
  refreshCooldownSpinner.value = true;
  loadReports();
  setTimeout(() => {
    refreshCooldown.value = false;
  }, 5000);
  setTimeout(() => {
    refreshCooldownSpinner.value = false;
  }, 800);
}

function hideModal() {
  const modalEl = document.getElementById("viewReports");
  if (modalEl && window.bootstrap?.Modal) {
    const instance = window.bootstrap.Modal.getInstance(modalEl);
    if (instance) instance.hide();
  }
}

function goToPost(postId) {
  if (!postId) return;
  hideModal();
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
  mostRecentTicket.value = reportId;
  showToast();
}

function onModalShown() {
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
      <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div ref="toastEl" id="myToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="4000">
          <div class="d-flex">
            <div class="toast-body">
            Resolved Ticket: #{{ mostRecentTicket }}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        </div>
      </div>

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
              <span class="modal-title fs-4" id="viewReportsModal">
                Submitted Reports
              </span>
              <button
                class="btn-refresh"
                @click="refreshReports"
                :disabled="refreshCooldown"
              >Refresh
              <i class="spinner-border fs-6 ms-2" style="width: 18px; height: 18px;" v-if="refreshCooldownSpinner"></i>
              <i class="bi-arrow-clockwise fs-6 ms-2" style="width: 18px; height: 18px;" v-else></i>
            </button>

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
                  class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2 pb-3"
                >
                  <div class="report-details">
                    <span class="report-id text-muted small text-center">
                      <span class="report-source-by">
                        <i class="mx-1" :class="r.source === 'Comment' ? sources[1].icon : sources[0].icon"></i>
                        Ticket: </span>#{{ r.reportId }}
                    </span>
                      <div class="col-12">
                        <span class="report-source col-12">
                          <span class="me-2">{{ r.source === 'Comment' ? 'Comment' : 'Post' }}</span>
                          <span class="report-source-by me-2">by</span> 
                          <span class="report-source-author">{{ r.source === 'Comment' ? r.commentAuthor : r.postAuthor }}</span>
                        </span>
                        <span class="report-title col-12 my-1 text-truncate">"{{ r.source === 'Comment' ? r.commentText : r.postTitle }}"</span>
                        <div class="col-12 gap-3 d-flex flex-wrap align-items-center">
                          <span class="report-reporter text-muted small col-auto">
                            <span class="report-source-by">Reported by: </span>
                            {{ r.reporter.fullName }}
                          </span>
                          <span class="report-reason col-auto">
                            <span class="report-source-by">Reason: </span>
                            {{ r.reason }}
                          </span>
                          <span class="report-date text-muted small col-12 col-xl-auto">At: {{ r.createdAt }}</span>
                        </div>
                    </div>
                  </div>
                  <div class="cta-btns d-flex gap-2 flex-shrink-0">
                    <button
                      type="button"
                      class="report-cta-btn text-white"
                      @click="goToPost(r.postId)"
                    >
                      Go To
                      <i class="ms-2" :class="r.source === 'Comment' ? sources[1].icon : sources[0].icon"></i>
                    </button>
                    <button
                      type="button"
                      class="report-cta-btn text-white"
                      @click="handleResolve(r.reportId)"
                    >
                      Resolve
                      <i class="bi-check2-square ms-1"></i>
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

.modal-header {
  position: sticky;
  top: 0;
  z-index: 1055;
  background-color: white;
}
.btn-refresh {
  margin-left: 2rem;
  padding: 0.5rem 1rem;
  background: #006649;
  border-radius: 10px;
  color: #ffffff;
  font-size: 1.2rem;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.15s ease;
  border: none;
}
.btn-refresh:disabled {
    background-color: #04392769 !important;
    cursor: not-allowed;
}
.btn-refresh:hover:not(:disabled) {
  color: #6dbe4b;
}
.btn-refresh:active:not(:disabled) {
  color: #a1fd7ae8;
  background-color: #017e5a !important;
}

.report-list {
  border-radius: 0;
}
.list-group-item{
  transition: background-color 0.15s ease;
}
.list-group-item:hover {
  background-color: rgba(211, 211, 211, 0.1);
  border-radius: 6px;
  .report-id {
    background-color: rgba(211, 211, 211, 0.842);
  }
}
.report-details {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
}
.report-id {
  min-width: 60px;
  font-weight: 700;
  color: #6d6d6d;
  background-color: rgba(211, 211, 211, 0.603);
  padding: .7rem 0.5rem;
  border-top-left-radius: 8px;
  border-bottom-left-radius: 8px;
  border-right: #007a4c solid 3px;
  transition: background-color 0.15s ease;
}
.report-source {
  font-weight: 700;
  .report-source-author {
    text-transform: capitalize;
  }
}
.report-source-by {
    font-weight: 400;
    font-size: .8rem;
    margin: 0 4px 0 0;
    color: #838383;
  }
.report-title {
  display: block;
  max-width: 10rem;
}
.report-reporter {
  font-weight: 700;
  text-transform: capitalize;
}
.report-reason {
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  background: rgba(154, 51, 36, 0.12);
  border-radius: 6px;
  font-size: 0.9rem;
}
.report-date {
  font-size: 0.8rem;
}
.report-cta-btn {
  padding: 0.45rem 0.7rem;
  font-size: 0.85rem;
  border-radius: 6px;
  background-color: #007a4c;
  border: none;
}
.report-cta-btn:hover {
  background-color: #007a4cce;
}
.report-cta-btn:active{
  background-color: #007a4c90;
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
  .report-title {
  max-width: 20rem;
  }
}
@media (min-width: 576px) {
  .report-title {
    max-width: 25rem;
  }
}
@media (min-width: 992px) {
  .report-title {
    max-width: 30rem;
  }
  .cta-btns {
    flex-direction: column;
  }
}
@media (min-width: 1200px) {
  .report-title {
    max-width: 45rem;
  }
  .cta-btns {
    flex-direction: row;
  }
}
</style>