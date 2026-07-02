import { computed, ref } from 'vue';

const STORAGE_KEY = 'backlog_selected_project_ids';

const selectedProjectIds = ref([]);
const initialized = ref(false);

const loadFromStorage = () => {
  if (initialized.value || typeof window === 'undefined') {
    return;
  }

  if (!localStorage.getItem(STORAGE_KEY)) {
    selectedProjectIds.value = [];
    initialized.value = true;
    return;
  }

  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];

    selectedProjectIds.value = Array.isArray(parsed)
      ? parsed.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
      : [];
  } catch (_error) {
    selectedProjectIds.value = [];
  }

  initialized.value = true;
};

const persist = () => {
  if (typeof window === 'undefined') {
    return;
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedProjectIds.value));
};

export function hasConfiguredProjectSelection() {
  if (typeof window === 'undefined') {
    return false;
  }

  return localStorage.getItem(STORAGE_KEY) !== null;
}

export function getSelectedProjectIds() {
  loadFromStorage();

  return [...selectedProjectIds.value];
}

export function reloadProjectSelectionFromStorage() {
  if (typeof window === 'undefined') {
    return getSelectedProjectIds();
  }

  initialized.value = false;
  loadFromStorage();

  return getSelectedProjectIds();
}

/**
 * @returns {string|null} Comma-separated IDs, empty string when configured-but-empty, or null when never configured.
 */
export function getSelectedProjectIdsHeader() {
  if (!hasConfiguredProjectSelection()) {
    return null;
  }

  return getSelectedProjectIds().join(',');
}

export function useBacklogProjectSettings() {
  loadFromStorage();

  const selectedIds = computed(() => selectedProjectIds.value);
  const hasSelection = computed(() => selectedProjectIds.value.length > 0);
  const isConfigured = computed(() => hasConfiguredProjectSelection());

  const setSelectedProjectIds = (ids) => {
    selectedProjectIds.value = [...new Set(ids.map((id) => Number(id)).filter((id) => id > 0))];
    persist();
  };

  const isProjectSelected = (projectId) => selectedProjectIds.value.includes(Number(projectId));

  const toggleProject = (projectId) => {
    const id = Number(projectId);

    if (selectedProjectIds.value.includes(id)) {
      selectedProjectIds.value = selectedProjectIds.value.filter((value) => value !== id);
    } else {
      selectedProjectIds.value = [...selectedProjectIds.value, id];
    }

    persist();
  };

  const selectAll = (projectIds) => {
    setSelectedProjectIds(projectIds);
  };

  const clearAll = () => {
    selectedProjectIds.value = [];
    persist();
  };

  const initializeDefaults = (projectIds) => {
    if (hasConfiguredProjectSelection()) {
      return;
    }

    const activeIds = projectIds
      .filter((project) => !project.archived)
      .map((project) => Number(project.id))
      .filter((id) => id > 0);

    setSelectedProjectIds(activeIds);
  };

  return {
    selectedIds,
    hasSelection,
    isConfigured,
    setSelectedProjectIds,
    isProjectSelected,
    toggleProject,
    selectAll,
    clearAll,
    initializeDefaults,
  };
}
