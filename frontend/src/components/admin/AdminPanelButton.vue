<script setup>
import { userRole } from "@/stores/userStore";
import { RouterLink } from "vue-router";

</script>

<template>
    <div class="action-container" v-show="userRole === 'admin'">
        <RouterLink to="/admin" class="btn-admin shadow-sm">
            <div class="btn-content">
                <div class="label-group">
                    <div class="icon-wrap">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="btn-text">To Admin Panel</span>
                </div>
            </div>
        </RouterLink>
    </div>
</template>

<style scoped>
.action-container {
    width: 100%;
}

.btn-admin {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 80px;
    padding: 10px 15px;

    background: linear-gradient(135deg, #007a4c 0%, #1a3c34 100%);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(154, 51, 36, 0.2);

    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-admin i {
    font-size: 1.3rem;
}

.btn-admin::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: 0.6s;
}

.btn-admin:hover:not(:disabled) {
    transform: translateY(-2px);
    filter: brightness(1.1);
    box-shadow: 0 8px 20px -5px rgba(154, 51, 36, 0.4) !important;
}

.btn-admin:hover:not(:disabled)::after {
    left: 100%;
}

.btn-admin:disabled {
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