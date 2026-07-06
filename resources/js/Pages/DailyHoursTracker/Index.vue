<script setup>
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import WeekdayDatePicker from '@/Components/WeekdayDatePicker.vue';
import { useActualHoursHistory } from '@/composables/useActualHoursHistory';
import {
  getDailyHoursCacheSignature,
  getBrowserTimezone,
  loadCachedHistoryStartsAt,
  loadDailyHoursCache,
  saveCachedHistoryStartsAt,
  saveDailyHoursCache,
} from '@/composables/useDailyHoursCache';
import {
  getSelectedProjectIds,
  reloadProjectSelectionFromStorage,
  useBacklogProjectSettings,
} from '@/composables/useBacklogProjectSettings';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import {
  clampToSelectableDate,
  firstWeekdayOnOrAfter,
} from '@/utils/weekdayDate';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

defineProps({
  has_api_key: {
    type: Boolean,
    required: true,
  },
});

const {
  saveSnapshot,
  saveIssueMetadata,
  finalizeSnapshots,
  cleanupOldHistory,
  todayLocalDateString,
} = useActualHoursHistory();

const { isConfigured } = useBacklogProjectSettings();

const selectedDate = ref(todayLocalDateString());
const loading = ref(false);
const refreshing = ref(false);
const fetchedAt = ref(null);
const fetchedTickets = ref([]);
const loadError = ref(null);
const fromCache = ref(false);
const changeCount = ref(0);
const signature = ref(null);
const scopedProjectIds = ref([]);
const historyStartsAt = ref(null);
const historyEndsAt = ref(null);
const earliestActivityAt = ref(null);
const beforeHistory = ref(false);
const dateReachable = ref(true);
const emptyReason = ref(null);
const boundsLoading = ref(false);
const boundsLoaded = ref(false);
const expandedTicketKey = ref(null);

const activeProjectIds = computed(() => getSelectedProjectIds());
const projectSelectionKey = computed(() => activeProjectIds.value.slice().sort((a, b) => a - b).join(','));
const hasProjectSelection = computed(() => !isConfigured.value || activeProjectIds.value.length > 0);
const scopedProjectCount = computed(() => scopedProjectIds.value.length);
const browserTimezone = getBrowserTimezone();
const maxSelectableDate = computed(() => todayLocalDateString());
const minSelectableDate = computed(() =>
  historyStartsAt.value ? firstWeekdayOnOrAfter(historyStartsAt.value) : null,
);
const historyRangeLabel = computed(() => {
  if (!boundsLoaded.value || !historyStartsAt.value) {
    return null;
  }

  const end = historyEndsAt.value ?? maxSelectableDate.value;

  return `${formatDisplayDate(historyStartsAt.value)} – ${formatDisplayDate(end)} (from day after earliest activity)`;
});

const isBusy = computed(() => loading.value || refreshing.value);

const displayTickets = computed(() =>
  [...fetchedTickets.value].sort(
    (a, b) =>
      (b.worked_hours ?? 0) - (a.worked_hours ?? 0) || a.issue_key.localeCompare(b.issue_key),
  ),
);

const totalHours = computed(() =>
  displayTickets.value.reduce((sum, ticket) => sum + Number(ticket.worked_hours ?? 0), 0),
);
const ticketCount = computed(() => displayTickets.value.length);

const formatDisplayDate = (value) => {
  if (!value) {
    return '—';
  }

  const date = new Date(`${value}T12:00:00`);

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
};

const formatHours = (hours) => `${Number(hours ?? 0).toFixed(1)}h`;

const formatFieldLabel = (change) => {
  if (change.field_kind === 'sub_actual_hours') {
    return change.field || 'Sub Actual Hours';
  }

  return 'Actual Hours';
};

const formatDateTime = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(isoString));
};

const toggleTicketExpanded = (issueKey) => {
  expandedTicketKey.value = expandedTicketKey.value === issueKey ? null : issueKey;
};

const formatFetchedAt = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(isoString));
};

const applyCachedDay = (date) => {
  const cached = loadDailyHoursCache(date, activeProjectIds.value, browserTimezone);

  if (!cached) {
    fetchedTickets.value = [];
    fetchedAt.value = null;
    fromCache.value = false;
    signature.value = null;
    return;
  }

  fetchedTickets.value = cached.items;
  fetchedAt.value = cached.fetchedAt;
  fromCache.value = true;
  signature.value = cached.signature;
};

