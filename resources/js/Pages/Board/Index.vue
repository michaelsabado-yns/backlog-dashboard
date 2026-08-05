<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
  isClosedStatusName,
  useBoardColumnVisibility,
} from '@/composables/useBoardColumnVisibility';
import {
  getSelectedProjectIds,
  reloadProjectSelectionFromStorage,
  useBacklogProjectSettings,
} from '@/composables/useBacklogProjectSettings';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

defineProps({
  has_api_key: {
    type: Boolean,
    required: true,
  },
});

const { isConfigured } = useBacklogProjectSettings();

const loading = ref(false);
const refreshing = ref(false);
const loadError = ref(null);
const fetchedAt = ref(null);
const fromCache = ref(false);
const myself = ref(null);
const columns = ref([]);
const issues = ref([]);
const scopedProjectIds = ref([]);
const searchQuery = ref('');

const {
  availableColumns,
  visibleColumnKeys,
  hiddenCount,
  visibleCount,
  isColumnVisible,
  toggleColumn,
  showAllColumns,
  hideClosedColumns,
} = useBoardColumnVisibility(columns);

const activeProjectIds = computed(() => getSelectedProjectIds());
const projectSelectionKey = computed(() =>
  activeProjectIds.value.slice().sort((a, b) => a - b).join(','),
);
const hasProjectSelection = computed(
  () => !isConfigured.value || activeProjectIds.value.length > 0,
);
const isBusy = computed(() => loading.value || refreshing.value);

const ROLE_LABELS = {
  assignee: 'Assignee',
  person_in_charge: 'PIC',
  sub_person_in_charge: 'Sub PIC',
  qa_in_charge: 'QA',
  sub_qa_in_charge: 'Sub QA',
  reviewer: 'Reviewer',
  sub_reviewer: 'Sub Reviewer',
};

const BOARD_LOCAL_CACHE_PREFIX = 'backlog_board_local_cache:';

const boardLocalCacheKey = (selectionKey) =>
  `${BOARD_LOCAL_CACHE_PREFIX}${selectionKey || 'all'}`;

const readBoardLocalCache = (selectionKey) => {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    const raw = localStorage.getItem(boardLocalCacheKey(selectionKey));

    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);

    if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.issues)) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
};

const writeBoardLocalCache = (selectionKey, payload) => {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    localStorage.setItem(
      boardLocalCacheKey(selectionKey),
      JSON.stringify({
        myself: payload.myself ?? null,
        columns: payload.columns ?? [],
        issues: payload.issues ?? [],
        scoped_project_ids: payload.scoped_project_ids ?? [],
        fetched_at: payload.fetched_at ?? null,
        saved_at: Date.now(),
      }),
    );
  } catch {
    // Ignore quota / private mode failures.
  }
};

const applyBoardPayload = (payload, { cached = false } = {}) => {
  myself.value = payload.myself ?? null;
  columns.value = Array.isArray(payload.columns) ? payload.columns : [];
  issues.value = Array.isArray(payload.issues) ? payload.issues : [];
  scopedProjectIds.value = Array.isArray(payload.scoped_project_ids)
    ? payload.scoped_project_ids
    : [];
  fetchedAt.value = payload.fetched_at ?? null;
  fromCache.value = cached || Boolean(payload.from_cache);
};

const issueCountsByColumn = computed(() => {
  const counts = new Map();

  for (const issue of issues.value) {
    const statusName = String(issue.status_name ?? '').trim() || 'Unknown';
    const key = statusName.toLowerCase();
    counts.set(key, (counts.get(key) ?? 0) + 1);
  }

  return counts;
});

const columnPickerItems = computed(() =>
  availableColumns.value.map((column) => ({
    ...column,
    count: issueCountsByColumn.value.get(column.key) ?? 0,
    visible: isColumnVisible(column.key),
  })),
);

const filteredIssues = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  const visibleKeys = new Set(visibleColumnKeys.value);

  return issues.value.filter((issue) => {
    const statusName = String(issue.status_name ?? '').trim() || 'Unknown';
    const statusKey = statusName.toLowerCase();

    if (!visibleKeys.has(statusKey)) {
      return false;
    }

    if (!query) {
      return true;
    }

    const haystack = [
      issue.issue_key,
      issue.summary,
      issue.project_key,
      issue.project_name,
      issue.assignee_name,
      ...(issue.roles ?? []).map((role) => ROLE_LABELS[role] ?? role),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return haystack.includes(query);
  });
});

const visibleColumns = computed(() => {
  const visibleKeys = new Set(visibleColumnKeys.value);
  const base = columns.value.filter((column) => visibleKeys.has(column.key));

  const issuesByStatus = new Map();

  for (const issue of filteredIssues.value) {
    const statusName = String(issue.status_name ?? '').trim() || 'Unknown';
    const key = statusName.toLowerCase();

    if (!issuesByStatus.has(key)) {
      issuesByStatus.set(key, []);
    }

    issuesByStatus.get(key).push(issue);
  }

  return base.map((column) => ({
    ...column,
    issues: issuesByStatus.get(column.key) ?? [],
  }));
});

