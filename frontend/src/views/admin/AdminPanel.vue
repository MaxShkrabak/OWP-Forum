<script setup>
import { ref } from 'vue';
import OWPLogoSmall from '@/assets/img/svg/owp-symbol-wht.svg'
import { RouterLink } from 'vue-router';
import AdminUsers from '@/components/admin/AdminUsers.vue';
import AdminCategories from '@/components/admin/AdminCategories.vue';
import AdminTags from '@/components/admin/AdminTags.vue';
import AdminReports from '@/components/admin/AdminReports.vue';
  
const activeTab = ref('Users');
  
const tabs = ref([
    { name: 'Users', icon: 'bi-person-fill-gear' },
    { name: 'Categories', icon: 'bi-file-text' },
    { name: 'Tags', icon: 'bi-tags-fill' },
    { name: 'Reports', icon: 'bi-flag-fill' }
])

const isNavOpen = ref(false);

const toggleNav = () => {
  isNavOpen.value = !isNavOpen.value;
};
</script>

<template>
    <div class="page">
        <div class="page-container d-flex">
            <div class="panel-nav" :class="{ 'panel-nav-open': isNavOpen }">
                <div class="nav-header py-1">
                    <button class="nav-toggle ms-2 pt-1" style="position: absolute;" @click="toggleNav">
                            <span class="text-white">☰</span>
                        </button>
                    <div class="nav-logo-container p-2">
                        
                        <RouterLink to="/">
                            <img :src="OWPLogoSmall" alt="owp logo small" class="nav-logo">
                        </RouterLink>
                        <span class="forum-title d-none d-sm-inline">Forum</span>
                    </div>
                </div>

                <div class="nav-divider mb-3 mt-2 mt-md-1"></div>
                <span class="forum-title fs-6 ps-2 d-sm-none">Forum:</span>

                <div class="nav-opts" v-for="tab in tabs" :key="tab.name">
                    <div class="btn-container mb-1" :class="{ 'nav-btn-active-container': activeTab === tab.name }">
                        <button class="nav-btns ms-3 py-2 px-0 px-md-1 text-start row"
                            :class="{ 'nav-btn-active': activeTab === tab.name }" @click="activeTab = tab.name , toggleNav()">
                            <i class="bi col-auto" :class="tab.icon"></i>
                            <span class="nav-name col-auto">{{ tab.name }}</span>
                            <i class="bi bi-arrow-right-short col-auto d-none d-lg-block"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="panel-content pt-2 px-2 overflow-auto">
                <button class="nav-toggle" @click="toggleNav">
                    <span class="text-black">☰</span>
                </button>
                <div class="tab-content h-100" v-for="tab in tabs" :key="'content-'+tab.name" v-show="activeTab === tab.name">
                    
                    <template v-if="tab.name === 'Users'">
                        <AdminUsers />
                    </template>
                    <template v-else-if="tab.name === 'Tags'">
                        <AdminTags />
                    </template>

                    <template v-else-if="tab.name === 'Categories'">
                        <AdminCategories />
                    </template>

                    <template v-else-if="tab.name === 'Reports'">
                        <AdminReports />
                    </template>

                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-container {
    min-height: 85vh;
    height: 100vh;
}

.panel-nav {
    background: linear-gradient(210deg, #005f6b 0%, #004750 100%);
    opacity: 97%;
    width: 70%;
    height: 100%;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
    position : absolute;
    z-index: 1000;
}
.panel-nav-open {
    transform: translateX(0);
}

.panel-content {
    background-color: #cbdad5;
    width: 100%;
    transition: all 0.25s ease;
}

.nav-toggle {
    background: none;
    border: none;
    font-size: 1.5rem;
    display: inline-block;
    margin-right: 0.5rem;
}

.nav-logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.8rem;
    .forum-title {
        color: rgba(255, 255, 255, 0.836);
    }
}

.forum-title {
    font-size: 2.0rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.418);
    font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
}

.nav-logo {
    width: 3.8rem;
}

.nav-divider {
    margin: auto;
    background-color: #6dbe4b;
    width: 75%;
    height: 3px;
    border-radius: 10px;
    margin-top: 6px;
    box-shadow: 0 2px 8px #6ebe4b86;
}

.nav-btns {
    border: none;
    background: none;
    transition: all 0.2s ease;
}

.nav-btn-active-container {
    border-right: 5px #6dbe4b solid;
    border-radius: 4px;
}

.nav-btns.nav-btn-active {
    background-color: green !important;
    border-radius: 5px;
}

.nav-btns:hover {
    background-color: rgba(211, 211, 211, 0.363);
    border-radius: 5px;
    color: white;

    .nav-btn-active {
        background: none;
    }
}

.nav-btns span,
.nav-btns i {
    color: white;
}

.nav-name {
    display: inline-block;
    width: unset;
    font-size: large; font-weight: 600; 
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #004750;
}

@media (min-width: 576px) {
    .panel-nav { 
        width: 40%;
    }
    .nav-logo { width: 3rem; }
}

@media (min-width: 768px) {
    .nav-logo { width: 3rem; }
    .nav-name { font-size: large; width: 14vw; font-weight: 600; }
    .panel-nav { 
        width: 25%;
        opacity: 100%;
        transform: translateX(0);
        position: inherit;
        }
    .panel-content { width: 75%; }
    .nav-toggle { display: none; }
}

@media (min-width: 992px) {
    .nav-logo { width: 4rem; }
}

@media (min-width: 1400px) {
    .nav-logo { width: 4rem; }
}
</style>