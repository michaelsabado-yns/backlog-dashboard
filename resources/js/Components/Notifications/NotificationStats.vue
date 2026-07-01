<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

/**
 * @typedef {Object} Props
 * @property {number} totalCount
 * @property {number} unreadCount
 * @property {number} readCount
 * @property {string} refreshedAt
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
  <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
    <div
      class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
    >
      <div class="grid flex-1 gap-4 sm:grid-cols-3">
        <div class="space-y-1">
          <p class="text-sm font-medium text-gray-500">Total notifications</p>
          <p class="text-3xl font-semibold text-gray-900">
            {{ totalCount.toLocaleString() }}
          </p>
        </div>

        <div class="space-y-1">
          <p class="text-sm font-medium text-gray-500">Unread notifications</p>
          <p class="text-3xl font-semibold text-indigo-600">
            {{ unreadCount.toLocaleString() }}
          </p>
        </div>

        <div class="space-y-1">
          <p class="text-sm font-medium text-gray-500">Read notifications</p>
          <p class="text-3xl font-semibold text-gray-700">
            {{ readCount.toLocaleString() }}
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <p class="text-sm text-gray-500 sm:mr-2">
          Last refreshed:
          <span class="font-medium text-gray-700">
            {{ formatTimestamp(refreshedAt) }}
          </span>
        </p>

        <SecondaryButton
          type="button"
          class="justify-center"
          :disabled="unreadCount === 0"
          @click="$emit('mark-all-read')"
        >
          Mark all as read
        </SecondaryButton>

        <PrimaryButton
          type="button"
          class="justify-center"
          :disabled="refreshing"
          @click="$emit('refresh')"
        >
          <span v-if="refreshing">Refreshing…</span>
          <span v-else>Refresh</span>
        </PrimaryButton>
      </div>
    </div>
  </div>
</template>
