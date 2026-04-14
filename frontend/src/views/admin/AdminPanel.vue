<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'

import OWPLogoSmall from '@/assets/img/svg/owp-symbol-wht.svg'
import AdminUsers from '@/components/admin/AdminUsers.vue'
import AdminCategories from '@/components/admin/AdminCategories.vue'
import AdminTags from '@/components/admin/AdminTags.vue'
import AdminReports from '@/components/admin/AdminReports.vue'

const activeTab = ref('Users')
const isNavOpen = ref(false)
const isDesktop = ref(false)
const isCollapsed = ref(false)

const tabs = [
  { name: 'Users', icon: 'bi-person-fill-gear' },
  { name: 'Categories', icon: 'bi-folder2-open' },
  { name: 'Tags', icon: 'bi-tags-fill' },
  { name: 'Reports', icon: 'bi-flag-fill' }
]

const currentComponent = computed(() => {
  switch (activeTab.value) {
    case 'Users':
      return AdminUsers
    case 'Categories':
      return AdminCategories
    case 'Tags':
      return AdminTags
    case 'Reports':
      return AdminReports
    default:
      return AdminUsers
  }
})

const getScreenMode = () => {
  const width = window.innerWidth

  if (width < 768) return 'mobile'
  if (width < 1200) return 'collapsed'
  return 'full'
}

const updateScreenState = () => {
  const mode = getScreenMode()

  if (mode === 'mobile') {
    isDesktop.value = false
    isNavOpen.value = false
    isCollapsed.value = false
    return
  }

  if (mode === 'collapsed') {
    isDesktop.value = true
    isNavOpen.value = true
    isCollapsed.value = true
    return
  }

  isDesktop.value = true
  isNavOpen.value = true
  isCollapsed.value = false
}

const toggleNav = () => {
  if (!isDesktop.value) {
    isNavOpen.value = !isNavOpen.value
  }
}

const toggleCollapse = () => {
  if (!isDesktop.value) return
  isCollapsed.value = !isCollapsed.value
}

const setActiveTab = (tabName) => {
  activeTab.value = tabName

  if (!isDesktop.value) {
    isNavOpen.value = false
  }
}

onMounted(() => {
  updateScreenState()
  window.addEventListener('resize', updateScreenState)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateScreenState)
})
</script>

