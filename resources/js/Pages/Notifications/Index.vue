<script setup>
import NotificationFilters from '@/Components/Notifications/NotificationFilters.vue';
import NotificationStats from '@/Components/Notifications/NotificationStats.vue';
import NotificationTable from '@/Components/Notifications/NotificationTable.vue';
import { useNotificationReadState } from '@/composables/useNotificationReadState';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * @typedef {import('@/types/notification').Notification} Notification
 */

/**
 * @typedef {Object} Props
 * @property {Notification[]} notifications
 * @property {number} total_count
 * @property {string} refreshed_at
 * @property {string} cache_expires_at
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
    required: true,
  },
  cache_expires_at: {
    type: String,
    required: true,
  },
});

const search = ref('');
const selectedProject = ref('');
const selectedType = ref('');
const selectedReadStatus = ref('');
const refreshing = ref(false);
const now = ref(Date.now());
let cacheExpiryTimer;

onMounted(() => {
  cacheExpiryTimer = window.setInterval(() => {
    now.value = Date.now();
  }, 1000);
});

onUnmounted(() => {
  window.clearInterval(cacheExpiryTimer);
});

const cacheIsValid = computed(
  () => now.value < new Date(props.cache_expires_at).getTime(),
);

const {
  readState,
  isRead,
  markAllAsRead,
  getUnreadCount,
  getReadCount,
  cleanupReadState,
} = useNotificationReadState();

watch(
  () => props.notifications,
  (notifications) => {
    cleanupReadState(notifications);
  },
  { immediate: true },
);

const projectOptions = computed(() =>
  [...new Set(props.notifications.map((notification) => notification.project))]
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b)),
);

const typeOptions = computed(() =>
  [...new Set(props.notifications.map((notification) => notification.type))]
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b)),
);

const notificationsWithReadState = computed(() => {
  readState.value;

  return props.notifications.map((notification) => ({
    ...notification,
    isRead: isRead(notification.id),
  }));
});

const notificationCounts = computed(() => {
  readState.value;

  return {
    total: props.notifications.length,
    unread: getUnreadCount(props.notifications),
    read: getReadCount(props.notifications),
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
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return searchableText.includes(query);
  });
});

const refreshNotifications = () => {
  refreshing.value = true;

  router.reload({
    only: ['notifications', 'total_count', 'refreshed_at', 'cache_expires_at'],
    onFinish: () => {
      refreshing.value = false;
    },
  });
};

const handleMarkAllAsRead = () => {
  markAllAsRead(props.notifications);
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
        <NotificationStats
          :total-count="notificationCounts.total"
          :unread-count="notificationCounts.unread"
          :read-count="notificationCounts.read"
          :refreshed-at="refreshed_at"
          :cache-expires-at="cache_expires_at"
          :cache-is-valid="cacheIsValid"
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
      </div>
    </div>
  </PublicLayout>
</template>
