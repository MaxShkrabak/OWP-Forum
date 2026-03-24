import { createRouter, createWebHistory } from "vue-router";
import HomePage from "../views/forum/ForumHome.vue";
import LoginPage from "../views/auth/Login.vue";
import RegistrationPage from "../views/auth/Register.vue";
import VerifyPasscode from "../views/auth/VerifyPasscode.vue";
import ForumUserProfile from "../views/forum/UserProfile.vue";
import CreatePost from "../components/forum/CreatePostModal.vue";
import CategoryPost from "@/views/forum/CategoryPosts.vue";
import ViewPost from "@/views/forum/ViewPost.vue";
import AdminPanel from "@/views/admin/AdminPanel.vue";
import TermsPage from "../views/legal/Terms.vue";
import PrivacyPage from "../views/legal/Privacy.vue";
import client from "@/api/client";

const routes = [
  { path: "/", name: "ForumHome", component: HomePage },
  { path: "/login", name: "Log in", component: LoginPage },
  { path: "/register", name: "Register", component: RegistrationPage },
  {
    path: "/verify",
    name: "VerifyPasscode",
    component: VerifyPasscode,
    props: (route) => ({ email: route.query.email || "" }),
  },

  { path: "/terms", name: "Terms", component: TermsPage, meta: { hideTermsModal: true } },
  { path: "/privacy", name: "Privacy", component: PrivacyPage, meta: { hideTermsModal: true } },

  { path: "/create-post", name: "CreatePost", component: CreatePost, meta: { requiresAuth: true } },

  { path: "/profile", name: "User Profile", component: ForumUserProfile },
  { path: "/categories/:categoryId/:slug?", name: "CategoryPosts", component: CategoryPost },
  { path: "/posts/:id", name: "ViewPost", component: ViewPost, props: true },

  { path: "/admin", name: "AdminPanel", component: AdminPanel, meta: { requiresAdmin: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Route guard
router.beforeEach(async (to, from, next) => {
  // For any page that requires user to be signed in.
  if (to.meta.requiresAuth) {
    try {
      const res = await client.get("/me");
      if (res.data.ok) {
        next();
        return;
      }
      next("/login");
    } catch (e) {
      next("/login");
    }
    return;
  }

  // Admin privileges are required for the page
  if (to.meta.requiresAdmin) {
    try {
      const res = await client.get("/me");
      if (res.data.ok && res.data.user?.RoleName === "admin") {
        next();
        return;
      }
      if (res.data.ok) {
        next("/");
        return;
      }
      next("/login");
    } catch (e) {
      next("/");
    }
    return;
  }

  next();
});

export default router;