<template>
  <div class="admin-page">
    <div
      v-if="isNavOpen && !isDesktop"
      class="sidebar-backdrop"
      @click="isNavOpen = false"
    ></div>

    <div class="admin-layout">
      <aside
        class="sidebar"
        :class="{ open: isNavOpen, collapsed: isCollapsed }"
      >
        <div class="sidebar-header">
          <div
            class="page-heading"
            :class="{ collapsed: isCollapsed }"
            :title="isCollapsed ? 'Admin Panel' : ''"
          >
            <div class="page-heading-main">
              <i class="bi bi-people-fill page-heading-icon"></i>

              <transition name="fade-slide">
                <span v-if="!isCollapsed" class="page-heading-title">
                  Admin Panel
                </span>
              </transition>
            </div>

            <transition name="fade-slide">
              <span v-if="!isCollapsed" class="page-heading-subtitle">
                Forum Management
              </span>
            </transition>
          </div>

          <div class="sidebar-top" :class="{ collapsed: isCollapsed }">
            <RouterLink to="/" class="brand back-link" aria-label="Back to forum">
              <div class="back-icon">
                <i class="bi bi-arrow-left"></i>
              </div>

              <div class="brand-logo-wrap">
                <img :src="OWPLogoSmall" alt="OWP logo" class="brand-logo" />
              </div>

              <transition name="fade-slide">
                <span v-if="!isCollapsed" class="back-text d-none d-sm-block">
                  Back to Forum
                </span>
              </transition>
            </RouterLink>

            <div class="sidebar-actions">
              <button
                v-if="isDesktop"
                class="collapse-btn"
                @click="toggleCollapse"
                :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
              >
                <i
                  class="bi"
                  :class="isCollapsed ? 'bi-chevron-right' : 'bi-chevron-left'"
                ></i>
              </button>

              <button
                v-if="!isDesktop"
                class="close-btn"
                @click="isNavOpen = false"
                aria-label="Close navigation"
              >
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>

          <transition name="fade-slide">
            <div v-if="!isCollapsed" class="current-section-card">
              <div class="current-section-top">
                <span class="current-section-label">Current Section</span>
              </div>

              <div class="current-section-main">
                <div class="current-section-icon-wrap">
                  <i
                    class="bi current-section-icon"
                    :class="tabs.find(tab => tab.name === activeTab)?.icon"
                  ></i>
                </div>

                <div class="current-section-text">
                  <span class="current-section-title">{{ activeTab }}</span>
                  <span class="current-section-subtitle d-none d-sm-block">
                    Admin management area
                  </span>
                </div>
              </div>
            </div>
          </transition>
        </div>

        <div class="sidebar-divider"></div>

        <nav class="nav-menu">
          <button
            v-for="tab in tabs"
            :key="tab.name"
            class="nav-item"
            :class="{ active: activeTab === tab.name, collapsed: isCollapsed }"
            @click="setActiveTab(tab.name)"
            :title="isCollapsed ? tab.name : ''"
          >
            <div class="nav-icon-wrap">
              <i class="bi nav-icon" :class="tab.icon"></i>
            </div>

            <transition name="fade-slide">
              <span v-if="!isCollapsed" class="nav-label">
                {{ tab.name }}
              </span>
            </transition>
          </button>
        </nav>
      </aside>

      <main class="main-panel">
        <header class="topbar">
          <div class="topbar-left">
            <button class="menu-btn" @click="toggleNav" aria-label="Open navigation">
              <i class="bi bi-list"></i>
            </button>
          </div>
        </header>

        <section class="content-card">
          <component :is="currentComponent" />
        </section>
      </main>
    </div>
  </div>
</template>

<style scoped>
.admin-page {
  min-height: 100vh;
  background:
    radial-gradient(circle at top right, rgba(109, 190, 75, 0.12), transparent 20%),
    linear-gradient(180deg, #eef4f2 0%, #dde8e3 100%);
  position: relative;
  overflow: hidden;
}

.admin-layout {
  display: flex;
  min-height: 100vh;
  position: relative;
  min-width: 0;
}

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(5, 20, 24, 0.45);
  backdrop-filter: blur(3px);
  z-index: 999;
}

.sidebar {
  width: 420px;
  max-width: 82vw;
  background: linear-gradient(180deg, #063b43 0%, #0a5963 55%, #0e6974 100%);
  color: white;
  padding: 1.25rem 1rem;
  position: fixed;
  top: 0;
  left: 0;
  min-height: 100%;
  transform: translateX(-100%);
  transition: width 0.28s ease, transform 0.28s ease, box-shadow 0.28s ease;
  z-index: 1000;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.sidebar.open {
  transform: translateX(0);
}

.sidebar.collapsed {
  width: 120px;
}

.sidebar.collapsed .back-link {
  justify-content: center;
  gap: 0;
  width: 100%;
  padding: 0.25rem;
  background: transparent;
}

.sidebar-header {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.page-heading {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 0.95rem 1rem;
  border-radius: 18px;
  background: linear-gradient(135deg, #004b33 0%, #003d4c 100%);
  box-shadow:
    0 10px 25px rgba(0, 0, 0, 0.2),
    inset 0 0 0 1px rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(6px);
}

.page-heading:hover {
  transform: none;
  box-shadow:
    0 10px 25px rgba(0, 0, 0, 0.2),
    inset 0 0 0 1px rgba(255, 255, 255, 0.2);
}

.page-heading-main {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-height: 2rem;
}

.page-heading-icon {
  font-size: 1.5rem;
  color: #ffffff;
  flex-shrink: 0;
}

.page-heading-title {
  font-size: 1.8rem;
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: 0.03em;
  color: #ffffff;
  white-space: nowrap;
}

.page-heading-subtitle {
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.85);
  white-space: nowrap;
}

.page-heading.collapsed {
  align-items: center;
  justify-content: center;
  padding: 0.8rem;
}

.page-heading.collapsed .page-heading-main {
  justify-content: center;
}

.page-heading.collapsed .page-heading-icon {
  font-size: 1.8rem;
}

.sidebar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.sidebar-top.collapsed {
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 0.85rem;
}

.sidebar-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
  flex-shrink: 0;
}

.sidebar-top.collapsed .sidebar-actions {
  margin-left: 0;
  width: 100%;
  justify-content: center;
}

.brand {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  color: white;
  flex-shrink: 0;
}

.sidebar.collapsed .brand {
  width: 100%;
}

.back-link {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.45rem 0.6rem;
  border-radius: 14px;
  background: linear-gradient(135deg, #007a4c 0%, #1a3c34 100%);
  text-decoration: none;
  color: white;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.back-link::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.15),
    transparent
  );
  transition: left 0.6s ease;
}

.back-link:hover {
  background: linear-gradient(45deg, #00A5B5 0%, #1a3c34 100%);
  transform: translateX(-2px);
  filter: brightness(1.1);
}

.back-link:hover::after {
  left: 100%;
}

.back-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.12);
  font-size: 1.15rem;
  flex-shrink: 0;
}

