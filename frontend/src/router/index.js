    import { createRouter, createWebHistory } from 'vue-router';
    import HomePage from '../views/forum/ForumHome.vue';
    import LoginPage from '../views/auth/Login.vue';
    import RegistrationPage from '../views/auth/Register.vue';
    import VerifyPasscode from '../views/auth/VerifyPasscode.vue';
    import ForumUserProfile from '../views/forum/UserProfile.vue';
    import CreatePost from '../components/forum/CreatePostModal.vue';
    import CategoryPost from '@/views/forum/CategoryPosts.vue';
    import ViewPost from '@/views/forum/ViewPost.vue';
    import client from '@/api/client';

    const routes = [
      { path: '/', name: 'ForumHome', component: HomePage },
      { path: '/login', name: 'Log in', component: LoginPage },
      { path: '/register', name: 'Register', component: RegistrationPage },
      { path: '/verify', name: 'VerifyPasscode', component: VerifyPasscode, props: (route) => ({ email: route.query.email || '' }) },
      { path: '/create-post', name: 'CreatePost', component: CreatePost, meta: { requiresAuth: true } },
      { path: '/profile', name: 'User Profile', component: ForumUserProfile },
      { path: '/categories/:categoryId/:slug?', name: 'CategoryPosts', component: CategoryPost, },
      { path: '/posts/:id', name: 'ViewPost', component: ViewPost, props: true}
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
          const res = await client.get(`/me`);
          if (res.data.ok) {
            next(); // user is logged in, route to page
          }
        } catch (e) {
          // User isn't logged in or some other issue, route to login page
          next('/login');
        }
      } else {
        next(); // no auth is needed for that page
      }
    });

    export default router; 