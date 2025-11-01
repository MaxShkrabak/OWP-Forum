<script setup>
import owpLogo from '@/assets/img/svg/owp-logo-horizontal-WHT-2color.svg';
import cart from '@/assets/img/svg/cart.svg';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { ref, computed, onMounted, watch } from 'vue';
import { isLoggedIn, checkAuth, logout } from "@/api/auth";

const route = useRoute();
const router = useRouter();

const onLoginPage = computed(() => route.path.startsWith('/login'));
const onRegisterPage = computed(() => route.path.startsWith('/register'));

// Ensures auth status is set correctly
onMounted(() => {
  checkAuth();
});

// Re-check auth when routing to different page
watch(route, () => {
  checkAuth();
});

// Function to log user out
async function handleLogout() {
  try {
    await logout();
    localStorage.clear();
    router.push('/login');
  } catch (e) {
    errorMsg.value = 'Something went wrong.';
  }
}
</script>

<template>
  <header id="water-program">
    <ul>
      <!-- menu icon -->
      <li id="menu" @click="console.log('open menu')">
        <span>☰ Menu</span>
      </li>

      <!-- logo -->
      <li id="owp-logo">
        <RouterLink to="/">
          <img 
            :src="owpLogo" 
            alt="water programs, sac state logo" 
            class="logo" 
          />
        </RouterLink>
      </li>

      <!-- filler -->
      <li class="fill"></li>

      <!-- cart icon -->
      <li id="cart">
        <img 
          :src="cart" 
          alt="cart" 
          class="cart-icon" 
        />
      </li>

      <!-- auth links -->
      <li id="userLogin" class="auth">
        <template v-if="isLoggedIn">
          <button class="logout" @click="handleLogout">Logout</button>
        </template>
        <template v-else>
          <RouterLink v-if="!onLoginPage" to="/login">Login</RouterLink>
          <RouterLink v-if="!onRegisterPage" to="/register">Create Account</RouterLink>
        </template>
      </li>
    </ul>
  </header>
</template>

<style scoped>
/* general header */
#water-program { 
  background: #143f36; 
  color: #fff; 
}

/* header layout */
ul { 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  list-style: none; 
  margin: 0; 
  padding: 0 20px; 
  height: 110px; 
}

/* menu */
#menu { 
  cursor: pointer; 
  font-family: "Arial", "Helvetica", sans-serif; 
  font-size: 18px; 
  font-weight: 700; 
  color: #fff; 
  display: flex; 
  align-items: center; 
  gap: 6px; 
}

/* logo */
#owp-logo { 
  margin-left: 30px; 
}

#owp-logo img.logo { 
  height: 55px; 
  width: auto; 
}

/* filler */
.fill { 
  flex: 1; 
}

/* cart */
#cart { 
  margin-right: 20px; 
}

.cart-icon { 
  width: 40px; 
  height: 50px; 
}

/* auth links */
.auth { 
  display: flex; 
  flex-direction: column; 
  justify-content: center; 
  align-items: flex-start; 
  gap: 4px; 
  min-height: 110px; 
  width: 120px; 
  white-space: nowrap; 
  font-family: "Arial", "Helvetica", sans-serif; 
  font-weight: 500; 
  font-size: 16px; 
  text-align: left; 
}

.auth a, 
.logout { 
  color: #fff; 
  text-decoration: underline; 
  line-height: 1.2; 
  background: none;
  border: none;
  padding: 0;
}
</style>
