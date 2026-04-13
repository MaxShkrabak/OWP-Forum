import { ref } from 'vue';
import { getAdminRoles } from '@/api/admin';

export function useAdminRoles() {
  const roles = ref([]);

  async function loadRoles() {
    roles.value = await getAdminRoles();
  }

  function roleLabel(id) {
    return roles.value.find((r) => r.id === Number(id))?.label || '';
  }

  return { roles, loadRoles, roleLabel };
}
