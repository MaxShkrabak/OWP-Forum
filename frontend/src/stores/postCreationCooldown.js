import { ref } from 'vue';

const STORAGE_KEY = "createPostBlockedUntil";

function getStoredBlockedUntil() {
    const stored = Number(localStorage.getItem(STORAGE_KEY) ?? 0)

    if (!Number.isFinite(stored) || stored <= Date.now()) {
        localStorage.removeItem(STORAGE_KEY);
        return 0;
    }

    return stored;
}

export const createPostBlockedUntil = ref(getStoredBlockedUntil());

export function blockPostCreationFor(seconds) {
    const safeSeconds = Math.max(0, Number(seconds) || 0);
    const blockedUntil = safeSeconds > 0 ? Date.now() + safeSeconds * 1000 : 0;

    createPostBlockedUntil.value = blockedUntil;

    if (blockedUntil > Date.now()) {
        localStorage.setItem(STORAGE_KEY, String(blockedUntil));
    } else {
        localStorage.removeItem(STORAGE_KEY);
    }
}