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

const openInBacklog = () => {
  markAsRead(props.notification.id);
};

const formatDate = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(isoString));
};

const formatRelativeTime = (isoString) => {
  if (!isoString) {
    return '';
  }

  const now = Date.now();
  const then = new Date(isoString).getTime();
  const diffSeconds = Math.round((then - now) / 1000);
  const absSeconds = Math.abs(diffSeconds);

  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

  if (absSeconds < 60) {
    return formatter.format(diffSeconds, 'second');
  }

  const diffMinutes = Math.round(diffSeconds / 60);

  if (Math.abs(diffMinutes) < 60) {
    return formatter.format(diffMinutes, 'minute');
  }

  const diffHours = Math.round(diffMinutes / 60);

  if (Math.abs(diffHours) < 24) {
    return formatter.format(diffHours, 'hour');
  }

  const diffDays = Math.round(diffHours / 24);

  if (Math.abs(diffDays) < 7) {
    return formatter.format(diffDays, 'day');
  }

  return formatDate(isoString);
};
</script>

<template>
  <Head :title="`${notification.issue_key ?? 'Notification'} · Backlog`" />

  <PublicLayout>
    <template #header>
      <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Notification</p>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ notification.issue_key ?? 'Notification' }}
          </h2>
        </div>

        <Link
          :href="route('notifications.index')"
          class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          ← Back to notifications
        </Link>
      </div>
    </template>

    <div class="py-5">
      <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10">
          <header class="border-b border-gray-100 px-4 py-4 dark:border-gray-700 sm:px-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ notification.issue_key ?? 'Notification' }}
                  </span>
                  <IssueStatusBadge
                    :status="notification.issue_status"
                    :color="notification.issue_status_color"
                  />
                  <span
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                  >
                    {{ notification.type }}
                  </span>
                </div>

                <h3 class="mt-2 text-base font-semibold leading-snug text-gray-900 dark:text-gray-100">
                  {{ notification.summary }}
                </h3>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  <span class="font-medium text-gray-700 dark:text-gray-300">{{ notification.project }}</span>
                  <span class="mx-1 text-gray-300 dark:text-gray-600">·</span>
                  <span>{{ notification.sender }}</span>
                  <template v-if="notification.assignee">
                    <span class="mx-1 text-gray-300 dark:text-gray-600">·</span>
                    <span>
                      Assignee:
                      <span class="font-medium text-gray-700 dark:text-gray-300">{{ notification.assignee }}</span>
                    </span>
                  </template>
                </p>
              </div>

              <time
                class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400"
                :datetime="notification.created_at ?? undefined"
                :title="formatDate(notification.created_at)"
              >
                <span class="block font-medium text-gray-700 dark:text-gray-300">
                  {{ formatRelativeTime(notification.created_at) }}
                </span>
                <span class="mt-0.5 block text-[11px] text-gray-400 dark:text-gray-500">
                  {{ formatDate(notification.created_at) }}
                </span>
              </time>
            </div>

            <a
              v-if="notification.backlog_url"
              :href="notification.backlog_url"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-3 inline-flex text-sm font-medium text-green-700 hover:underline dark:text-green-300"
              @click="openInBacklog"
            >
              Open in Backlog
            </a>
          </header>

          <section v-if="notification.content" class="px-4 py-4 sm:px-5">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
              Comment
            </p>
            <BacklogMarkdown :content="notification.content" />
          </section>

          <section
            v-else
            class="border-t border-gray-100 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400 sm:px-5"
          >
            <p class="font-medium text-gray-700 dark:text-gray-300">No comment body</p>
            <p class="mt-1 text-xs">
              Open the issue in Backlog for full context.
            </p>
          </section>
        </article>
      </div>
    </div>
  </PublicLayout>
</template>
