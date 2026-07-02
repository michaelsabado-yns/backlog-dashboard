import { getSelectedProjectIds } from '@/composables/useBacklogProjectSettings';

const STORAGE_KEY = 'backlog_daily_hours_cache';
const HISTORY_STARTS_KEY = 'backlog_activity_range_starts_at_v2';
const LEGACY_HISTORY_STARTS_KEY = 'backlog_activity_history_starts_at';

export const getBrowserTimezone = () => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch (_error) {
    return 'UTC';
  }
};

const buildProjectKey = (projectIds) => {
  const ids = Array.isArray(projectIds) ? projectIds : getSelectedProjectIds();

  return [...ids].sort((a, b) => a - b).join(',');
};

const buildCacheKey = (date, projectIds, timezone) =>
  `${date}|${buildProjectKey(projectIds)}|${timezone || getBrowserTimezone()}`;

const readStore = () => {
  if (typeof window === 'undefined') {
    return {};
  }

  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : {};

    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch (_error) {
    return {};
  }
};

const writeStore = (store) => {
  if (typeof window === 'undefined') {
    return;
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
};

export function loadDailyHoursCache(date, projectIds = null, timezone = null) {
  const cacheKey = buildCacheKey(date, projectIds, timezone);
  const entry = readStore()[cacheKey];

  if (!entry || !Array.isArray(entry.items)) {
    return null;
  }

  return {
    items: entry.items,
    fetchedAt: entry.fetched_at ?? null,
    signature: entry.signature ?? null,
    fromCache: true,
  };
}

export function saveDailyHoursCache(date, projectIds, payload, timezone = null) {
  const cacheKey = buildCacheKey(date, projectIds, timezone);
  const store = readStore();

  store[cacheKey] = {
    items: Array.isArray(payload.items) ? payload.items : [],
    fetched_at: payload.fetched_at ?? null,
    signature: payload.signature ?? null,
  };

  writeStore(store);
}

export function getDailyHoursCacheSignature(date, projectIds = null, timezone = null) {
  return loadDailyHoursCache(date, projectIds, timezone)?.signature ?? null;
}

export function loadCachedHistoryStartsAt() {
  if (typeof window === 'undefined') {
    return null;
  }

  localStorage.removeItem(LEGACY_HISTORY_STARTS_KEY);

  const value = localStorage.getItem(HISTORY_STARTS_KEY);

  return value && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null;
}

export function saveCachedHistoryStartsAt(value) {
  if (typeof window === 'undefined' || !value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return;
  }

  localStorage.setItem(HISTORY_STARTS_KEY, value);
}
