<script setup>
import BacklogMarkdown from '@/Components/Notifications/BacklogMarkdown.vue';
import IssueStatusBadge from '@/Components/Notifications/IssueStatusBadge.vue';
import { useNotificationReadState } from '@/composables/useNotificationReadState';
import { commentNeedsExpand, hasCommentContent } from '@/utils/backlogMarkdown';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const expandedCommentIds = ref(new Set());

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

/**
 * @param {number} id
 */
const isCommentExpanded = (id) => expandedCommentIds.value.has(id);

/**
 * @param {number} id
 */
const toggleComment = (id) => {
  const next = new Set(expandedCommentIds.value);

  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }

  expandedCommentIds.value = next;
};

/**
 * @param {NotificationWithReadState} notification
 */
const showFullComment = (notification) =>
  isCommentExpanded(notification.id) || !commentNeedsExpand(notification.content);

/**
 * @param {NotificationWithReadState} notification
 */
const openInBacklog = (notification) => {
  markAsRead(notification.id);
};
</script>

<template>
  <div>
    <div
      v-if="notifications.length === 0"
      class="px-4 py-12 text-center text-sm text-gray-500"
    >
      <p class="font-medium text-gray-900">No notifications found</p>
      <p class="mt-1">Try different filters or refetch from Backlog.</p>
    </div>

    <ul v-else class="divide-y divide-gray-100">
      <li
        v-for="notification in notifications"
        :key="notification.id"
        class="px-3 py-3 sm:px-4"
        :class="notification.isRead ? 'bg-white' : 'bg-green-50/60'"
      >
        <article class="min-w-0">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span
                  v-if="!notification.isRead"
                  class="inline-block h-2 w-2 shrink-0 rounded-full bg-green-600"
                  aria-label="Unread"
                />
                <Link
                  :href="route('notifications.show', notification.id)"
                  class="text-sm font-semibold hover:underline"
                  :class="
                    notification.isRead ? 'text-gray-800' : 'text-green-700'
                  "
                >
                  {{ notification.issue_key ?? 'Notification' }}
                </Link>
                <IssueStatusBadge
                  :status="notification.issue_status"
                  :color="notification.issue_status_color"
                />
              </div>

              <p
                class="mt-1 line-clamp-2 text-sm leading-snug text-gray-900"
                :class="notification.isRead ? 'font-normal' : 'font-medium'"
              >
                {{ notification.summary }}
              </p>
            </div>

            <time
              class="shrink-0 whitespace-nowrap text-xs text-gray-500"
              :datetime="notification.created_at ?? undefined"
              :title="formatDate(notification.created_at)"
            >
              {{ formatRelativeTime(notification.created_at) }}
            </time>
          </div>

          <p class="mt-1.5 text-xs text-gray-500">
            <span>{{ notification.project }}</span>
            <span class="mx-1 text-gray-300">·</span>
            <span>{{ notification.type }}</span>
            <span class="mx-1 text-gray-300">·</span>
            <span>{{ notification.sender }}</span>
            <template v-if="notification.assignee">
              <span class="mx-1 text-gray-300">·</span>
              <span>
                Assignee:
                <span class="font-medium text-gray-700">{{ notification.assignee }}</span>
              </span>
            </template>
          </p>

          <div v-if="hasCommentContent(notification.content)" class="mt-2">
            <div class="rounded-md border border-gray-100 bg-gray-50 px-2.5 py-2">
              <BacklogMarkdown
                :content="notification.content"
                :compact="!showFullComment(notification)"
              />
            </div>

            <button
              v-if="commentNeedsExpand(notification.content)"
              type="button"
              class="mt-1 text-[11px] font-medium text-gray-500 hover:text-gray-800"
              @click="toggleComment(notification.id)"
            >
              {{
                isCommentExpanded(notification.id)
                  ? 'Hide full comment'
                  : 'Show full comment'
              }}
            </button>
          </div>

          <div class="mt-2 flex flex-wrap gap-x-4 text-xs">
            <Link
              :href="route('notifications.show', notification.id)"
              class="font-medium text-green-700 hover:underline"
            >
              View
            </Link>
            <a
              v-if="notification.backlog_url"
              :href="notification.backlog_url"
              target="_blank"
              rel="noopener noreferrer"
              class="font-medium text-gray-600 hover:text-gray-900"
              @click="openInBacklog(notification)"
            >
              Open in Backlog
            </a>
          </div>
        </article>
      </li>
    </ul>
  </div>
</template>
