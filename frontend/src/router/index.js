    import { createRouter, createWebHistory } from 'vue-router';
    import HomePage from '../views/ForumHome.vue';
    import LoginPage from '../views/Login.vue';
    import RegistrationPage from '../views/Register.vue';
    import VerifyPasscode from '../views/VerifyPasscode.vue';
    import ForumUserProfile from '../views/UserProfile.vue';
    import axios from 'axios';

    const routes = [
      { path: '/', name: 'ForumHome', component: HomePage },
      { path: '/login', name: 'Log in', component: LoginPage },

      { path: '/register', name: 'Register', component: RegistrationPage },
      { path: '/verify', name: 'VerifyPasscode', component: VerifyPasscode, props: (route) => ({ email: route.query.email || '' }) },
      { path: '/profile', name: 'User Profile', component: ForumUserProfile, meta: { requiresAuth: true } },
    ];

    const router = createRouter({
      history: createWebHistory(),
      routes,
    });

    // Route guard
    router.beforeEach(async (to, from, next) => {
      if (to.meta.requiresAuth) {
        // Authentication is required for the page
        try {
          const res = await axios.get(`${API}/api/me`, { withCredentials: true });
          if (res.data.ok) {
            next(); // user is logged in, route to page
          } else {
            next({ name: '/', query: { redirect: to.fullPath } }); // user is NOT logged in, route to home
          }
        } catch (e) {
          // Backend is down or some other issue, redirect to home
          next({ name: '/', query: { redirect: to.fullPath } });
        }
      } else {
        next(); // no auth is needed for that page
      }
    });

    export default router; 