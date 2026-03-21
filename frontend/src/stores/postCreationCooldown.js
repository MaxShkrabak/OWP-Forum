import { ref } from 'vue';

export const createPostBlockedUntil = ref(0);

export function blockPostCreationFor(seconds) {
    createPostBlockedUntil.value = Date.now() + seconds * 1000;
}