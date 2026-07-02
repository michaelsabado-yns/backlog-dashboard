<script setup>
import NotificationFilters from '@/Components/Notifications/NotificationFilters.vue';
import NotificationStats from '@/Components/Notifications/NotificationStats.vue';
import NotificationTable from '@/Components/Notifications/NotificationTable.vue';
import {
  getSelectedProjectIds,
  hasConfiguredProjectSelection,
} from '@/composables/useBacklogProjectSettings';
import { useNotificationReadState } from '@/composables/useNotificationReadState';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * @typedef {import('@/types/notification').Notification} Notification
 */

/**
 * @typedef {Object} Props
 * @property {Notification[]} notifications
 * @property {number} total_count
 * @property {string} refreshed_at
 * @property {number} backlog_unread_count
 * @property {boolean} from_cache
 * @property {boolean} has_api_key
 */

/** @type {Props} */
const props = defineProps({
  notifications: {
    type: Array,
    required: true,
  },
  total_count: {
    type: Number,
    required: true,
  },
  refreshed_at: {
    type: String,
    default: null,
  },
  backlog_unread_count: {
    type: Number,
    required: true,
  },
  from_cache: {
    type: Boolean,
    required: true,
  },
  has_api_key: {
    type: Boolean,
    required: true,
  },
});

const {
  readState,
  isRead,
  markAllAsRead,
  getUnreadCount,
  getReadCount,
  cleanupReadState,
} = useNotificationReadState();

const search = ref('');
const selectedProject = ref('');
const selectedType = ref('');
const selectedReadStatus = ref('');
const refreshing = ref(false);

watch(
  () => props.notifications,
  (notifications) => {
    cleanupReadState(notifications);
  },
  { immediate: true },
);

const projectScopedNotifications = computed(() => {
  if (!hasConfiguredProjectSelection()) {
    return props.notifications;
  }

  const allowed = new Set(getSelectedProjectIds());

  if (allowed.size === 0) {
    return [];
  }

  return props.notifications.filter(
    (notification) =>
      notification.project_id !== null && allowed.has(notification.project_id),
  );
});

const projectOptions = computed(() =>
  [...new Set(projectScopedNotifications.value.map((notification) => notification.project))]
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b)),
);

const typeOptions = computed(() =>
  [...new Set(projectScopedNotifications.value.map((notification) => notification.type))]
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b)),
);

const notificationsWithReadState = computed(() => {
  readState.value;

  return projectScopedNotifications.value.map((notification) => ({
    ...notification,
    isRead: isRead(notification.id),
  }));
});

const notificationCounts = computed(() => {
  readState.value;

  return {
    total: projectScopedNotifications.value.length,
    unread: getUnreadCount(projectScopedNotifications.value),
    read: getReadCount(projectScopedNotifications.value),
  };
});

const filteredNotifications = computed(() => {
  const query = search.value.trim().toLowerCase();

  return notificationsWithReadState.value.filter((notification) => {
    if (
      selectedProject.value &&
      notification.project !== selectedProject.value
    ) {
      return false;
    }

    if (selectedType.value && notification.type !== selectedType.value) {
      return false;
    }

    if (selectedReadStatus.value === 'unread' && notification.isRead) {
      return false;
    }

    if (selectedReadStatus.value === 'read' && !notification.isRead) {
      return false;
    }

    if (!query) {
      return true;
    }

    const searchableText = [
      notification.issue_key,
      notification.summary,
      notification.sender,
      notification.content,
      notification.issue_status,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return searchableText.includes(query);
  });
});

const refreshNotifications = () => {
  if (!props.has_api_key) {
    return;
  }

  refreshing.value = true;

  router.get(
    route('notifications.index'),
    { force: 1 },
    {
      preserveState: true,
      preserveScroll: true,
      only: [
        'notifications',
        'total_count',
        'refreshed_at',
        'backlog_unread_count',
        'from_cache',
      ],
      onFinish: () => {
        refreshing.value = false;
      },
    },
  );
};

const handleMarkAllAsRead = () => {
  markAllAsRead(projectScopedNotifications.value);
};
</script>

<template>
  <Head title="Backlog Notification Dashboard" />

  <PublicLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Backlog Notification Dashboard
      </h2>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div
          v-if="!has_api_key"
          class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-sm"
        >
          <p class="text-lg font-medium text-gray-900">
            Enter your Backlog API key to get started
          </p>
          <p class="mt-2 text-sm text-gray-500">
            Use the API key field in the navbar above. Your key is stored in
            this browser only and is never saved on the server.
          </p>
        </div>

        <template v-else>
          <NotificationStats
            :total-count="notificationCounts.total"
            :unread-count="notificationCounts.unread"
            :read-count="notificationCounts.read"
            :refreshed-at="refreshed_at"
            :backlog-unread-count="backlog_unread_count"
            :from-cache="from_cache"
            :refreshing="refreshing"
            @refresh="refreshNotifications"
            @mark-all-read="handleMarkAllAsRead"
          />

          <NotificationFilters
            v-model:search="search"
            v-model:project="selectedProject"
            v-model:type="selectedType"
            v-model:read-status="selectedReadStatus"
            :project-options="projectOptions"
            :type-options="typeOptions"
          />

          <div class="flex items-center justify-between text-sm text-gray-500">
            <p>
              Showing
              <span class="font-medium text-gray-700">
                {{ filteredNotifications.length.toLocaleString() }}
              </span>
              of
              <span class="font-medium text-gray-700">
                {{ total_count.toLocaleString() }}
              </span>
              notifications
            </p>
          </div>

          <NotificationTable :notifications="filteredNotifications" />
        </template>
      </div>
    </div>
  </PublicLayout>
</template>
