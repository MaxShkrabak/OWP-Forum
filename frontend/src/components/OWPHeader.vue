<script setup>
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue';
import { isLoggedIn, checkAuth, logout, getName } from "@/api/auth";

// Image imports
import owpLogo from '@/assets/img/svg/owp-logo-horizontal-WHT-2color.svg';
import owpSymbol from '@/assets/img/svg/owp-symbol-2color.svg';
import cart from '@/assets/img/svg/cart.svg';

const route = useRoute();
const router = useRouter();

const width = ref(window.innerWidth);
const fullName = ref('');

const onLoginPage = computed(() => route.path.startsWith('/login'));
const onRegisterPage = computed(() => route.path.startsWith('/register'));
const logoType = computed(() => (width.value <= 584 ? owpSymbol : owpLogo));
const fname = computed(() => fullName.value.split(' ')[0] || ''); // First name only

function handleResize() {
  width.value = window.innerWidth;
}

// Helper function to store users name in cache
async function loadName() {
  if (!isLoggedIn.value) { return; }
  fullName.value = localStorage.getItem('fullName') || (await getName());
  localStorage.setItem('fullName', fullName.value);
}

// Ensures auth status is set correctly
onMounted(async () => {
  window.addEventListener('resize', handleResize);
  await checkAuth();
  await loadName();
  
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
});

// Re-check auth when routing to different page
watch(route, async () => {
  await checkAuth();
  await loadName();
});

// Function to log user out
async function handleLogout() {
  try {
    await logout();
    localStorage.removeItem('fullName');
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
        <span class="menu-icon">☰</span>
        <span class="menu-text">Menu</span>
      </li>

      <!-- logo -->
      <li id="owp-logo">
        <RouterLink to="/">
          <img 
            :src="logoType" 
            alt="water programs, sac state logo"  
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
          <span class="greeting">Hello, {{ fname }}!</span>
          <RouterLink to="" class="account-action">My Account</RouterLink> <!-- Doesn't actually route anywhere, just to match UI -->
          <button class="account-action" @click="handleLogout">Logout</button>
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
  padding: 1em;
  font-family: "Arial", "Helvetica", sans-serif; 
}

/* header layout */
ul { 
  display: flex; 
  align-items: center; 
  list-style: none; 
  margin: 0; 
  padding: 0;
  gap: 8px;
}

/* menu icon */
#menu .menu-icon {
  cursor: pointer;
  font-size: 14px;
  font-weight: 700;
  margin-right: 2px;
}
.menu-text {
  cursor: pointer;
  font-weight: 700;
  font-size: 14px;
}

/* OWP logo */
#owp-logo img { 
  height: 50px; 
  width: auto; 
}

/* filler */
.fill { 
  flex: 1; 
}

/* cart icon */
#cart {
  margin-right: 16px;
  font-weight: 700;
}
.cart-icon { 
  width: 40px; 
  height: 40px;
}

/* auth/login */
#userLogin a{
  display: flex;
  color: #fff;
  font-size: 14px;
  white-space: nowrap;
  flex-direction: column;
  padding-right: 14px;
  line-height: 1.2;
  font-family: 'Lato';
}

/* Account Buttons: My Account, Logout */
.account-action{
  color: #fff;
  font-size: 14px;
  background: none;
  display: flex;
  text-decoration: none;
  padding: 0;
  border: none;
}

/* Text displaying: Hello, {user}!*/
.greeting {
  color: #ccc;
  font-size: 14px;
  line-height: 1.2;
}
</style>