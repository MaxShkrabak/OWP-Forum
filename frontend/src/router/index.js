    import { createRouter, createWebHistory } from 'vue-router';
    import HomePage from '../views/ForumHome.vue';
    import LoginPage from '../views/Login.vue';
    import RegistrationPage from '../views/Register.vue';
    import VerifyPasscode from '../views/VerifyPasscode.vue';
    import ForumUserProfile from '../views/UserProfile.vue';

    const routes = [
      { path: '/', name: 'Home', component: HomePage },
      { path: '/login', name: 'Log in', component: LoginPage },
      { path: '/register', name: 'Create Account', component: RegistrationPage },
      { path: '/verify', component: VerifyPasscode }, 
      { path: '/profile', name: 'User Profile', component: ForumUserProfile },
    ];

    const router = createRouter({
      history: createWebHistory(),
      routes,
    });

    export default router; 