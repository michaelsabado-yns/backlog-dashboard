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

const normalizedColor = computed(() => {
  if (!props.color || typeof props.color !== 'string') {
    return null;
  }

  const trimmed = props.color.trim();

  if (trimmed === '') {
    return null;
  }

  return trimmed.startsWith('#') ? trimmed : `#${trimmed}`;
});

const fallbackColor = computed(() => {
  const name = (props.status ?? '').toLowerCase();

  if (!name) {
    return null;
  }

  if (/(closed|完了|クローズ|done|resolved|解決)/.test(name)) {
    return '#6b7280';
  }

  if (/(progress|処理中|対応中|doing|active)/.test(name)) {
    return '#2563eb';
  }

  if (/(open|未対応|未着手|new|todo)/.test(name)) {
    return '#ea580c';
  }

  if (/(review|レビュー|pending|待機)/.test(name)) {
    return '#7c3aed';
  }

  return '#4b5563';
});

const badgeStyle = computed(() => {
  const color = normalizedColor.value ?? fallbackColor.value;

  if (!color) {
    return {};
  }

  return {
    color,
    borderColor: color,
    backgroundColor: `${color}1a`,
  };
});
</script>

<template>
  <span
    v-if="status"
    class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold leading-none"
    :style="badgeStyle"
  >
    {{ status }}
  </span>
</template>
