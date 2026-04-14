<script setup>
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { isLoggedIn, fullName, userRole, logoutUser } from '@/stores/userStore';

import owpLogo from '@/assets/img/svg/owp-logo-horizontal-WHT-2color.svg';
import owpSymbol from '@/assets/img/svg/owp-symbol-2color.svg';
import cart from '@/assets/img/svg/cart.svg';

const route = useRoute();
const router = useRouter();

const width = ref(window.innerWidth);

const onLoginPage = computed(() => route.path.startsWith('/login'));
const onRegisterPage = computed(() => route.path.startsWith('/register'));
const logoType = computed(() => (width.value <= 584 ? owpSymbol : owpLogo));
const fname = computed(() => fullName.value.split(' ')[0] || '');

function handleResize() {
  width.value = window.innerWidth;
}

onMounted(async () => {
  window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
});

async function handleLogout() {
  await logoutUser();
  router.push('/');
}
</script>

<template>
  <header id="water-program">
    <ul>
      <!-- Menu Icon -->
      <li id="menu" @click="console.log('open menu')">
        <span class="menu-icon">☰</span>
        <span class="menu-text">Menu</span>
      </li>

      <!-- Logo -->
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

      <!-- Cart Icon -->
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
           <!-- Doesn't actually route anywhere, just to match UI -->
          <RouterLink to="" class="account-action">My Account</RouterLink>

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
#water-program { 
  background: #143f36; 
  color: #fff;
  padding: 1em;
  font-family: "Arial", "Helvetica", sans-serif; 
}

ul { 
  display: flex; 
  align-items: center; 
  list-style: none; 
  margin: 0; 
  padding: 0;
  gap: 8px;
}

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

#owp-logo img { 
  height: 50px; 
  width: auto; 
}

/* filler */
.fill { 
  flex: 1; 
}

#cart {
  margin-right: 16px;
  font-weight: 700;
}
.cart-icon { 
  width: 40px; 
  height: 40px;
}

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

.account-action{
  color: #fff;
  font-size: 14px;
  background: none;
  display: flex;
  text-decoration: none;
  padding: 0;
  border: none;
}

.greeting {
  color: #ccc;
  font-size: 14px;
  line-height: 1.2;
}
</style>