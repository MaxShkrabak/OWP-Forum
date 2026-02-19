import { ref } from 'vue';
import { checkAuth, logout } from '@/api/auth';

// Import all images to get default avatar
const allImages = import.meta.glob('/src/assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)', { eager: true });

const resolveAvatarPath = (filename) => {
  if (!filename) return null;
  const match = Object.keys(allImages).find(path => path.endsWith(filename));
  return match ? allImages[match].default : null;
};

const defaultAvatar = resolveAvatarPath('default-pfp.png') || '';

export const isLoggedIn = ref(false);
export const uid = ref(localStorage.getItem('uid') || 0);
export const fullName = ref(localStorage.getItem('fullName') || '');
export const userAvatar = ref(localStorage.getItem('userAvatar') || defaultAvatar);
export const userRole = ref(localStorage.getItem('userRole') || 'Guest');
export const userRoleId = ref(localStorage.getItem('userRoleId') || 0);
export const isBanned = ref(localStorage.getItem('isBanned') === 'true');
export const banType = ref(localStorage.getItem('banType') || null); // 'permanent' | 'temporary'
export const bannedUntil = ref(localStorage.getItem('bannedUntil') || null); // ISO date string

export const syncProfileOnLoad = async () => {
  try {
    const data = await checkAuth();

    if (data?.ok && data?.user) {
      const { user } = data;
      isLoggedIn.value = true;

      uid.value = user.User_ID;
      fullName.value = `${user.FirstName} ${user.LastName}`;
      userRole.value = user.RoleName || 'User';
      userRoleId.value = user.RoleID;
      isBanned.value = Boolean(Number(user.IsBanned ?? 0));
      banType.value = user.BanType && (user.BanType === 'permanent' || user.BanType === 'temporary') ? user.BanType : null;
      bannedUntil.value = user.BannedUntil ? String(user.BannedUntil) : null;

      // User avatar
      const avatarPath = resolveAvatarPath(user.Avatar);
      userAvatar.value = avatarPath || defaultAvatar;

      localStorage.setItem('uid', uid.value);
      localStorage.setItem('fullName', fullName.value);
      localStorage.setItem('userRole', userRole.value);
      localStorage.setItem('userAvatar', userAvatar.value);
      localStorage.setItem('userRoleId', userRoleId.value);
      localStorage.setItem('isBanned', isBanned.value ? 'true' : 'false');
      localStorage.setItem('banType', banType.value || '');
      localStorage.setItem('bannedUntil', bannedUntil.value || '');
    } else {
      // User isn't signed in
      resetStore();
    }
  } catch (error) {
    console.error('Profile sync failed:', error);
    resetStore();
  }
};

const resetStore = () => {
  isLoggedIn.value = false;
  fullName.value = '';
  userRole.value = 'Guest';
  userAvatar.value = defaultAvatar;
  uid.value = 0;
  userRoleId.value = 0;
  isBanned.value = false;
  banType.value = null;
  bannedUntil.value = null;

  localStorage.removeItem('fullName');
  localStorage.removeItem('userRole');
  localStorage.removeItem('userAvatar');
  localStorage.removeItem('uid');
  localStorage.removeItem('userRoleId');
  localStorage.removeItem('isBanned');
  localStorage.removeItem('banType');
  localStorage.removeItem('bannedUntil');
};

export async function logoutUser() {
  try {
    await logout();
  } catch (e) {
    console.error('Logout API error:', e);
  } finally {
    resetStore();
  }
}

syncProfileOnLoad(); // run when app starts