<script setup>
import { ref } from "vue";
import { userRole } from "@/stores/userStore";

const totalReports = ref(5);
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
          <div class="modal-body"></div>
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