const persistSnapshots = (items) => {
  items.forEach((item) => {
    saveIssueMetadata(item.issue_key, item.summary, item.backlog_url);

    const changes = Array.isArray(item.hour_changes) ? item.hour_changes : [];

    if (changes.length > 0) {
      changes.forEach((change) => {
        saveSnapshot(item.issue_key, change.after, item.summary, {
          ensureDay: selectedDate.value,
          timestamp: change.changed_at,
        });
      });
      return;
    }

    saveSnapshot(item.issue_key, item.actual_hours, item.summary, {
      ensureDay: selectedDate.value,
      timestamp: item.updated_at,
    });
  });

  finalizeSnapshots();
};

const snapshotMyIssues = async ({ force = false } = {}) => {
  if (!hasProjectSelection.value) {
    fetchedTickets.value = [];
    fetchedAt.value = null;
    fromCache.value = false;
    changeCount.value = 0;
    signature.value = null;
    scopedProjectIds.value = [];
    return;
  }

  loadError.value = null;

  const projectIds = activeProjectIds.value;
  const cachedSignature = force
    ? null
    : getDailyHoursCacheSignature(selectedDate.value, projectIds, browserTimezone);

  const response = await window.axios.get(route('daily-hours.my-issues'), {
    params: {
      date: selectedDate.value,
      timezone: browserTimezone,
      signature: cachedSignature ?? undefined,
      force: force ? 1 : undefined,
    },
  });

  const data = response?.data ?? {};
  let items = Array.isArray(data.items) ? data.items : [];

  if (data.from_cache && items.length === 0 && data.signature) {
    const localCache = loadDailyHoursCache(selectedDate.value, projectIds, browserTimezone);

    if (localCache?.signature === data.signature && localCache.items.length > 0) {
      items = localCache.items;
    }
  }

  fetchedTickets.value = items;
  fetchedAt.value = data.fetched_at ?? new Date().toISOString();
  fromCache.value = Boolean(data.from_cache);
  changeCount.value = Number(data.change_count ?? 0);
  signature.value = data.signature ?? null;
  scopedProjectIds.value = Array.isArray(data.scoped_project_ids) ? data.scoped_project_ids : [];
  beforeHistory.value = Boolean(data.before_history);
  dateReachable.value = data.date_reachable !== false;
  emptyReason.value = data.empty_reason ?? null;

  if (data.history_starts_at) {
    historyStartsAt.value = data.history_starts_at;
    saveCachedHistoryStartsAt(data.history_starts_at);
  }

  if (data.history_ends_at) {
    historyEndsAt.value = data.history_ends_at;
  }

  if (data.earliest_activity_at) {
    earliestActivityAt.value = data.earliest_activity_at;
  }

  saveDailyHoursCache(
    selectedDate.value,
    projectIds,
    {
      items,
      fetched_at: fetchedAt.value,
      signature: signature.value,
    },
    browserTimezone,
  );

  persistSnapshots(items);
};

const loadDateBounds = async () => {
  boundsLoading.value = true;

  try {
    const cachedStartsAt = loadCachedHistoryStartsAt();

    if (cachedStartsAt) {
      historyStartsAt.value = cachedStartsAt;
    }

    const response = await window.axios.get(route('daily-hours.date-bounds'), {
      params: { timezone: browserTimezone },
    });

    const startsAt = response?.data?.history_starts_at ?? null;
    const endsAt = response?.data?.history_ends_at ?? null;

    historyStartsAt.value = startsAt;
    historyEndsAt.value = endsAt;
    earliestActivityAt.value = response?.data?.earliest_activity_at ?? null;

    if (startsAt) {
      saveCachedHistoryStartsAt(startsAt);
    }

    boundsLoaded.value = true;

    const clamped = clampToSelectableDate(
      selectedDate.value,
      minSelectableDate.value,
      maxSelectableDate.value,
    );

    if (clamped !== selectedDate.value) {
      selectedDate.value = clamped;
    }
  } catch (_error) {
    boundsLoaded.value = historyStartsAt.value !== null;
  } finally {
    boundsLoading.value = false;
  }
};

