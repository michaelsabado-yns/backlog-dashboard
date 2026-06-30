<script setup>
import { computed } from 'vue';
import { renderBacklogMarkdown } from '@/utils/backlogMarkdown';

/**
 * @typedef {Object} Props
 * @property {string|null|undefined} content
 * @property {boolean} [compact]
 */

/** @type {Props} */
const props = defineProps({
  content: {
    type: String,
    default: null,
  },
  compact: {
    type: Boolean,
    default: false,
  },
});

const renderedHtml = computed(() => renderBacklogMarkdown(props.content));
</script>

<template>
  <div
    v-if="renderedHtml"
    class="backlog-markdown"
    :class="compact ? 'backlog-markdown--compact' : 'backlog-markdown--full'"
    v-html="renderedHtml"
  />
</template>
