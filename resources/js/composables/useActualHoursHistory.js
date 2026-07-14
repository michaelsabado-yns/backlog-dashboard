import { computed, ref } from 'vue';

const STORAGE_KEY = 'backlog_actual_hours_history';
const METADATA_KEY = 'backlog_actual_hours_metadata';
const RETENTION_DAYS = 90;

const historyState = ref({});
const metadataState = ref({});
const historyVersion = ref(0);
const initialized = ref(false);

const toNumber = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

const toLocalDateString = (date) => {
  if (!date) {
    return '';
  }

  if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    return date;
  }

  const parsed = new Date(date);

  if (Number.isNaN(parsed.getTime())) {
    return '';
  }

  const year = parsed.getFullYear();
  const month = String(parsed.getMonth() + 1).padStart(2, '0');
  const day = String(parsed.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

export const todayLocalDateString = () => toLocalDateString(new Date());

const bumpHistoryVersion = () => {
  historyVersion.value += 1;
};

const loadHistory = () => {
  if (initialized.value || typeof window === 'undefined') {
    return;
  }

  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    historyState.value = raw ? JSON.parse(raw) : {};
  } catch (_error) {
    historyState.value = {};
  }

  try {
    const rawMetadata = localStorage.getItem(METADATA_KEY);
    metadataState.value = rawMetadata ? JSON.parse(rawMetadata) : {};
  } catch (_error) {
    metadataState.value = {};
  }

  initialized.value = true;
  bumpHistoryVersion();
};

const persistHistory = () => {
  if (typeof window === 'undefined') {
    return;
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(historyState.value));
};

const persistMetadata = () => {
  if (typeof window === 'undefined') {
    return;
  }

  localStorage.setItem(METADATA_KEY, JSON.stringify(metadataState.value));
};

const normalizeIssueSnapshots = (issueKey) => {
  if (!Array.isArray(historyState.value[issueKey])) {
    historyState.value[issueKey] = [];
  }

  historyState.value[issueKey] = historyState.value[issueKey]
    .filter(
      (item) =>
        item &&
        typeof item.timestamp === 'string' &&
        Number.isFinite(Number(item.actualHours)),
    )
    .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());
};

const formatHours = (value) => `${toNumber(value).toFixed(2)}h`;

const computeDailySummary = (issueKey, date) => {
  const targetDate = toLocalDateString(date);
  const allSnapshots = getSnapshotsInternal(issueKey);
  const daySnapshots = allSnapshots.filter(
    (snapshot) => toLocalDateString(snapshot.timestamp) === targetDate,
  );

  if (daySnapshots.length === 0) {
    return null;
  }

  const firstOfDay = daySnapshots[0];
  const lastOfDay = daySnapshots[daySnapshots.length - 1];
  const priorSnapshots = allSnapshots.filter(
    (snapshot) => toLocalDateString(snapshot.timestamp) < targetDate,
  );
  const lastBeforeDay = priorSnapshots.at(-1);

  const previousHours =
    daySnapshots.length > 1
      ? toNumber(firstOfDay.actualHours)
      : lastBeforeDay
        ? toNumber(lastBeforeDay.actualHours)
        : toNumber(firstOfDay.actualHours);

  const currentHours = toNumber(lastOfDay.actualHours);
  const workedHours = currentHours - previousHours;
  const dayChanges = buildDayChanges(daySnapshots, lastBeforeDay);

  return {
    issueKey,
    previousHours,
    currentHours,
    workedHours,
    lastUpdatedAt: lastOfDay.timestamp,
    snapshotCount: daySnapshots.length,
    dayChanges,
  };
};

const buildDayChanges = (daySnapshots, lastBeforeDay) => {
  const changes = [];
  let previousHours = lastBeforeDay ? toNumber(lastBeforeDay.actualHours) : null;

  daySnapshots.forEach((snapshot) => {
    const currentHours = toNumber(snapshot.actualHours);

    if (previousHours === null) {
      previousHours = currentHours;
      return;
    }

    if (currentHours !== previousHours) {
      changes.push({
        before: previousHours,
        after: currentHours,
        timestamp: snapshot.timestamp,
        source: 'snapshot',
      });
      previousHours = currentHours;
    }
  });

  return changes;
};

const getSnapshotsInternal = (issueKey) => {
  const key = String(issueKey ?? '').trim();
  if (!key) {
    return [];
  }

  normalizeIssueSnapshots(key);

  return [...historyState.value[key]];
};

