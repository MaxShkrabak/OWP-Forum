import { ref } from "vue";
import { checkAuth, logout } from "@/api/auth";
import { clearNotificationPreferences } from "@/utils/notificationPreferences";

const LEGACY_POST_COOLDOWN_KEY = "createPostBlockedUntil";

function getPostCooldownKey(userId = localStorage.getItem("uid")) {
  return userId ? `${LEGACY_POST_COOLDOWN_KEY}:${userId}` : null;
}

function clearStoredBlockedUntil(userId = localStorage.getItem("uid")) {
  const scopedKey = getPostCooldownKey(userId);
  if (scopedKey) {
    localStorage.removeItem(scopedKey);
  }
  localStorage.removeItem(LEGACY_POST_COOLDOWN_KEY);
}

function getStoredBlockedUntil() {
  const scopedKey = getPostCooldownKey();
  const raw = scopedKey
    ? localStorage.getItem(scopedKey) ?? localStorage.getItem(LEGACY_POST_COOLDOWN_KEY)
    : null;
  const stored = Number(raw ?? 0);

  if (!Number.isFinite(stored) || stored <= Date.now()) {
    clearStoredBlockedUntil();
    return 0;
  }

  if (scopedKey && localStorage.getItem(LEGACY_POST_COOLDOWN_KEY) != null) {
    localStorage.setItem(scopedKey, String(stored));
    localStorage.removeItem(LEGACY_POST_COOLDOWN_KEY);
  }

  return stored;
}

export const createPostBlockedUntil = ref(getStoredBlockedUntil());

export function blockPostCreationFor(seconds) {
  const safeSeconds = Math.max(0, Number(seconds) || 0);
  const blockedUntil = safeSeconds > 0 ? Date.now() + safeSeconds * 1000 : 0;
  createPostBlockedUntil.value = blockedUntil;
  const scopedKey = getPostCooldownKey();

  if (blockedUntil > Date.now() && scopedKey) {
    localStorage.setItem(scopedKey, String(blockedUntil));
    localStorage.removeItem(LEGACY_POST_COOLDOWN_KEY);
  } else {
    clearStoredBlockedUntil();
  }
}

const allImages = import.meta.glob(
  "/src/assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)",
  { eager: true },
);

const resolveAvatarPath = (filename) => {
  if (!filename) return null;
  const match = Object.keys(allImages).find((path) => path.endsWith(filename));
  return match ? allImages[match].default : null;
};

const defaultAvatar = resolveAvatarPath("default-pfp.png") || "";

export const isLoggedIn = ref(false);
export const uid = ref(localStorage.getItem("uid") || 0);
export const fullName = ref(localStorage.getItem("fullName") || "");
export const userAvatar = ref(
  localStorage.getItem("userAvatar") || defaultAvatar,
);
export const userRole = ref(localStorage.getItem("userRole") || "Guest");
export const userRoleId = ref(localStorage.getItem("userRoleId") || 0);
export const isBanned = ref(localStorage.getItem("isBanned") === "true");
export const banType = ref(localStorage.getItem("banType") || null);
export const bannedUntil = ref(localStorage.getItem("bannedUntil") || null);
export const termsAccepted = ref(false);

export const syncProfileOnLoad = async () => {
  try {
    const data = await checkAuth();

    if (data?.ok && data?.user) {
      const { user } = data;
      isLoggedIn.value = true;

      uid.value = user.userId;
      fullName.value = `${user.firstName} ${user.lastName}`;
      userRole.value = user.roleName || "User";
      userRoleId.value = user.roleId;
      isBanned.value = Boolean(Number(user.isBanned ?? 0));
      banType.value =
        user.banType &&
        (user.banType === "permanent" || user.banType === "temporary")
          ? user.banType
          : null;
      bannedUntil.value = user.bannedUntil ? String(user.bannedUntil) : null;
      termsAccepted.value = Number(user.termsAccepted) === 1;

      const avatarPath = resolveAvatarPath(user.avatar);
      userAvatar.value = avatarPath || defaultAvatar;

      localStorage.setItem("uid", uid.value);
      localStorage.setItem("fullName", fullName.value);
      localStorage.setItem("userRole", userRole.value);
      localStorage.setItem("userAvatar", userAvatar.value);
      localStorage.setItem("userRoleId", userRoleId.value);
      localStorage.setItem("isBanned", isBanned.value ? "true" : "false");
      localStorage.setItem("banType", banType.value || "");
      localStorage.setItem("bannedUntil", bannedUntil.value || "");
      createPostBlockedUntil.value = getStoredBlockedUntil();
    } else {
      resetStore();
    }
  } catch (error) {
    if (error.response?.status !== 401) {
      console.error("Profile sync failed:", error);
    }
    resetStore();
  }
};

const resetStore = () => {
  const currentUserId = uid.value || localStorage.getItem("uid");

  isLoggedIn.value = false;
  fullName.value = "";
  userRole.value = "Guest";
  userAvatar.value = defaultAvatar;
  uid.value = 0;
  userRoleId.value = 0;
  isBanned.value = false;
  banType.value = null;
  bannedUntil.value = null;
  termsAccepted.value = false;
  createPostBlockedUntil.value = 0;

  clearStoredBlockedUntil(currentUserId);
  clearNotificationPreferences(currentUserId);

  localStorage.removeItem("fullName");
  localStorage.removeItem("userRole");
  localStorage.removeItem("userAvatar");
  localStorage.removeItem("uid");
  localStorage.removeItem("userRoleId");
  localStorage.removeItem("isBanned");
  localStorage.removeItem("banType");
  localStorage.removeItem("bannedUntil");
};

export async function logoutUser() {
  try {
    await logout();
  } catch (e) {
    console.error("Logout API error:", e);
  } finally {
    resetStore();
  }
}

export const profileLoaded = syncProfileOnLoad(); // run when app starts