const totalVisibleIssues = computed(() =>
  visibleColumns.value.reduce((sum, column) => sum + column.issues.length, 0),
);

const roleLabel = (role) => ROLE_LABELS[role] ?? role;

const statusAccent = (color, name) => {
  if (color && typeof color === 'string' && color.trim() !== '') {
    return color.startsWith('#') ? color : `#${color}`;
  }

  if (isClosedStatusName(name)) {
    return '#6b7280';
  }

  const lower = String(name ?? '').toLowerCase();

  if (/(progress|処理中|対応中|doing|active)/.test(lower)) {
    return '#2563eb';
  }

  if (/(open|未対応|未着手|new|todo)/.test(lower)) {
    return '#ea580c';
  }

  if (/(review|レビュー|pending|待機)/.test(lower)) {
    return '#7c3aed';
  }

  return '#4b5563';
};

const formatFetchedAt = (value) => {
  if (!value) {
    return null;
  }

  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const loadBoard = async ({ force = false } = {}) => {
  if (!hasProjectSelection.value) {
    columns.value = [];
    issues.value = [];
    scopedProjectIds.value = [];
    loadError.value = null;
    return;
  }

  const selectionKey = projectSelectionKey.value;
  const hasRows = issues.value.length > 0 || columns.value.length > 0;

  if (!force && !hasRows) {
    const localCache = readBoardLocalCache(selectionKey);

    if (localCache) {
      applyBoardPayload(localCache, { cached: true });
    }
  }

  const showingStale = issues.value.length > 0 || columns.value.length > 0;

  if (force || showingStale) {
    refreshing.value = true;
  } else {
    loading.value = true;
  }

  loadError.value = null;

  try {
    const response = await window.axios.get(route('board.issues'), {
      params: force ? { force: 1 } : {},
    });

    applyBoardPayload(response.data, { cached: Boolean(response.data.from_cache) });
    writeBoardLocalCache(selectionKey, response.data);
  } catch (error) {
    loadError.value =
      error?.response?.data?.message ?? 'Failed to load board from Backlog.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
};

const handleProjectSelectionStorage = (event) => {
  if (event.key !== 'backlog_selected_project_ids') {
    return;
  }

  reloadProjectSelectionFromStorage();
  loadBoard({ force: true });
};

onMounted(async () => {
  await loadBoard();
  window.addEventListener('storage', handleProjectSelectionStorage);
});

onUnmounted(() => {
  window.removeEventListener('storage', handleProjectSelectionStorage);
});

watch(projectSelectionKey, (nextKey, previousKey) => {
  if (previousKey === undefined || nextKey === previousKey) {
    return;
  }

  loadBoard({ force: true });
});
</script>

<template>
  <Head title="My Board" />

  <PublicLayout>
    <template #header>
      <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            My Board
          </h2>
          <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
            <span v-if="myself?.name">Tickets for {{ myself.name }}</span>
            <span v-else>Merged Kanban across selected projects</span>
          </p>
        </div>
        <p
          v-if="fetchedAt"
          class="text-xs text-gray-400 dark:text-gray-500"
        >
          Updated {{ formatFetchedAt(fetchedAt) }}
          <span v-if="fromCache"> · cached</span>
        </p>
      </div>
    </template>

    <div class="py-5">
      <div class="mx-auto max-w-[100vw] space-y-3 px-4 sm:px-6 lg:px-8">
        <div
          v-if="!has_api_key"
          class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10"
        >
          <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Enter your Backlog API key to get started
          </p>
        </div>

        <template v-else>
          <div
            class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10"
          >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                  <label
                    for="board-search"
                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                  >
                    Search
                  </label>
                  <input
                    id="board-search"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Issue key, summary, project, role…"
                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                  />
                </div>

                <div class="pb-0.5">
                  <p class="mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">
                    Columns
                  </p>
                  <Dropdown align="left" width="72" content-classes="py-0 bg-white dark:bg-gray-800">
                    <template #trigger>
                      <button
                        type="button"
                        class="inline-flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 sm:min-w-[11rem]"
                      >
                        <span>
                          {{ visibleCount }}/{{ availableColumns.length || 0 }} shown
                          <span v-if="hiddenCount" class="text-gray-400">
                            · {{ hiddenCount }} hidden
                          </span>
                        </span>
                        <svg
                          class="h-4 w-4 text-gray-400"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                          aria-hidden="true"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                            clip-rule="evenodd"
                          />
                        </svg>
                      </button>
                    </template>

                    <template #content>
                      <div class="border-b border-gray-100 px-3 py-2 dark:border-gray-700" @click.stop>
                        <div class="flex flex-wrap gap-2">
                          <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                            @click="showAllColumns"
                          >
                            Show all
                          </button>
                          <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="hideClosedColumns"
                          >
                            Hide closed
                          </button>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                          Saved in this browser
                        </p>
                      </div>

                      <div
                        class="max-h-72 overflow-y-auto py-1"
                        @click.stop
                      >
                        <p
                          v-if="columnPickerItems.length === 0"
                          class="px-3 py-3 text-xs text-gray-400 dark:text-gray-500"
                        >
                          Load the board to see columns
                        </p>
                        <label
                          v-for="column in columnPickerItems"
                          :key="column.key"
                          class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60"
                        >
                          <input
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                            :checked="column.visible"
                            @change="toggleColumn(column.key)"
                          />
                          <span class="min-w-0 flex-1 truncate">{{ column.name }}</span>
                          <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
                            {{ column.count }}
                          </span>
                        </label>
                      </div>
                    </template>
                  </Dropdown>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ totalVisibleIssues.toLocaleString() }} ticket(s)
                  <span v-if="scopedProjectIds.length">
                    · {{ scopedProjectIds.length.toLocaleString() }} project(s)
                  </span>
                </p>
                <SecondaryButton
                  :disabled="isBusy"
                  @click="loadBoard({ force: true })"
                >
                  Refresh
                </SecondaryButton>
              </div>
            </div>
          </div>

          <div
            v-if="!hasProjectSelection"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
          >
            No projects selected. Choose projects in Settings to populate the board.
          </div>

          <template v-else>
            <div
              v-if="loadError"
              class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
            >
              {{ loadError }}
              <span
                v-if="issues.length > 0 || columns.length > 0"
                class="text-red-600/80 dark:text-red-300/80"
              >
                Showing last loaded board.
              </span>
            </div>

            <div
              v-if="loading && issues.length === 0"
              class="rounded-lg border border-gray-200 bg-white px-6 py-16 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10"
            >
              <LoadingSpinner label="Loading your board…" />
            </div>

            <div
              v-else-if="!loading && !refreshing && issues.length === 0"
              class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
              <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
                No matching tickets
              </p>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Try another search, enable more columns, or refresh after updating Project settings.
              </p>
              <div class="mt-4">
                <PrimaryButton :disabled="isBusy" @click="loadBoard({ force: true })">
                  Refresh board
                </PrimaryButton>
              </div>
            </div>

            <div
              v-else-if="!loading && issues.length > 0 && totalVisibleIssues === 0"
              class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
              <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
                All tickets are in hidden columns
              </p>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Enable more columns from the Columns menu, or clear your search.
              </p>
            </div>

            <div
              v-else-if="issues.length > 0 || columns.length > 0 || refreshing"
              class="relative"
            >
            <div
              v-if="refreshing"
              class="absolute inset-x-0 top-0 z-10 flex justify-center"
            >
              <span
                class="inline-flex items-center gap-2 rounded-b-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white shadow"
              >
                <span
                  class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                  role="status"
                  aria-hidden="true"
                />
                Refreshing…
              </span>
            </div>

            <div class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
              <section
                v-for="column in visibleColumns"
                :key="column.key"
                class="flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40"
              >
                <header
                  class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-gray-700"
                  :style="{ borderTopColor: statusAccent(column.color, column.name), borderTopWidth: '3px' }"
                >
                  <h3 class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {{ column.name }}
                  </h3>
                  <span
                    class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-white px-1.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600"
                  >
                    {{ column.issues.length }}
                  </span>
                </header>

                <ul class="flex max-h-[calc(100vh-16rem)] flex-col gap-2 overflow-y-auto p-2">
                  <li
                    v-for="issue in column.issues"
                    :key="issue.issue_key"
                  >
                    <a
                      :href="issue.backlog_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="block rounded-md border border-gray-200 bg-white p-3 shadow-sm transition hover:border-indigo-300 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500"
                    >
                      <div class="flex items-start justify-between gap-2">
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                          {{ issue.issue_key }}
                        </span>
                        <span
                          v-if="issue.project_key"
                          class="truncate text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500"
                          :title="issue.project_name ?? issue.project_key"
                        >
                          {{ issue.project_key }}
                        </span>
                      </div>

                      <p class="mt-1 line-clamp-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ issue.summary }}
                      </p>

                      <div
                        v-if="issue.roles?.length"
                        class="mt-2 flex flex-wrap gap-1"
                      >
                        <span
                          v-for="role in issue.roles"
                          :key="`${issue.issue_key}-${role}`"
                          class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        >
                          {{ roleLabel(role) }}
                        </span>
                      </div>

                      <p
                        v-if="issue.assignee_name"
                        class="mt-2 truncate text-[11px] text-gray-500 dark:text-gray-400"
                      >
                        {{ issue.assignee_name }}
                      </p>
                    </a>
                  </li>

                  <li
                    v-if="column.issues.length === 0"
                    class="rounded-md border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500"
                  >
                    Empty
                  </li>
                </ul>
              </section>
            </div>
            </div>
          </template>
        </template>
      </div>
    </div>
  </PublicLayout>
</template>