export function useActualHoursHistory() {
  loadHistory();

  const state = computed(() => {
    historyVersion.value;
    return historyState.value;
  });

  const saveIssueMetadata = (issueKey, summary = '', backlogUrl = null) => {
    const key = String(issueKey ?? '').trim();

    if (!key) {
      return;
    }

    metadataState.value[key] = {
      summary: String(summary ?? ''),
      backlogUrl: backlogUrl ? String(backlogUrl) : null,
    };

    persistMetadata();
  };

  const getIssueMetadata = (issueKey) => {
    const key = String(issueKey ?? '').trim();

    if (!key) {
      return { summary: '', backlogUrl: null };
    }

    const metadata = metadataState.value[key] ?? {};

    return {
      summary: String(metadata.summary ?? ''),
      backlogUrl: metadata.backlogUrl ?? null,
    };
  };

  const saveSnapshot = (issueKey, actualHours, summary = '', options = {}) => {
    const key = String(issueKey ?? '').trim();

    if (!key) {
      return false;
    }

    if (summary) {
      const existing = getIssueMetadata(key);
      saveIssueMetadata(key, summary, existing.backlogUrl);
    }

    normalizeIssueSnapshots(key);

    const snapshots = historyState.value[key];
    const current = toNumber(actualHours);
    const previous = snapshots.at(-1);
    const ensureDay = options.ensureDay ? toLocalDateString(options.ensureDay) : null;
    const hasSnapshotForDay =
      ensureDay !== null &&
      snapshots.some((snapshot) => toLocalDateString(snapshot.timestamp) === ensureDay);

    if (previous && toNumber(previous.actualHours) === current && hasSnapshotForDay) {
      return false;
    }

    snapshots.push({
      timestamp: options.timestamp ? new Date(options.timestamp).toISOString() : new Date().toISOString(),
      actualHours: current,
    });

    return true;
  };

  const finalizeSnapshots = () => {
    cleanupOldHistory();
    persistHistory();
    bumpHistoryVersion();
  };

  const getSnapshots = (issueKey) => {
    historyVersion.value;
    return getSnapshotsInternal(issueKey);
  };

  const calculateDailyHours = (issueKey, date) => {
    historyVersion.value;

    const summary = computeDailySummary(issueKey, date);

    if (!summary) {
      return {
        issueKey,
        previousHours: 0,
        currentHours: 0,
        workedHours: 0,
        lastUpdatedAt: null,
        snapshotCount: 0,
        dayChanges: [],
      };
    }

    return summary;
  };

  const getDailyTicketSummaries = (date, { onlyWithHours = false } = {}) => {
    historyVersion.value;

    return Object.keys(historyState.value)
      .map((issueKey) => {
        const summary = computeDailySummary(issueKey, date);

        if (!summary) {
          return null;
        }

        if (onlyWithHours && summary.workedHours <= 0) {
          return null;
        }

        const metadata = getIssueMetadata(issueKey);

        return {
          ...summary,
          summary: metadata.summary || issueKey,
          backlogUrl: metadata.backlogUrl,
        };
      })
      .filter((item) => item !== null)
      .sort((a, b) => b.workedHours - a.workedHours || a.issueKey.localeCompare(b.issueKey));
  };

  const getTotalHoursForDay = (date) =>
    getDailyTicketSummaries(date, { onlyWithHours: true }).reduce(
      (sum, item) => sum + toNumber(item.workedHours),
      0,
    );

  const getTrackedCountForDay = (date) => {
    historyVersion.value;
    const targetDate = toLocalDateString(date);

    return Object.keys(historyState.value).filter((issueKey) =>
      getSnapshotsInternal(issueKey).some(
        (snapshot) => toLocalDateString(snapshot.timestamp) === targetDate,
      ),
    ).length;
  };

  const cleanupOldHistory = () => {
    const cutoff = Date.now() - RETENTION_DAYS * 24 * 60 * 60 * 1000;
    const cleaned = {};

    Object.entries(historyState.value).forEach(([issueKey, snapshots]) => {
      const validSnapshots = Array.isArray(snapshots)
        ? snapshots
            .filter((snapshot) => new Date(snapshot.timestamp).getTime() >= cutoff)
            .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime())
        : [];

      if (validSnapshots.length > 0) {
        cleaned[issueKey] = validSnapshots;
      } else {
        delete metadataState.value[issueKey];
      }
    });

    historyState.value = cleaned;
    persistMetadata();
  };

  return {
    state,
    formatHours,
    saveSnapshot,
    finalizeSnapshots,
    saveIssueMetadata,
    getIssueMetadata,
    getSnapshots,
    calculateDailyHours,
    getDailyTicketSummaries,
    getTrackedCountForDay,
    getTotalHoursForDay,
    cleanupOldHistory,
    todayLocalDateString,
  };
}
