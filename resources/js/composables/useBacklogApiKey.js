import { ref } from 'vue';

const STORAGE_KEY = 'backlog_api_key';
const COOKIE_NAME = 'backlog_api_key';

/** @type {import('vue').Ref<string>} */
const apiKey = ref('');

let initialized = false;

/**
 * @returns {string}
 */
export function getBacklogApiKey() {
  if (typeof window === 'undefined') {
    return '';
  }

  return localStorage.getItem(STORAGE_KEY)?.trim() ?? '';
}

/**
 * @param {string} key
 */
export function setBacklogApiKeyCookie(key) {
  if (typeof document === 'undefined') {
    return;
  }

  if (key) {
    document.cookie = `${COOKIE_NAME}=${encodeURIComponent(key)}; path=/; max-age=31536000; SameSite=Lax`;
  } else {
    document.cookie = `${COOKIE_NAME}=; path=/; max-age=0; SameSite=Lax`;
  }
}

export function syncBacklogApiKeyCookie() {
  setBacklogApiKeyCookie(getBacklogApiKey());
}

function loadFromStorage() {
  apiKey.value = getBacklogApiKey();
}

/**
 * @param {string} key
 */
export function persistBacklogApiKey(key) {
  const trimmed = key.trim();

  if (trimmed) {
    localStorage.setItem(STORAGE_KEY, trimmed);
  } else {
    localStorage.removeItem(STORAGE_KEY);
  }

  setBacklogApiKeyCookie(trimmed);
  apiKey.value = trimmed;
}

export function useBacklogApiKey() {
  if (!initialized && typeof window !== 'undefined') {
    loadFromStorage();
    syncBacklogApiKeyCookie();
    initialized = true;
  }

  /**
   * @param {string} key
   */
  function saveApiKey(key) {
    persistBacklogApiKey(key);
  }

  function clearApiKey() {
    persistBacklogApiKey('');
  }

  return {
    apiKey,
    saveApiKey,
    clearApiKey,
  };
}
