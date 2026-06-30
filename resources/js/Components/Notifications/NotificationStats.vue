<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';

/**
 * @typedef {Object} Props
 * @property {number} totalCount
 * @property {string} refreshedAt
 * @property {boolean} [refreshing]
 */

/** @type {Props} */
defineProps({
  totalCount: {
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

defineEmits(['refresh']);

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
  <div
    class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="space-y-1">
      <p class="text-sm font-medium text-gray-500">Total notifications</p>
      <p class="text-3xl font-semibold text-gray-900">
        {{ totalCount.toLocaleString() }}
      </p>
      <p class="text-sm text-gray-500">
        Last refreshed:
        <span class="font-medium text-gray-700">
          {{ formatTimestamp(refreshedAt) }}
        </span>
      </p>
    </div>

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
</template>
