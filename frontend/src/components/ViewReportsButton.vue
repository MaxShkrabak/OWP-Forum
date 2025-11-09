<script setup>
import { ref } from "vue";

const role = ref("Moderator");
const totalReports = ref(2);
</script>

<template>
  <button
    class="btn"
    data-bs-toggle="modal"
    data-bs-target="#viewReports"
    v-show="role === 'Moderator' || role === 'Admin'"
    :disabled="totalReports == 0"
  >
    <div class="row text-center text-nowrap text-white lh-1">
      <div class="col-12 lead">
        <span class="roboto-bold">View Reports</span>
      </div>
      <div class="col-12">
        <span class="roboto-bold">{{ totalReports }} Unresolved</span>
      </div>
    </div>
  </button>

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
</template>

<style scoped>
.btn {
    background-color: #B65433;
    height: 84px;
    width: fit-content;
}
</style>
