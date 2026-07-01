<script setup>
import { computed } from 'vue';

/**
 * @typedef {Object} Props
 * @property {string|null} status
 * @property {string|null} color
 */

/** @type {Props} */
const props = defineProps({
  status: {
    type: String,
    default: null,
  },
  color: {
    type: String,
    default: null,
  },
});

const badgeStyle = computed(() => {
  if (!props.color) {
    return {};
  }

  return {
    color: props.color,
    borderColor: props.color,
    backgroundColor: `${props.color}1a`,
  };
});
</script>

<template>
  <span
    v-if="status"
    class="inline-flex shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
    :style="badgeStyle"
  >
    {{ status }}
  </span>
</template>
