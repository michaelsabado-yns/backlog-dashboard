import { ref } from 'vue';

const STORAGE_KEY = 'backlog_notification_read_state';

/**
 * @typedef {Object} ReadStateEntry
 * @property {boolean} read
 * @property {string} readAt
 */

/**
 * @typedef {Record<string, ReadStateEntry>} ReadStateMap
 */

/**
 * @returns {ReadStateMap}
 */
function loadFromStorage() {
  if (typeof window === 'undefined') {
    return {};
  }

  try {
    const raw = localStorage.getItem(STORAGE_KEY);

    if (!raw) {
      return {};
    }

    const parsed = JSON.parse(raw);

    return typeof parsed === 'object' && parsed !== null ? parsed : {};
  } catch {
    return {};
  }
}

/**
 * @param {ReadStateMap} state
 */
function persistToStorage(state) {
  if (typeof window === 'undefined') {
    return;
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

/** @type {import('vue').Ref<ReadStateMap>} */
const readState = ref(loadFromStorage());

/**
 * Frontend-only read/unread state backed by localStorage.
 * Swap the storage layer here to migrate to a backend later.
 */
export function useNotificationReadState() {
  /**
   * @returns {ReadStateMap}
   */
  function getReadState() {
    return { ...readState.value };
  }

  /**
   * @param {number|string} id
   * @returns {boolean}
   */
  function isRead(id) {
    return readState.value[String(id)]?.read === true;
  }

  /**
   * @param {number|string} id
   */
  function markAsRead(id) {
    readState.value = {
      ...readState.value,
      [String(id)]: {
        read: true,
        readAt: new Date().toISOString(),
      },
    };
    persistToStorage(readState.value);
  }

  /**
   * @param {Array<{ id: number|string }>} notifications
   */
  function markAllAsRead(notifications) {
    const readAt = new Date().toISOString();
    const updated = { ...readState.value };

    for (const notification of notifications) {
      updated[String(notification.id)] = {
        read: true,
        readAt,
      };
    }

    readState.value = updated;
    persistToStorage(readState.value);
  }

  /**
   * @param {Array<{ id: number|string }>} notifications
   * @returns {number}
   */
  function getUnreadCount(notifications) {
    return notifications.filter((notification) => !isRead(notification.id)).length;
  }

  /**
   * @param {Array<{ id: number|string }>} notifications
   * @returns {number}
   */
  function getReadCount(notifications) {
    return notifications.filter((notification) => isRead(notification.id)).length;
  }

  /**
   * @param {Array<{ id: number|string }>} notifications
   */
  function cleanupReadState(notifications) {
    const validIds = new Set(notifications.map((notification) => String(notification.id)));
    const cleaned = {};

    for (const [id, entry] of Object.entries(readState.value)) {
      if (validIds.has(id)) {
        cleaned[id] = entry;
      }
    }

    readState.value = cleaned;
    persistToStorage(readState.value);
  }

  return {
    readState,
    getReadState,
    isRead,
    markAsRead,
    markAllAsRead,
    getUnreadCount,
    getReadCount,
    cleanupReadState,
  };
}
