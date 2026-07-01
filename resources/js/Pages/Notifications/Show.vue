<script setup>
import BacklogMarkdown from '@/Components/Notifications/BacklogMarkdown.vue';
import IssueStatusBadge from '@/Components/Notifications/IssueStatusBadge.vue';
import { useNotificationReadState } from '@/composables/useNotificationReadState';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

/**
 * @typedef {import('@/types/notification').Notification} Notification
 */

/**
 * @typedef {Object} Props
 * @property {Notification} notification
 */

/** @type {Props} */
const props = defineProps({
  notification: {
    type: Object,
    required: true,
  },
});

const { markAsRead } = useNotificationReadState();

markAsRead(props.notification.id);

/**
 * @param {number|string} id
 */
const openInBacklog = (id) => {
  markAsRead(id);
};

const formatDate = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'full',
    timeStyle: 'short',
  }).format(new Date(isoString));
};
</script>

<template>
  <Head :title="`${notification.issue_key ?? 'Notification'} · Backlog`" />

  <PublicLayout>
    <template #header>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-gray-500">{{ notification.project }}</p>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
              {{ notification.issue_key ?? 'Notification' }}
            </h2>
        </div>

        <Link
          :href="route('notifications.index')"
          class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
        >
          ← Back to notifications
        </Link>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
              <span
                class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700"
              >
                {{ notification.type }}
              </span>
              <IssueStatusBadge
                :status="notification.issue_status"
                :color="notification.issue_status_color"
              />
              <h3 class="text-lg font-semibold text-gray-900">
                {{ notification.summary }}
              </h3>
              <p class="text-sm text-gray-500">
                From
                <span class="font-medium text-gray-700">
                  {{ notification.sender }}
                </span>
                ·
                {{ formatDate(notification.created_at) }}
              </p>
            </div>

            <a
              v-if="notification.backlog_url"
              :href="notification.backlog_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500"
              @click="openInBacklog(notification.id)"
            >
              Open in Backlog
            </a>
          </div>
        </div>

        <div
          v-if="notification.content"
          class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
        >
          <h4 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
            Comment
          </h4>
          <BacklogMarkdown :content="notification.content" />
        </div>

        <div
          v-else
          class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500"
        >
          This notification has no comment body. Open the issue in Backlog for full
          context.
        </div>
      </div>
    </div>
  </PublicLayout>
</template>
