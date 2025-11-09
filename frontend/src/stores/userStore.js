import { ref, computed } from 'vue';
import { checkAuth, logout } from '@/api/auth';

// Import all images to get default avatar
const allImages = import.meta.glob('/src/assets/img/user-pfps-premade/*.(png|jpeg|jpg|svg)', { eager: true });
const images = computed(() => {
  return Object.values(allImages).map((module) => module.default);
});

const defaultAvatar = images.value[0] || '';

export const isLoggedIn = ref(false);
export const fullName= ref(localStorage.getItem('fullName' || ''));
export const userAvatar = ref(localStorage.getItem('userAvatar') || defaultAvatar);

export const syncProfileOnLoad = async () => {
  try {
    const data = await checkAuth();

    if (data && data.ok && data.user) {
      const user = data.user;
      isLoggedIn.value = true;
     
      // Users name
      const name = `${user.FirstName} ${user.LastName}`;
      fullName.value = name;
      localStorage.setItem('fullName', name);

      // User profile icon
      const dbAvatarFilename = user.Avatar;
      if (dbAvatarFilename) {
        const matchingPathKey = Object.keys(allImages).find(path => path.endsWith(dbAvatarFilename));
        if (matchingPathKey) {
          const fullImagePath = allImages[matchingPathKey].default;
          userAvatar.value = fullImagePath;
          localStorage.setItem('userAvatar', fullImagePath);
        } else {
          userAvatar.value = defaultAvatar;
          localStorage.setItem('userAvatar', defaultAvatar);
        }
      } else {
        userAvatar.value = defaultAvatar;
        localStorage.setItem('userAvatar', defaultAvatar);
      }

    } else {
      // User isn't signed in
      isLoggedIn.value = false;
      fullName.value = '';
      userAvatar.value = defaultAvatar;
      localStorage.clear();
    }
  } catch (error) {
    // Something went wrong
    isLoggedIn.value = false;
    console.error('Something went wrong with syncing the profile', error);
  }
};

// Logout helper
export async function logoutUser() {
    try {
        await logout();
    } catch (e) {
        console.error('Logout error', e)
    }

    isLoggedIn.value = false;
    fullName.value = '';
    userAvatar.value = defaultAvatar;

    localStorage.clear();
}

syncProfileOnLoad(); // run when app starts