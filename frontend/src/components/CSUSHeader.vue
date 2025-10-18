<template>
  <header class="csus-bar" role="banner">
    <div class="csus-wrap">
      <!-- CSUS wordmark -->
      <div class="left">
        <img
          class="logo"
          :src="logoSrc"
          alt="Sacramento State"
          decoding="async"
        />
      </div>

      <!-- Search -->
      <form class="search" @submit.prevent="goSearch">
        <input
          v-model="query"
          type="search"
          class="search-box"
          placeholder="Search..."
          aria-label="Search Sacramento State"
        />
        <button class="icon-btn" type="submit" aria-label="Submit search">
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true">
            <circle
              cx="11"
              cy="11"
              r="7"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            />
            <path
              d="M20 20l-4.2-4.2"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
            />
          </svg>
        </button>
      </form>
    </div>
  </header>
</template>

<script>
import defaultLogo from '@/assets/Primary_Horizontal_3_Color_gld_hnd_hires.png'

export default {
  name: 'HeaderCSUS',
  props: {
    logoSrc: { type: String, default: () => defaultLogo }
  },
  data() {
    return { query: '' }
  },
  methods: {
    goSearch() {
      const q = this.query.trim()
      if (!q) return
      if (this.$router?.push) {
        this.$router.push({ path: '/search', query: { q } })
      } else {
        console.log('Search:', q)
      }
    }
  }
}
</script>

<style scoped>
:root {
  --csus-green: #043927;
  --ink: #1f2937;
}

/* Header */
.csus-bar {
  width: 100%;
  background: #ffffff;
  color: var(--ink);
  border-bottom: 1px solid #e5e7eb;
  height: 70px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  box-sizing: border-box;
}

.csus-wrap {
  width: 100%;
  margin: 0;
  padding: 8px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  overflow-x: hidden;
  box-sizing: border-box;
}

/* Logo */
.left {
  display: inline-flex;
  align-items: center;
}
.csus-bar .logo {
  height: 55px;
  width: auto;
  margin: 8px 0;
}

/* Search */
.search {
  display: flex;
  align-items: center;
  background: #ffffff;
  border: 1.5px solid #ccc;
  border-radius: 6px;
  padding: 2px 8px;
  min-width: 250px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.search:focus-within {
  border-color: var(--csus-green);
  box-shadow: 0 0 0 2px rgba(4, 57, 39, 0.15);
}

.search input {
  border: none;
  outline: none;
  font-size: 0.95rem;
  width: 14rem;
  max-width: 40vw;
  color: var(--ink);
  background: transparent;
}
.search input::placeholder {
  color: #9aa2a9;
}

/* Icon */
.icon-btn {
  display: grid;
  place-items: center;
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--csus-green);
  padding: 0 4px;
  transition: color 0.2s;
}
.icon-btn:hover {
  color: #006633;
}
.icon {
  width: 22px;
  height: 22px;
}

/* Responsive */
@media (max-width: 800px) {
  .logo {
    height: 38px;
  }
  .search input {
    width: 12rem;
  }
}
@media (max-width: 560px) {
  .logo {
    height: 32px;
  }
  .search {
    min-width: 0;
  }
  .search input {
    width: 10rem;
    max-width: 50vw;
  }
}

header {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
  box-sizing: border-box;
}
</style>
