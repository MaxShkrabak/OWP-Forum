<script setup>
import { ref } from 'vue';
import OWPLogoSmall from '@/assets/img/svg/owp-symbol-wht.svg'
import { RouterLink } from 'vue-router';
import AdminRoles from '@/components/admin/AdminRoles.vue'; 

const activeTab = ref('Roles');
const tabs = ref([
    { name: 'Users', icon: 'bi-person-fill-gear' },
    { name: 'Roles', icon: 'bi-diagram-3-fill' },
    { name: 'Categories', icon: 'bi-file-text' },
    { name: 'Tags', icon: 'bi-tags-fill' },
    { name: 'Reports', icon: 'bi-flag-fill' }
])
</script>

<template>
    <div class="page">
        <div class="page-container d-flex">
            <div class="panel-nav w-25">
                <div class="nav-header text-center py-1">
                    <div class="nav-logo-container p-2">
                        <RouterLink to="/">
                            <img :src="OWPLogoSmall" alt="owp logo small" class="nav-logo">
                        </RouterLink>
                        <span class="forum-title d-none d-md-inline">Forum</span>
                    </div>
                </div>

                <div class="nav-divider mb-3 mt-2 mt-md-1"></div>
                <span class="forum-title fs-6 ps-2 d-md-none">Forum:</span>

                <div class="nav-opts" v-for="tab in tabs" :key="tab.name">
                    <div class="btn-container mb-1" :class="{ 'nav-btn-active-container': activeTab === tab.name }">
                        <button class="nav-btns ms-1 ms-sm-3 py-2 px-0 px-md-1 text-start row"
                            :class="{ 'nav-btn-active': activeTab === tab.name }" @click="activeTab = tab.name">
                            <i class="bi col-auto d-none d-sm-block" :class="tab.icon"></i>
                            <span class="nav-name col-auto">{{ tab.name }}</span>
                            <i class="bi bi-arrow-right-short col-auto d-none d-lg-block"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="panel-content w-75 pt-4 px-4 overflow-auto">
                <div class="tab-content h-100" v-for="tab in tabs" :key="'content-'+tab.name" v-show="activeTab === tab.name">
                    
                    <template v-if="tab.name === 'Roles'">
                        <AdminRoles />
                    </template>

                    <template v-else>
                        <h2 class="page-title text-start mb-4">{{ tab.name }} Overview</h2>
                        <p class="text-muted text-start">The {{ tab.name }} component will render here.</p>
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

.panel-content {
    background-color: #cbdad5;
}

.panel-nav {
    background: linear-gradient(210deg, #005f6b 0%, #004750 100%);
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
    width: 3.5rem;
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
    font-weight: 400;
    font-size: small;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #004750;
}

@media (min-width: 768px) {
    .nav-logo { width: 3rem; }
    .nav-name { font-size: large; width: 14vw; font-weight: 600; }
}

@media (min-width: 992px) {
    .nav-logo { width: 4rem; }
}

@media (min-width: 1400px) {
    .nav-logo { width: 4rem; }
}
</style>