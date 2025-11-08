<script setup>
<<<<<<< HEAD
import CreatePostButton from '@/components/CreatePostButton.vue';
=======
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
>>>>>>> 63b020a2f0c1e4cea085826cf90ea40944ca7892
import ForumHeader from '../components/ForumHeader.vue';
import { isLoggedIn, checkAuth } from '@/api/auth';
import axios from 'axios';

const API = import.meta.env.VITE_API_BASE || 'http://localhost:8080';
const user = ref(null);

// Fetch user data if logged in
async function fetchUserData() {
  if (isLoggedIn.value) {
    try {
      const res = await axios.get(`${API}/api/me`, { withCredentials: true });
      if (res.data.ok && res.data.user) {
        user.value = res.data.user;
      }
    } catch (e) {
      console.error('Error fetching user data:', e);
      user.value = null;
    }
  } else {
    user.value = null;
  }
}

// Watch for auth state changes
watch(isLoggedIn, async () => {
  await fetchUserData();
});

onMounted(async () => {
  await checkAuth();
  await fetchUserData();
});
</script>

<template>
<<<<<<< HEAD
    <ForumHeader /> <!-- Display forum header -->
  <h1>HOME PAGE</h1>
    <router-link to="/login">login</router-link> | | |
    <router-link to="/register">register</router-link> | | |
    <router-link to="/create-post">create post</router-link> | | |
    <router-link to="/profile">My Profile</router-link>
    <router-view></router-view>

    <CreatePostButton/>
=======
    <ForumHeader />
    <div class="forum-home py-3">
        <div class="container-xl">
            <div class="row g-4">
                <!-- User Cards -->
                <div class="col-12 col-lg-3">
                    <!-- Logged In User Card -->
                    <div class="card border-0 shadow-sm rounded-2 mb-3 profile-card">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="avatar bg-secondary-subtle rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center">
                                <i class="pi pi-user text-secondary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fw-semibold text-dark">{{ user ? `${user.FirstName} ${user.LastName}` : 'Joe Hornet' }}</div>
                                    <span class="badge role-badge student">Student</span>
                                </div>
                                <div class="small text-secondary">Posts: <b>23</b> &nbsp; Likes: <b>235</b> &nbsp; Comments: <b>59</b></div>
                            </div>
                        </div>
                    </div>

                    <!-- Guest/Not Logged In User Card -->
                    <div class="card border-0 shadow-sm rounded-2 mb-3 profile-card guest-card">
                        <div class="card-body d-flex gap-3 align-items-start">
                            <div class="avatar-guest bg-secondary-subtle rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center">
                                <i class="pi pi-user text-secondary"></i>
                            </div>
                            <div class="flex-grow-1 guest-content">
                                <div class="fw-semibold text-dark mb-2">Guest</div>
                                <div class="welcome-text">
                                    <div class="mb-1">Welcome!</div>
                                    <div class="small">Here, you will find your activity report, once you <RouterLink to="/login" class="sign-in-link">sign in!</RouterLink></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
>>>>>>> 63b020a2f0c1e4cea085826cf90ea40944ca7892
</template>

<style scoped>
.forum-home { background: #F0F2F3; }

.profile-card .avatar { width: 56px; height: 56px; }
.profile-card .avatar i { font-size: 1.5rem; }

.guest-card .avatar-guest { width: 64px; height: 64px; }
.guest-card .avatar-guest i { font-size: 2rem; }

.guest-content {
  display: flex;
  flex-direction: column;
}

.welcome-text {
  color: #374151;
  font-size: 0.95rem;
  line-height: 1.5;
}

.sign-in-link {
  color: #2E6C44;
  font-weight: 700;
  text-decoration: none;
}

.sign-in-link:hover {
  text-decoration: underline;
}

/* Role badges */
.role-badge { border-radius: 12px; padding: 2px 8px; font-weight:700; font-size: .70rem; }
.role-badge.student { background:#E6F2FF; color:#1E6FDB; }
.role-badge.admin { background:#FFE8E8; color:#C03B3B; }
.role-badge.moderator { background:#FFF5DB; color:#A87400; }
.role-badge.user { background:#EDEDED; color:#555; }
.role-badge.teacher { background:#EAF8EE; color:#2E6C44; }
</style>