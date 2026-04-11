<script setup>
import { computed } from "vue";

const props = defineProps({
  page: { type: Number, required: true },
  perPage: { type: Number, required: true },
  total: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  /** Accessible label for the per-page select, e.g. "Users per page" */
  perPageLabel: { type: String, default: "Per page" },
});

const emit = defineEmits(["update:page", "update:perPage"]);

const perPageOptions = [5, 10, 25, 50, 100];

const totalPages = computed(() =>
  props.total <= 0 ? 1 : Math.max(1, Math.ceil(props.total / props.perPage)),
);

const rangeFrom = computed(() => {
  if (props.total <= 0) return 0;
  return (props.page - 1) * props.perPage + 1;
});

const rangeTo = computed(() => {
  if (props.total <= 0) return 0;
  return Math.min(props.page * props.perPage, props.total);
});

function goPrev() {
  if (props.loading || props.page <= 1) return;
  emit("update:page", props.page - 1);
}

function goNext() {
  if (props.loading || props.page >= totalPages.value) return;
  emit("update:page", props.page + 1);
}

function onPerPageChange(e) {
  const next = Number(e.target.value);
  if (!perPageOptions.includes(next)) return;
  emit("update:perPage", next);
}
</script>

<template>
  <div class="admin-pag" :class="{ 'is-loading': loading }">
    <div class="admin-pag-summary" aria-live="polite">
      <span v-if="total === 0">No results</span>
      <span v-else>
        {{ rangeFrom }}–{{ rangeTo }} of {{ total }}
      </span>
    </div>

    <div class="admin-pag-actions">
      <label class="admin-pag-per-label">
        <span class="admin-pag-per-text">{{ perPageLabel }}</span>
        <select
          class="admin-pag-select"
          :value="perPage"
          :disabled="loading"
          @change="onPerPageChange"
        >
          <option v-for="n in perPageOptions" :key="n" :value="n">
            {{ n }}
          </option>
        </select>
      </label>

      <div class="admin-pag-nav" role="group" aria-label="Pagination">
        <button
          type="button"
          class="admin-pag-btn"
          aria-label="Previous page"
          :disabled="loading || page <= 1"
          @click="goPrev"
        >
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
          <span class="admin-pag-btn-text">Prev</span>
        </button>
        <span
          class="admin-pag-page"
          :aria-label="`Page ${page} of ${totalPages}`"
        >
          <span class="admin-pag-page-long">Page {{ page }} / {{ totalPages }}</span>
          <span class="admin-pag-page-short" aria-hidden="true">
            {{ page }} / {{ totalPages }}
          </span>
        </span>
        <button
          type="button"
          class="admin-pag-btn"
          aria-label="Next page"
          :disabled="loading || page >= totalPages"
          @click="goNext"
        >
          <span class="admin-pag-btn-text">Next</span>
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-pag {
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 16px;
  padding: 12px 4px 4px;
  border-top: 1px solid #e5e7eb;
}

.admin-pag-summary {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  text-align: center;
  padding: 0 2px;
  word-break: break-word;
  overflow-wrap: anywhere;
  min-width: 0;
}

.admin-pag-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 100%;
  max-width: 100%;
  min-width: 0;
}

/* Label + select stay one unit; never space-between on narrow viewports */
.admin-pag-per-label {
  box-sizing: border-box;
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  flex-wrap: nowrap;
  gap: 8px;
  min-width: 0;
  max-width: 100%;
  font-size: 12px;
  font-weight: 700;
  color: #334155;
}

.admin-pag-per-text {
  flex: 0 1 auto;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.admin-pag-select {
  box-sizing: border-box;
  flex: 0 0 auto;
  width: auto;
  min-width: 3rem;
  max-width: min(5.5rem, 100%);
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: #fff;
  font-weight: 700;
  font-size: 13px;
  color: #0f172a;
}

.admin-pag-nav {
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  max-width: 100%;
  min-width: 0;
}

.admin-pag-page {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 12px;
  font-weight: 800;
  color: #0f172a;
  text-align: center;
}

.admin-pag-page-long {
  display: none;
}

.admin-pag-page-short {
  display: inline;
}

.admin-pag-btn {
  box-sizing: border-box;
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 44px;
  min-height: 44px;
  padding: 10px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #fff;
  font-weight: 800;
  font-size: 14px;
  color: #004750;
  cursor: pointer;
  transition:
    background 0.15s ease,
    border-color 0.15s ease;
}

.admin-pag-btn-text {
  display: none;
}

.admin-pag-btn:hover:not(:disabled) {
  background: #f0fdfa;
  border-color: #004750;
}

.admin-pag-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.admin-pag.is-loading .admin-pag-btn {
  pointer-events: none;
}

@media (min-width: 576px) {
  .admin-pag-summary {
    font-size: 13px;
  }

  .admin-pag {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
  }

  .admin-pag-summary {
    text-align: left;
  }

  .admin-pag-actions {
    flex-direction: row;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 14px;
    width: auto;
    max-width: none;
  }

  .admin-pag-per-label {
    font-size: 13px;
    gap: 10px;
    justify-content: flex-end;
  }

  .admin-pag-select {
    min-width: 72px;
    max-width: 100%;
    padding: 8px 12px;
  }

  .admin-pag-nav {
    flex: 0 0 auto;
    width: auto;
    max-width: none;
  }

  .admin-pag-page-long {
    display: inline;
  }

  .admin-pag-page-short {
    display: none;
  }

  .admin-pag-page {
    flex: 0 0 auto;
    min-width: 7rem;
    font-size: 13px;
  }

  .admin-pag-btn {
    min-width: 88px;
    min-height: unset;
    padding: 10px 12px;
  }

  .admin-pag-btn-text {
    display: inline;
  }
}
</style>