.back-icon i {
  transition: transform 0.2s ease;
}

.back-link:hover .back-icon i {
  transform: translateX(-2px);
}

.back-text {
  font-size: 1rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.92);
  white-space: nowrap;
}

.sidebar.collapsed .back-icon {
  display: none;
}

.brand-logo-wrap {
  width: 58px;
  height: 58px;
  border-radius: 18px;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.08);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
  flex-shrink: 0;
}

.sidebar.collapsed .brand-logo-wrap {
  background: linear-gradient(135deg, #007a4c 0%, #1a3c34 100%);
}

.sidebar.collapsed .brand-logo-wrap:hover {
  background: linear-gradient(45deg, #00A5B5 0%, #1a3c34 100%);
  transform: translateX(-2px);
  transition: 0.5s;
}

.brand-logo {
  width: 38px;
  height: auto;
}

.current-section-card {
  border-radius: 20px;
  padding: 0.95rem 1rem;
  background: linear-gradient(
    180deg,
   #00A5B5,
    rgba(255, 255, 255, 0.177)
  );
  box-shadow:
    inset 0 0 0 1px rgba(255, 255, 255, 0.08),
    0 10px 24px rgba(0, 0, 0, 0.12);
  backdrop-filter: blur(6px);
}

.current-section-top {
  margin-bottom: 0.7rem;
}

.current-section-label {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.72);
}

.current-section-main {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.current-section-icon-wrap {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #007a4c 0%, #6DBE4B 100%);
  box-shadow:
    0 10px 22px rgba(77, 160, 45, 0.28),
    inset 0 0 0 1px rgba(255, 255, 255, 0.18);
  flex-shrink: 0;
}

.current-section-icon {
  font-size: 1.35rem;
  color: white;
}

.current-section-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.current-section-title {
  font-size: 1.08rem;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
  white-space: nowrap;
}

.current-section-subtitle {
  font-size: 0.88rem;
  color: rgba(255, 255, 255, 0.72);
  white-space: nowrap;
}

.close-btn,
.menu-btn,
.collapse-btn {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.close-btn,
.collapse-btn {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  color: rgba(255, 255, 255, 0.92);
  background: rgba(255, 255, 255, 0.08);
  font-size: 1rem;
  display: grid;
  place-items: center;
  transition: background 0.2s ease, transform 0.2s ease;
  flex-shrink: 0;
}

.close-btn:hover,
.collapse-btn:hover {
  background: rgba(255, 255, 255, 0.14);
  transform: translateY(-1px);
}

.collapse-btn i {
  transition: transform 0.25s ease;
}

.sidebar-divider {
  width: 100%;
  height: 1px;
  margin: 1.25rem 0 1rem;
  background: linear-gradient(
    90deg,
    rgba(109, 190, 75, 0.15),
    rgba(109, 190, 75, 0.95),
    rgba(109, 190, 75, 0.15)
  );
  box-shadow: 0 0 18px rgba(109, 190, 75, 0.35);
}

.nav-menu {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  margin-top: 0.5rem;
}

.nav-item {
  width: 100%;
  border: none;
  background: rgba(255, 255, 255, 0.05);
  color: white;
  border-radius: 20px;
  padding: 1.1rem 1.4rem;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 0.95rem;
  text-align: left;
  cursor: pointer;
  transition: all 0.22s ease;
  min-height: 78px;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
}

.nav-item:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.11);
  box-shadow:
    0 10px 24px rgba(0, 0, 0, 0.18),
    inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.nav-item.active {
  background: linear-gradient(135deg, #007a4c 0%, #6DBE4B 100%);
  color: #ffffff;
  box-shadow:
    0 14px 28px rgba(77, 160, 45, 0.34),
    inset 0 0 0 1px rgba(255, 255, 255, 0.15);
}

.nav-item.collapsed {
  justify-content: center;
  padding: 0.85rem 0.5rem;
  min-height: 82px;
  border-radius: 18px;
}

.nav-icon-wrap {
  width: 54px;
  height: 54px;
  border-radius: 16px;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.11);
  flex-shrink: 0;
  transition: width 0.22s ease, height 0.22s ease;
}

.sidebar.collapsed .nav-icon-wrap {
  width: 52px;
  height: 52px;
}

.nav-item.active .nav-icon-wrap {
  background: rgba(255, 255, 255, 0.18);
}

.nav-icon {
  font-size: 1.55rem;
}

.nav-label {
  font-size: 1.12rem;
  font-weight: 800;
  letter-spacing: 0.01em;
  white-space: nowrap;
  text-align: left;
}

.main-panel {
  flex: 1;
  width: 100%;
  min-width: 0;
  padding: 1rem;
  transition: all 0.28s ease;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding: 0.25rem 0;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 0.9rem;
}

.menu-btn {
  width: 50px;
  height: 50px;
  border-radius: 16px;
  background: white;
  color: #0a5963;
  font-size: 1.65rem;
  display: grid;
  place-items: center;
  box-shadow: 0 10px 24px rgba(0, 71, 80, 0.1);
}

.page-title {
  margin: 0;
  font-size: 1.7rem;
  font-weight: 800;
  color: #0a4d56;
}

.page-subtitle {
  margin: 0.2rem 0 0;
  color: #55737a;
  font-size: 0.96rem;
}

.content-card {
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(8px);
  border-radius: 28px;
  padding: 1rem;
  min-height: calc(100vh - 110px);
  min-width: 0;
  box-shadow:
    0 20px 45px rgba(0, 71, 80, 0.08),
    inset 0 0 0 1px rgba(255, 255, 255, 0.45);
  overflow: auto;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.fade-slide-enter-to,
.fade-slide-leave-from {
  opacity: 1;
  transform: translateY(0);
}

@media (min-width: 1200px) {
  .sidebar {
    position: sticky;
    top: 0;
    transform: translateX(0);
    width: 420px;
    max-width: none;
    flex-shrink: 0;
  }

  .menu-btn {
    display: none;
  }
}

@media (min-width: 768px) and (max-width: 1199px) {
  .sidebar {
    position: sticky;
    top: 0;
    transform: translateX(0);
    width: 120px;
    max-width: none;
    flex-shrink: 0;
    padding: 1.1rem 0.75rem;
  }

  .sidebar:not(.collapsed) {
    width: 320px;
    padding: 1.25rem 1rem;
  }

  .menu-btn {
    display: none;
  }
}

@media (min-width: 1400px) {
  .sidebar {
    width: 460px;
  }
}

@media (max-width: 767px) {
  .main-panel {
    padding: 0.85rem;
  }

  .content-card {
    border-radius: 22px;
    padding: 0.85rem;
  }

  .page-title {
    font-size: 1.35rem;
  }

  .page-subtitle {
    font-size: 0.88rem;
  }
  .page-heading-title {
  font-size: 1.5rem;
}
}

</style>