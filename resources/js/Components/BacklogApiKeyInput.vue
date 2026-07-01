<script setup>
import { useBacklogApiKey } from '@/composables/useBacklogApiKey';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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
</script>

<template>
  <div class="flex items-center gap-2">
    <label for="backlog-api-key" class="sr-only">Backlog API key</label>
    <input
      id="backlog-api-key"
      v-model="draft"
      type="password"
      autocomplete="off"
      placeholder="Backlog API key"
      class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-52"
      @keydown="handleKeydown"
    />
    <button
      type="button"
      class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-50"
      :disabled="saving"
      @click="applyApiKey"
    >
      {{ saving ? 'Saving…' : 'Apply' }}
    </button>
  </div>
</template>
