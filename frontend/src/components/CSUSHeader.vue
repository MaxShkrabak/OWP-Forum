<script>
import defaultLogo from '@/assets/img/svg/sac-state-logo-3Color.svg'
import smallLogo from '@/assets/img/png/logo-horizontal-small.png'

export default {
  name: 'HeaderCSUS',
  data() {
    return { query: '' }
  },
  computed: {
    defaultLogo: () => defaultLogo,
    smallLogo: () => smallLogo
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

<template>
  <header class="csus-bar" role="banner">
    <div class="csus-wrap">
      <!-- CSUS wordmark -->
      <div class="left">
        <picture>
          <source :srcset="smallLogo" media="(max-width: 464px)"/>
          <img class="logo" :src="defaultLogo" alt="Sacramento State Logo" decoding="async"/>
        </picture>
      </div>

      <!-- Search -->
      <form class="searchComp" @submit.prevent="goSearch">
          <input
            v-model="query"
            type="search"
            class="search-box search"
            placeholder="Search..."
            aria-label="Search Sacramento State"
          />
          <button class="icon-btn" type="submit" aria-label="Submit search">
          </button>
          <img src="@/assets/img/svg/search-icon.svg" alt="search" class="icon">
      </form>
    </div>
  </header>
</template>

<style scoped>
:root {
  --csus-green: #043927;
  --ink: #1f2937;
}

/* Header */
.csus-bar {
  background: #ffffff;
  color: var(--ink);
  border-bottom: 1px solid #e5e7eb;
  height: 71px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-left: 0.3em;
  box-sizing: border-box;
}

.csus-wrap {
  width: 100%;
  margin: 0;
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
}

/* Search */
.searchComp {
  display: flex;
  justify-content: center;
  align-items: center;
  padding-right: 1em;
}

.search {
  display: flex;
  align-items: center;
  background: #ffffff;
  border: 1.5px solid #ccc;
  border-radius: 6px;
  margin-right: 3px;
  height: 39px;
  padding: 2px 8px;
  min-width: 250px;
}

.search:focus {
  box-shadow: 0 0 0 2px rgba(204, 204, 204, 0.22);
  border-color: rgb(0, 0, 0, .5);
  outline: none;
  width: 14rem;
  max-width: 40vw;
  color: rgb(46, 46, 46);
  background: transparent;
  transition: .2s;
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
  width: 28px;
  height: 28px;
  cursor: pointer;
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
/* Small Sac State logo */
@media (max-width: 464px) {
  .csus-bar .logo {
    height: 35px;
    width: auto;
    padding-left: 10px;
  }
}

header {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
  box-sizing: border-box;
}
</style>
