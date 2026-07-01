<script setup>
import IssueStatusBadge from '@/Components/Notifications/IssueStatusBadge.vue';
import { useNotificationReadState } from '@/composables/useNotificationReadState';
import { previewBacklogMarkdown } from '@/utils/backlogMarkdown';
import { Link } from '@inertiajs/vue3';

/**
 * @typedef {import('@/types/notification').NotificationWithReadState} NotificationWithReadState
 */

/**
 * @typedef {Object} Props
 * @property {NotificationWithReadState[]} notifications
 */

/** @type {Props} */
defineProps({
  notifications: {
    type: Array,
    required: true,
  },
});

const { markAsRead } = useNotificationReadState();

const formatDate = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(isoString));
};

/**
 * @param {NotificationWithReadState} notification
 */
const openInBacklog = (notification) => {
  markAsRead(notification.id);
};
</script>

<template>
  <div
    class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
  >
    <div v-if="notifications.length === 0" class="px-6 py-16 text-center">
      <p class="text-lg font-medium text-gray-900">No notifications found</p>
      <p class="mt-2 text-sm text-gray-500">
        Try adjusting your filters or refresh to load the latest notifications.
      </p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Status
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Project
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Issue Key
            </th>
            <th
              scope="col"
              class="min-w-[16rem] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Summary
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Sender
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Type
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Date
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Action
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr
            v-for="notification in notifications"
            :key="notification.id"
            class="transition-colors"
            :class="
              notification.isRead
                ? 'hover:bg-gray-50'
                : 'bg-indigo-50/60 hover:bg-indigo-50'
            "
          >
            <td class="whitespace-nowrap px-4 py-3 text-sm">
              <span
                v-if="notification.isRead"
                class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800"
              >
                Read
              </span>
              <span
                v-else
                class="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"
              >
                Unread
              </span>
            </td>
            <td
              class="whitespace-nowrap px-4 py-3 text-sm text-gray-700"
              :class="{ 'font-semibold text-gray-900': !notification.isRead }"
            >
              {{ notification.project }}
            </td>
            <td
              class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
              :class="{ 'font-bold': !notification.isRead, 'font-medium': notification.isRead }"
            >
              <Link
                :href="route('notifications.show', notification.id)"
                class="text-indigo-600 hover:text-indigo-500"
              >
                {{ notification.issue_key ?? '—' }}
              </Link>
            </td>
            <td
              class="px-4 py-3 text-sm text-gray-700"
              :class="{ 'font-semibold text-gray-900': !notification.isRead }"
            >
              <div class="flex items-start gap-2">
                <IssueStatusBadge
                  :status="notification.issue_status"
                  :color="notification.issue_status_color"
                />
                <div class="min-w-0 flex-1">
                  <p class="line-clamp-2">{{ notification.summary }}</p>
                  <p
                    v-if="notification.content"
                    class="mt-1 line-clamp-2 text-xs text-gray-500"
                  >
                    {{ previewBacklogMarkdown(notification.content) }}
                  </p>
                </div>
              </div>
            </td>
            <td
              class="whitespace-nowrap px-4 py-3 text-sm text-gray-700"
              :class="{ 'font-semibold text-gray-900': !notification.isRead }"
            >
              {{ notification.sender }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
              <span
                class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700"
              >
                {{ notification.type }}
              </span>
            </td>
            <td
              class="whitespace-nowrap px-4 py-3 text-sm text-gray-500"
              :class="{ 'font-semibold text-gray-700': !notification.isRead }"
            >
              {{ formatDate(notification.created_at) }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
              <div class="flex items-center justify-end gap-3">
                <Link
                  :href="route('notifications.show', notification.id)"
                  class="font-medium text-indigo-600 hover:text-indigo-500"
                >
                  View
                </Link>
                <a
                  v-if="notification.backlog_url"
                  :href="notification.backlog_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-gray-600 hover:text-gray-800"
                  @click="openInBacklog(notification)"
                >
                  Open in Backlog
                </a>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