const refreshBacklogData = async ({ force = false } = {}) => {
  if (isBusy.value) {
    return;
  }

  if (force) {
    refreshing.value = true;
  } else {
    loading.value = true;
  }

  try {
    await snapshotMyIssues({ force });
  } catch (error) {
    loadError.value =
      error?.response?.data?.message ?? 'Failed to refresh tickets from Backlog.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
};

onMounted(async () => {
  cleanupOldHistory();
  applyCachedDay(selectedDate.value);
  await loadDateBounds();
  await refreshBacklogData();

  window.addEventListener('storage', handleProjectSelectionStorage);
});

onUnmounted(() => {
  window.removeEventListener('storage', handleProjectSelectionStorage);
});

const handleProjectSelectionStorage = (event) => {
  if (event.key !== 'backlog_selected_project_ids') {
    return;
  }

  reloadProjectSelectionFromStorage();
  applyCachedDay(selectedDate.value);
  refreshBacklogData({ force: true });
};

watch(projectSelectionKey, (nextKey, previousKey) => {
  if (previousKey === undefined || nextKey === previousKey) {
    return;
  }

  applyCachedDay(selectedDate.value);
  refreshBacklogData({ force: true });
});

watch(historyStartsAt, (minDate) => {
  if (!minDate) {
    return;
  }

  const clamped = clampToSelectableDate(
    selectedDate.value,
    firstWeekdayOnOrAfter(minDate),
    maxSelectableDate.value,
  );

  if (clamped !== selectedDate.value) {
    selectedDate.value = clamped;
  }
});

watch(selectedDate, (date, previousDate) => {
  if (!date || date === previousDate) {
    return;
  }

  const clamped = clampToSelectableDate(
    date,
    minSelectableDate.value,
    maxSelectableDate.value,
  );

  if (clamped !== date) {
    selectedDate.value = clamped;
    return;
  }

  applyCachedDay(date);
  refreshBacklogData();
});
</script>

<template>
  <Head title="Daily Hours Tracker" />

  <PublicLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Daily Hours Tracker</h2>
    </template>

    <div class="py-5">
      <div class="mx-auto max-w-4xl space-y-3 px-4 sm:px-6 lg:px-8">
        <div
          v-if="!has_api_key"
          class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-sm"
        >
          <p class="text-lg font-medium text-gray-900">
            Enter your Backlog API key to get started
          </p>
        </div>

        <template v-else>
          <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0 flex-1 space-y-1">
                <WeekdayDatePicker
                  v-model="selectedDate"
                  :min-date="historyStartsAt"
                  :max-date="maxSelectableDate"
                  :disabled="isBusy || boundsLoading"
                />
                <p v-if="historyRangeLabel" class="text-[11px] text-gray-400">
                  {{ historyRangeLabel }}
                </p>
                <p v-else-if="boundsLoading" class="text-[11px] text-gray-400">
                  Loading activity range…
                </p>
              </div>

              <PrimaryButton
                type="button"
                class="shrink-0 justify-center"
                :disabled="isBusy"
                @click="refreshBacklogData({ force: true })"
              >
                <span v-if="refreshing">Refetching…</span>
                <span v-else-if="loading">Checking…</span>
                <span v-else>Refetch</span>
              </PrimaryButton>
            </div>

            <p class="mt-2 text-[11px] text-gray-400">
              <span v-if="!isConfigured">All joined projects</span>
              <span v-else-if="activeProjectIds.length === 0">No projects selected</span>
              <span v-else>
                {{ activeProjectIds.length.toLocaleString() }} enabled project(s)
                <span v-if="scopedProjectCount > 0">
                  · {{ scopedProjectCount.toLocaleString() }} in scope
                </span>
              </span>
              <span v-if="fetchedAt">
                · {{ formatFetchedAt(fetchedAt) }}
                <span v-if="fromCache">cached</span>
              </span>
            </p>
          </div>

          <div
            v-if="isConfigured && activeProjectIds.length === 0"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
          >
            No projects are enabled. Select projects in
            <a :href="route('project-settings.index')" class="font-medium underline">Project settings</a>.
          </div>

          <div
            v-if="loadError"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {{ loadError }}
          </div>

          <div v-if="isBusy && hasProjectSelection" class="rounded-lg border border-gray-200 bg-white px-4 py-12 shadow-sm">
            <LoadingSpinner
              :label="refreshing ? 'Refetching from Backlog…' : 'Loading daily hours…'"
            />
          </div>

          <template v-else-if="hasProjectSelection">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
              <div
                class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-3 py-3 sm:px-4"
              >
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm text-gray-600">
                  <span>
                    <span class="text-lg font-bold text-green-700">{{ formatHours(totalHours) }}</span>
                    total
                  </span>
                  <span>
                    <span class="font-semibold text-gray-900">{{ ticketCount.toLocaleString() }}</span>
                    {{ ticketCount === 1 ? 'ticket' : 'tickets' }}
                  </span>
                </div>
                <span class="text-xs text-gray-400">{{ formatDisplayDate(selectedDate) }}</span>
              </div>

              <div
                v-if="displayTickets.length === 0"
                class="px-4 py-12 text-center text-sm text-gray-500"
              >
                <p class="font-medium text-gray-900">No hour changes for this date.</p>
                <p
                  v-if="beforeHistory || emptyReason === 'before_history' || !dateReachable"
                  class="mt-2 text-amber-700"
                >
                  This date is before the selectable range. The earliest day in your latest 100
                  activities is
                  <template v-if="earliestActivityAt">{{ formatDisplayDate(earliestActivityAt) }}</template
                  ><template v-else-if="historyStartsAt">{{ formatDisplayDate(historyStartsAt) }}</template>,
                  so tracking starts from
                  <template v-if="historyStartsAt">{{ formatDisplayDate(historyStartsAt) }}</template>.
                </p>
                <p v-else-if="fetchedAt && !loadError" class="mt-2">
                  Backlog responded successfully. You had no actual-hours updates on this date in
                  your latest 100 activities.
                </p>
                <p v-else class="mt-2">
                  Select a date within your hour history range or refetch after logging hours in
                  Backlog.
                </p>
              </div>

              <ul v-else class="divide-y divide-gray-100">
                <li
                  v-for="ticket in displayTickets"
                  :key="ticket.issue_key"
                  class="px-3 py-3 sm:px-4"
                >
                  <article class="min-w-0">
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                          <a
                            v-if="ticket.backlog_url"
                            :href="ticket.backlog_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-semibold text-indigo-600 hover:underline"
                          >
                            {{ ticket.issue_key }}
                          </a>
                          <span v-else class="text-sm font-semibold text-gray-800">
                            {{ ticket.issue_key }}
                          </span>
                        </div>

                        <p class="mt-0.5 line-clamp-2 text-sm leading-snug text-gray-900">
                          {{ ticket.summary }}
                        </p>
                      </div>

                      <div class="shrink-0 text-right">
                        <p class="text-lg font-bold leading-none text-green-700">
                          +{{ formatHours(ticket.worked_hours) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-gray-400">worked</p>
                      </div>
                    </div>

                    <p class="mt-1.5 text-xs text-gray-500">
                      {{ formatHours(ticket.previous_hours) }}
                      <span class="text-gray-300">→</span>
                      {{ formatHours(ticket.current_hours) }}
                      <span class="mx-1 text-gray-300">·</span>
                      updated {{ formatDateTime(ticket.updated_at) }}
                    </p>

                    <template v-if="ticket.hour_changes?.length">
                      <ul
                        v-if="ticket.hour_changes.length === 1 || expandedTicketKey === ticket.issue_key"
                        class="mt-2 space-y-1"
                      >
                        <li
                          v-for="(change, index) in ticket.hour_changes"
                          :key="`${ticket.issue_key}-change-${index}`"
                          class="rounded border border-gray-100 bg-gray-50 px-2 py-1.5 text-xs text-gray-600"
                        >
                          <span class="font-medium text-gray-700">{{ formatFieldLabel(change) }}:</span>
                          {{ formatHours(change.before) }} → {{ formatHours(change.after) }}
                          <span class="text-gray-400">
                            · {{ change.changed_by }}
                            <span v-if="change.changed_at">· {{ formatDateTime(change.changed_at) }}</span>
                          </span>
                        </li>
                      </ul>

                      <button
                        v-else
                        type="button"
                        class="mt-1.5 text-[11px] font-medium text-gray-500 hover:text-gray-800"
                        @click="toggleTicketExpanded(ticket.issue_key)"
                      >
                        {{ ticket.hour_changes.length }} hour updates · Show
                      </button>

                      <button
                        v-if="ticket.hour_changes.length > 1 && expandedTicketKey === ticket.issue_key"
                        type="button"
                        class="mt-1 text-[11px] font-medium text-gray-500 hover:text-gray-800"
                        @click="toggleTicketExpanded(ticket.issue_key)"
                      >
                        Hide updates
                      </button>
                    </template>

                    <a
                      v-if="ticket.backlog_url"
                      :href="ticket.backlog_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline"
                    >
                      Open in Backlog
                    </a>
                  </article>
                </li>
              </ul>
            </div>
          </template>
        </template>
      </div>
    </div>
  </PublicLayout>
</template>
