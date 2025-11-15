<script setup>

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

function resetSelections(){
    document.getElementById("buttonOptions").reset();
}
</script>

<template>
    <div class="modal fade" id="reports" tabindex="-1" aria-labelledby="reportsModal" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h1 class="modal-title fs-4" id="reportsModal">Submit a report for a 
                <span class="fw-bold" v-if="props.isPost">Post by {{ props.useID }}</span>
                <span class="fw-bold" v-else>Comment by {{ props.useID }}</span>
            </h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form class="modal-body" id="buttonOptions">
            <div class="row">
                <div class="col-auto" v-for="opt in reportOpts">
                    <input type="radio" class="btn-check" name="reportOptions" :id=opt autocomplete="off">
                    <label class="btn btn-success btn-outline-warning badge fs-6" :for=opt>{{ opt }}</label>
                </div>
            </div>
          </form>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="resetSelections">Cancel</button>
            <button type="button" class="btn btn-primary" @click="resetSelections">Submit</button>
          </div>
        </div>
      </div>
    </div>
</template>
<style scoped> 
.row {
    padding: 1em;
}
.modal-backdrop {
  background-color: #000; /* Darker background color (black in this case) */
  opacity: 0.2; /* Adjust opacity for desired darkness */
}
</style>