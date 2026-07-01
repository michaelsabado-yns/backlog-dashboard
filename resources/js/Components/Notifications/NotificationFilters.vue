<script setup>
import InputLabel from '@/Components/InputLabel.vue';
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
</script>

<template>
  <div
    class="sticky top-0 z-10 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
  >
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div>
        <InputLabel for="notification-search" value="Search" />
        <TextInput
          id="notification-search"
          :model-value="search"
          type="search"
          class="mt-1 block w-full"
          placeholder="Issue key, summary, sender, comment…"
          @update:model-value="$emit('update:search', $event)"
        />
      </div>

      <div>
        <InputLabel for="notification-read-status" value="Read status" />
        <select
          id="notification-read-status"
          :value="readStatus"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          @change="$emit('update:readStatus', $event.target.value)"
        >
          <option value="">All</option>
          <option value="unread">Unread</option>
          <option value="read">Read</option>
        </select>
      </div>

      <div>
        <InputLabel for="notification-project" value="Project" />
        <select
          id="notification-project"
          :value="project"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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

      <div>
        <InputLabel for="notification-type" value="Notification type" />
        <select
          id="notification-type"
          :value="type"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          @change="$emit('update:type', $event.target.value)"
        >
          <option value="">All types</option>
          <option v-for="option in typeOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>
    </div>
  </div>
</template>
