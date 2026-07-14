<script setup>
import { useBacklogApiKey } from '@/composables/useBacklogApiKey';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  /** Show a visible label above the field */
  labeled: {
    type: Boolean,
    default: false,
  },
  /** Stack input and button vertically (settings panels) */
  stacked: {
    type: Boolean,
    default: false,
  },
  inputId: {
    type: String,
    default: 'backlog-api-key',
  },
});

const { apiKey, saveApiKey } = useBacklogApiKey();
const draft = ref(apiKey.value);
const saving = ref(false);

watch(apiKey, (value) => {
  draft.value = value;
});

const applyApiKey = () => {
  if (draft.value.trim() === apiKey.value) {
    return;
  }

  saving.value = true;
  saveApiKey(draft.value);

  router.reload({
    onFinish: () => {
      saving.value = false;
    },
  });
};

const handleKeydown = (event) => {
  if (event.key === 'Enter') {
    event.preventDefault();
    applyApiKey();
  }
};

const wrapperClass = computed(() =>
  props.stacked
    ? 'flex w-full flex-col gap-2'
    : 'flex w-full items-center gap-2',
);

const inputClass = computed(() =>
  [
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-400',
    props.stacked ? 'w-full' : 'min-w-0 flex-1',
  ].join(' '),
);
</script>

<template>
  <div :class="wrapperClass">
    <label
      v-if="labeled"
      :for="inputId"
      class="text-xs font-medium text-gray-600 dark:text-gray-300"
    >
      Backlog API key
    </label>
    <label v-else :for="inputId" class="sr-only">Backlog API key</label>
    <div :class="stacked ? 'flex w-full flex-col gap-2' : 'flex w-full min-w-0 items-center gap-2'">
      <input
        :id="inputId"
        v-model="draft"
        type="password"
        autocomplete="off"
        placeholder="Backlog API key"
        :class="inputClass"
        @keydown="handleKeydown"
      />
      <button
        type="button"
        class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-50"
        :disabled="saving"
        @click="applyApiKey"
      >
        {{ saving ? 'Saving…' : 'Apply' }}
      </button>
    </div>
  </div>
</template>
