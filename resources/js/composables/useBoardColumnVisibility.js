import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'backlog_board_hidden_columns';

/**
 * @typedef {Object} BoardColumnVisibilityState
 * @property {string[]} hiddenKeys
 * @property {string[]} knownKeys
 */

/**
 * @returns {BoardColumnVisibilityState}
 */
function emptyState() {
  return {
    hiddenKeys: [],
    knownKeys: [],
  };
}

/**
 * @returns {BoardColumnVisibilityState}
 */
function loadFromStorage() {
  if (typeof window === 'undefined') {
    return emptyState();
  }

  try {
    const raw = localStorage.getItem(STORAGE_KEY);

    if (!raw) {
      return emptyState();
    }

    const parsed = JSON.parse(raw);

    if (typeof parsed !== 'object' || parsed === null) {
      return emptyState();
    }

    const hiddenKeys = Array.isArray(parsed.hiddenKeys)
      ? parsed.hiddenKeys.map((key) => String(key).toLowerCase()).filter(Boolean)
      : [];
    const knownKeys = Array.isArray(parsed.knownKeys)
      ? parsed.knownKeys.map((key) => String(key).toLowerCase()).filter(Boolean)
      : [];

    return {
      hiddenKeys: [...new Set(hiddenKeys)],
      knownKeys: [...new Set(knownKeys)],
    };
  } catch {
    return emptyState();
  }
}

/**
 * @param {BoardColumnVisibilityState} state
 */
function persist(state) {
  if (typeof window === 'undefined') {
    return;
  }

  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      hiddenKeys: state.hiddenKeys,
      knownKeys: state.knownKeys,
    }),
  );
}

/**
 * @param {string|null|undefined} name
 */
export function isClosedStatusName(name) {
  const value = String(name ?? '').toLowerCase();

  return /(closed|完了|クローズ|done|resolved|解決)/.test(value);
}

/**
 * Persist which Kanban columns are hidden. New columns default to visible,
 * except closed-like statuses which default to hidden on first sight.
 *
 * @param {import('vue').Ref<Array<{ key: string, name: string }>>|import('vue').ComputedRef<Array<{ key: string, name: string }>>} columnsRef
 */
export function useBoardColumnVisibility(columnsRef) {
  const state = ref(loadFromStorage());
  const panelOpen = ref(false);

  const hiddenKeySet = computed(() => new Set(state.value.hiddenKeys));

  const availableColumns = computed(() =>
    (columnsRef.value ?? []).map((column) => ({
      key: String(column.key).toLowerCase(),
      name: column.name,
    })),
  );

  const visibleColumnKeys = computed(() =>
    availableColumns.value
      .map((column) => column.key)
      .filter((key) => !hiddenKeySet.value.has(key)),
  );

  const hiddenCount = computed(() =>
    availableColumns.value.filter((column) => hiddenKeySet.value.has(column.key)).length,
  );

  const visibleCount = computed(
    () => availableColumns.value.length - hiddenCount.value,
  );

  const isColumnVisible = (key) => !hiddenKeySet.value.has(String(key).toLowerCase());

  const syncNewColumns = () => {
    const nextHidden = new Set(state.value.hiddenKeys);
    const nextKnown = new Set(state.value.knownKeys);
    let changed = false;

    for (const column of availableColumns.value) {
      if (nextKnown.has(column.key)) {
        continue;
      }

      nextKnown.add(column.key);
      changed = true;

      if (isClosedStatusName(column.name)) {
        nextHidden.add(column.key);
      }
    }

    if (!changed) {
      return;
    }

    state.value = {
      hiddenKeys: [...nextHidden],
      knownKeys: [...nextKnown],
    };
    persist(state.value);
  };

  watch(
    availableColumns,
    () => {
      syncNewColumns();
    },
    { immediate: true, deep: true },
  );

  const setColumnVisible = (key, visible) => {
    const normalized = String(key).toLowerCase();
    const nextHidden = new Set(state.value.hiddenKeys);
    const nextKnown = new Set(state.value.knownKeys);

    nextKnown.add(normalized);

    if (visible) {
      nextHidden.delete(normalized);
    } else {
      nextHidden.add(normalized);
    }

    state.value = {
      hiddenKeys: [...nextHidden],
      knownKeys: [...nextKnown],
    };
    persist(state.value);
  };

  const toggleColumn = (key) => {
    setColumnVisible(key, !isColumnVisible(key));
  };

  const showAllColumns = () => {
    const nextKnown = new Set([
      ...state.value.knownKeys,
      ...availableColumns.value.map((column) => column.key),
    ]);

    state.value = {
      hiddenKeys: [],
      knownKeys: [...nextKnown],
    };
    persist(state.value);
  };

  const hideClosedColumns = () => {
    const nextHidden = new Set(state.value.hiddenKeys);
    const nextKnown = new Set(state.value.knownKeys);

    for (const column of availableColumns.value) {
      nextKnown.add(column.key);

      if (isClosedStatusName(column.name)) {
        nextHidden.add(column.key);
      }
    }

    state.value = {
      hiddenKeys: [...nextHidden],
      knownKeys: [...nextKnown],
    };
    persist(state.value);
  };

  const togglePanel = () => {
    panelOpen.value = !panelOpen.value;
  };

  const closePanel = () => {
    panelOpen.value = false;
  };

  return {
    panelOpen,
    availableColumns,
    visibleColumnKeys,
    hiddenCount,
    visibleCount,
    isColumnVisible,
    setColumnVisible,
    toggleColumn,
    showAllColumns,
    hideClosedColumns,
    togglePanel,
    closePanel,
  };
}
