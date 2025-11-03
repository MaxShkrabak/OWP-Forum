    import { createRouter, createWebHistory } from 'vue-router';
    import HomePage from '../views/ForumHome.vue';
    import LoginPage from '../views/Login.vue';
    import RegistrationPage from '../views/Register.vue';
    import VerifyPasscode from '../views/VerifyPasscode.vue';
    import ForumUserProfile from '../views/UserProfile.vue';
    import CreatePost from '../views/CreatePost.vue';

    const routes = [
      { path: '/', name: 'ForumHome', component: HomePage },
      { path: '/login', name: 'Log in', component: LoginPage },

      { path: '/register', name: 'Register', component: RegistrationPage },
      { path: '/verify', name: 'VerifyPasscode', component: VerifyPasscode, props: (route) => ({ email: route.query.email || '' }) },
      { path: '/profile', name: 'User Profile', component: ForumUserProfile },
      { path: '/create-post', name: 'CreatePost', component: CreatePost, meta: { requiresAuth: false } }, // set to true when login is wired
    ];

    const router = createRouter({
      history: createWebHistory(),
      routes,
    });

    export default router; 