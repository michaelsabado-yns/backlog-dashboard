<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

/**
 * @typedef {Object} Props
 * @property {number} totalCount
 * @property {number} unreadCount
 * @property {number} readCount
 * @property {string} refreshedAt
 * @property {string} cacheExpiresAt
 * @property {boolean} cacheIsValid
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
  cacheExpiresAt: {
    type: String,
    required: true,
  },
  cacheIsValid: {
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
          <p class="text-3xl font-semibold text-green-700">
            {{ readCount.toLocaleString() }}
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <div class="text-sm text-gray-500 sm:mr-2">
          <p>
            Last fetched:
            <span class="font-medium text-gray-700">
              {{ formatTimestamp(refreshedAt) }}
            </span>
          </p>
          <p v-if="cacheIsValid" class="text-xs text-gray-400">
            Cached until {{ formatTimestamp(cacheExpiresAt) }}
          </p>
        </div>

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
          :disabled="refreshing || cacheIsValid"
          :title="
            cacheIsValid
              ? `Notifications are cached until ${formatTimestamp(cacheExpiresAt)}`
              : undefined
          "
          @click="$emit('refresh')"
        >
          <span v-if="refreshing">Refreshing…</span>
          <span v-else-if="cacheIsValid">Cached</span>
          <span v-else>Refresh</span>
        </PrimaryButton>
      </div>
    </div>
  </div>
</template>
