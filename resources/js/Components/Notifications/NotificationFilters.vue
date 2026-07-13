<script setup>
import TextInput from '@/Components/TextInput.vue';

/**
 * @typedef {Object} Props
 * @property {string} search
 * @property {string} project
 * @property {string} type
 * @property {string} readStatus
 * @property {string[]} projectOptions
 * @property {string[]} typeOptions
 */

/** @type {Props} */
defineProps({
  search: {
    type: String,
    required: true,
  },
  project: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    required: true,
  },
  readStatus: {
    type: String,
    required: true,
  },
  projectOptions: {
    type: Array,
    required: true,
  },
  typeOptions: {
    type: Array,
    required: true,
  },
});

defineEmits([
  'update:search',
  'update:project',
  'update:type',
  'update:readStatus',
]);

const selectClass =
  'block w-full rounded-md border border-gray-300 bg-white py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
</script>

<template>
  <div class="border-t border-gray-200 bg-gray-50 px-3 py-3 dark:border-gray-700 dark:bg-gray-900/50 sm:px-4">
    <div class="grid grid-cols-2 gap-3">
      <div class="col-span-2">
        <label for="notification-search" class="sr-only">Search</label>
        <TextInput
          id="notification-search"
          :model-value="search"
          type="search"
          class="block w-full border-gray-300 py-2 text-sm shadow-sm"
          placeholder="Search issue, summary, comment…"
          @update:model-value="$emit('update:search', $event)"
        />
      </div>

      <div>
        <label
          for="notification-read-status"
          class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
        >
          Status
        </label>
        <select
          id="notification-read-status"
          :value="readStatus"
          :class="selectClass"
          @change="$emit('update:readStatus', $event.target.value)"
        >
          <option value="">All</option>
          <option value="unread">Unread</option>
          <option value="read">Read</option>
        </select>
      </div>

      <div>
        <label
          for="notification-type"
          class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
        >
          Type
        </label>
        <select
          id="notification-type"
          :value="type"
          :class="selectClass"
          @change="$emit('update:type', $event.target.value)"
        >
          <option value="">All types</option>
          <option v-for="option in typeOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>

      <div class="col-span-2">
        <label
          for="notification-project"
          class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
        >
          Project
        </label>
        <select
          id="notification-project"
          :value="project"
          :class="selectClass"
          @change="$emit('update:project', $event.target.value)"
        >
          <option value="">All projects</option>
          <option
            v-for="option in projectOptions"
            :key="option"
            :value="option"
          >
            {{ option }}
          </option>
        </select>
      </div>
    </div>
  </div>
</template>
