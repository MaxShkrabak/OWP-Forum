<script setup>
import UserIcon from "@/assets/img/user-pfps-premade/pfp-0.png";
import { isLoggedIn, fullName, userAvatar, userRole} from "@/stores/userStore";
import UserRole from "@/components/user/UserRole.vue";
</script>

<template>
  <!-- Signed in User Card -->
  <div class="user-card-wrapper my-3">
    <div class="user-main-card shadow-sm border" v-if="isLoggedIn">
      <div class="card-header-gradient"></div>
      <div class="card-body-content px-3 pb-3">
        <div class="profile-section text-center">
          <RouterLink to="/profile" class="pfp-wrapper shadow-sm">
            <img :src="userAvatar" alt="avatar" class="profile-img" />
          </RouterLink>
          <h5 class="user-name mt-2 mb-1">{{ fullName }}</h5>
          <UserRole :role="userRole" />
        </div>
        <!-- User Stats Section -->
        <div class="stats-divider my-3"></div>
        <div class="stats-container d-flex justify-content-around text-center">
          <!-- Posts count-->
          <div class="stat-item">
            <span class="stat-value">0</span>
            <span class="stat-label text-uppercase">Posts</span>
          </div>
          <!-- Likes count -->
          <div class="stat-item">
            <span class="stat-value">0</span>
            <span class="stat-label text-uppercase">Likes</span>
          </div>
          <!-- Comment count -->
          <div class="stat-item">
            <span class="stat-value">0</span>
            <span class="stat-label text-uppercase">Comments</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Signed Out User Card -->
    <div class="user-main-card shadow-sm border" v-else>
      <div class="card-body-content p-3">
        <div class="row align-items-center">
          <div class="col-5 text-center">
            <div class="guest-pfp shadow-sm mx-auto">
              <img :src="UserIcon" alt="guest" class="profile-img" />
            </div>
            <div class="mt-2">
              <UserRole role="Guest" />
            </div>
          </div>
          
          <div class="col-7">
            <h5 class="mb-1">Welcome!</h5>
            <p class="welcome-msg mb-0">
              Here you'll find your activity report once you 
              <RouterLink to="/login" class="fw-bold text-success text-decoration-none">sign in</RouterLink>!
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-main-card {
  background-color: white;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
}

.card-header-gradient {
  height: 60px;
  background: linear-gradient(135deg, #004b33 0%, #003d4c 100%);
}

.profile-section {
  margin-top: -38px;
}

.pfp-wrapper, .guest-pfp {
  display: inline-block;
  width: 75px;
  height: 75px;
  border-radius: 50%;
  padding: 3px;
  background: white;
  transition: all 0.3s ease;
  cursor: pointer;
  overflow: hidden;
  border: 2px solid #7e9291;
}
.pfp-wrapper:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
  border-color: #ff8f1c;
}
.pfp-wrapper:hover .profile-img {
  transform: scale(1.1);
}

.profile-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  transition: transform 0.3s ease;
}

.user-name {
  font-family: 'Roboto', sans-serif;
  font-weight: 700;
  color: #1a1a1b;
  font-size: 1.1rem;
}

.stats-divider {
  height: 1px;
  background-color: #004b3379;
}

.stats-container {
  background-color: #007a4b17;
  border-radius: 8px;
  padding: 10px 0;
}
.stat-value {
  font-weight: 800;
  font-size: 1rem;
  color: #004b33;
}

.stat-label {
  font-size: 0.6rem;
  font-weight: 700;
  color: #7e9291;
  letter-spacing: 0.4px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  flex: 1 1 0;
}

.welcome-msg {
  font-size: 0.85rem;
  line-height: 1.4;
}

.guest-pfp {
  cursor: default;
}
</style>