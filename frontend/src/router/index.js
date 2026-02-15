    import { createRouter, createWebHistory } from 'vue-router';
    import HomePage from '../views/forum/ForumHome.vue';
    import LoginPage from '../views/auth/Login.vue';
    import RegistrationPage from '../views/auth/Register.vue';
    import VerifyPasscode from '../views/auth/VerifyPasscode.vue';
    import ForumUserProfile from '../views/forum/UserProfile.vue';
    import CreatePost from '../components/forum/CreatePostModal.vue';
    import CategoryPost from '@/views/forum/CategoryPosts.vue';
    import ViewPost from '@/views/forum/ViewPost.vue';
    import AdminUsersPage from '@/views/admin/AdminUsers.vue';
    import client from '@/api/client';

const routes = [
  { path: '/', name: 'ForumHome', component: HomePage },
  { path: '/login', name: 'Log in', component: LoginPage },
  { path: '/register', name: 'Register', component: RegistrationPage },
  { path: '/verify', name: 'VerifyPasscode', component: VerifyPasscode, props: (route) => ({ email: route.query.email || '' }) },
  { path: '/create-post', name: 'CreatePost', component: CreatePost, meta: { requiresAuth: true } },
  { path: '/profile', name: 'User Profile', component: ForumUserProfile, meta: { requiresAuth: true } },
  { path: '/categories/:categoryId/:slug?', name: 'CategoryPosts', component: CategoryPost },
  { path: '/posts/:id', name: 'ViewPost', component: ViewPost, props: true },
  { path: '/admin/users', name: 'AdminUsers', component: AdminUsersPage, meta: { requiresAuth: true, requiresAdmin: true } },

];

 
    const router = createRouter({
      history: createWebHistory(),
      routes,
    });

// Route guard
router.beforeEach(async (to, from, next) => {
  
  // 1. Admin Gate (Checks admin first)
  if (to.meta.requiresAdmin) {
    try {
      const adminRes = await client.get(`/admin/me`);
      if (adminRes.data?.ok) {
        return next(); // User is an admin, let them through
      }
    } catch (e) {
      // If the backend returns 403 Forbidden, they are logged in but lack privileges
      if (e.response && e.response.status === 403) {
        return next('/'); // Send unauthorized non-admins to the home page
      }
      // If it's a 401 or any other error, send them to login
      return next('/login'); 
    }
  }

  // 2. Standard Auth Gate (For non-admin protected routes)
  else if (to.meta.requiresAuth) {
    try {
      const res = await client.get(`/me`);
      if (res.data.ok) {
        return next(); // User is logged in, let them through
      }
    } catch (e) {
      // User isn't logged in, route to login page
      return next('/login');
    }
  } 

  // 3. Public Routes (No auth required)
  else {
    return next();
  }
});

    export default router; 