<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

/**
 * @typedef {Object} Props
 * @property {number} totalCount
 * @property {number} unreadCount
 * @property {number} readCount
 * @property {string} refreshedAt
 * @property {number} backlogUnreadCount
 * @property {boolean} fromCache
 * @property {boolean} [refreshing]
 */

/** @type {Props} */
defineProps({
  totalCount: {
    type: Number,
    required: true,
  },
  unreadCount: {
    type: Number,
    required: true,
  },
  readCount: {
    type: Number,
    required: true,
  },
  refreshedAt: {
    type: String,
    default: null,
  },
  backlogUnreadCount: {
    type: Number,
    required: true,
  },
  fromCache: {
    type: Boolean,
    required: true,
  },
  refreshing: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['refresh', 'mark-all-read']);

const formatTimestamp = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(isoString));
};
</script>

<template>
  <div class="space-y-2">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm text-gray-600">
        <span>
          <span class="font-semibold text-gray-900">{{ totalCount.toLocaleString() }}</span>
          total
        </span>
        <span>
          <span class="font-semibold text-green-700">{{ unreadCount.toLocaleString() }}</span>
          unread
        </span>
        <span>
          <span class="font-semibold text-gray-800">{{ readCount.toLocaleString() }}</span>
          read
        </span>
      </div>

      <div class="flex w-full items-center gap-2 sm:w-auto">
        <SecondaryButton
          type="button"
          class="flex-1 justify-center sm:flex-none"
          :disabled="unreadCount === 0"
          @click="$emit('mark-all-read')"
        >
          Mark all read
        </SecondaryButton>

        <PrimaryButton
          type="button"
          class="flex-1 justify-center sm:flex-none"
          :disabled="refreshing"
          title="Force refetch from Backlog API"
          @click="$emit('refresh')"
        >
          <span v-if="refreshing">Refetching…</span>
          <span v-else>Refetch</span>
        </PrimaryButton>
      </div>
    </div>

    <p class="text-xs text-gray-400">
      Fetched {{ formatTimestamp(refreshedAt) }}
      <span v-if="fromCache">· cached</span>
      · Backlog unread {{ backlogUnreadCount.toLocaleString() }}
    </p>
  </div>
</template>
