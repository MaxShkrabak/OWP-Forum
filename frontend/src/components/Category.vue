<template>
  <div class="box">
    <div class="field-row">
      <label for="category" class="field-label">Category:</label>
      <select
        id="category"
        v-model="categoryId"
        class="category-input"
      >
        <option :value="null"> </option>
        <option v-for="c in categories" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>
    </div>

    <div v-if="error" class="error-box">
      {{ error }}
      <button
        v-if="!categoryId && defaultCategoryId"
        class="btn-link"
        type="button"
        @click="useDefault"
      >
        Use default ({{ defaultCategoryName }})
      </button>
    </div>

    <div v-if="success" class="success-box">
       {{ success }}
    </div>

    <button class="btn" type="button" @click="publish">Publish</button>
  </div>
</template>

<script>
export default {
  name: 'PostComposer',
  data() {
    return {
      categories: [
        { id: 1, name: 'General' },
        { id: 2, name: 'News' },
        { id: 3, name: 'Events' }
      ],
      defaultCategoryId: 1,
      categoryId: null,
      error: '',
      success: ''
    };
  },
  computed: {
    defaultCategoryName() {
      const c = this.categories.find(c => c.id === this.defaultCategoryId);
      return c ? c.name : 'Default';
    }
  },
  methods: {
    publish() {
      this.error = '';
      this.success = '';

      if (!this.categoryId) {
        this.error = 'You must choose a category before publishing.';
        return;
      }

      this.success = `Published with category: ${this.getCategoryName(this.categoryId)}`;
    },
    useDefault() {
      this.categoryId = this.defaultCategoryId;
      this.error = '';
      this.success = `Default category selected: ${this.defaultCategoryName}`;
    },
    getCategoryName(id) {
      const cat = this.categories.find(c => c.id === id);
      return cat ? cat.name : 'Unknown';
    }
  }
};
</script>

<style scoped>
.box {
  max-width: 480px;
  padding: 16px;
  border: 1px solid #ddd;
  border-radius: 8px;
}

.field-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 6px 0 14px;
}

.field-label {
  font-weight: 700;
  color: #1f2937;
  min-width: 88px;
}

.category-input {
  width: 230px;
  height: 34px;
  padding: 0 34px 0 10px;
  font-size: 14px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background-color: #fff;
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.04);
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 16px 16px;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.category-input:focus {
  border-color: #94c5a5;
  box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
}

.category-input option[value=""] {
  color: #6b7280;
}

.btn {
  background: #166534;
  color: #fff;
  padding: 8px 14px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}
.btn:hover {
  background: #15803d;
}
.btn-link {
  background: none;
  border: none;
  color: #2563eb;
  text-decoration: underline;
  cursor: pointer;
  font-size: 0.9rem;
  margin-left: 6px;
}


.error-box {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 10px;
  margin: 8px 0;
}

.success-box {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 10px;
  margin: 8px 0;
}
</style>
