<script setup>
defineProps({
  label: {
    type: String,
    default: 'Loading…',
  },
  size: {
    type: String,
    default: 'md',
  },
});

const sizeClasses = {
  sm: 'h-5 w-5 border-2',
  md: 'h-8 w-8 border-[3px]',
  lg: 'h-10 w-10 border-4',
};
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 text-center">
    <div
      class="animate-spin rounded-full border-indigo-600 border-t-transparent"
      :class="sizeClasses[size] ?? sizeClasses.md"
      role="status"
      aria-live="polite"
    />
    <p v-if="label" class="text-sm text-gray-500 dark:text-gray-400">{{ label }}</p>
  </div>
</template>
