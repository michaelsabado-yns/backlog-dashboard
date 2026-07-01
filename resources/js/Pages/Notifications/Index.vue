<script setup>
import NotificationFilters from '@/Components/Notifications/NotificationFilters.vue';
import NotificationStats from '@/Components/Notifications/NotificationStats.vue';
import NotificationTable from '@/Components/Notifications/NotificationTable.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * @typedef {import('@/types/notification').Notification} Notification
 */

/**
 * @typedef {Object} Props
 * @property {Notification[]} notifications
 * @property {number} total_count
 * @property {string} refreshed_at
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
});

const search = ref('');
const selectedProject = ref('');
const selectedType = ref('');
const refreshing = ref(false);

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

const filteredNotifications = computed(() => {
  const query = search.value.trim().toLowerCase();

  return props.notifications.filter((notification) => {
    if (
      selectedProject.value &&
      notification.project !== selectedProject.value
    ) {
      return false;
    }

    if (selectedType.value && notification.type !== selectedType.value) {
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
    only: ['notifications', 'total_count', 'refreshed_at'],
    onFinish: () => {
      refreshing.value = false;
    },
  });
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
          :total-count="total_count"
          :refreshed-at="refreshed_at"
          :refreshing="refreshing"
          @refresh="refreshNotifications"
        />

        <NotificationFilters
          v-model:search="search"
          v-model:project="selectedProject"
          v-model:type="selectedType"
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
