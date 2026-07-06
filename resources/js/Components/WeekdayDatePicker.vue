<script setup>
import {
  addDays,
  firstWeekdayOnOrAfter,
  formatLocalDate,
  isWeekdayInHistoryRange,
  mondayOfWeek,
  parseLocalDate,
} from '@/utils/weekdayDate';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    required: true,
  },
  minDate: {
    type: String,
    default: null,
  },
  maxDate: {
    type: String,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const WEEKDAY_LABELS = ['Mo', 'Tu', 'We', 'Th', 'Fr'];

const weekAnchor = ref(mondayOfWeek(props.modelValue));

watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      weekAnchor.value = mondayOfWeek(value);
    }
  },
);

const effectiveMinDate = computed(() =>
  props.minDate ? firstWeekdayOnOrAfter(props.minDate) : null,
);

const weekLabel = computed(() => {
  const monday = parseLocalDate(weekAnchor.value);
  const friday = parseLocalDate(addDays(weekAnchor.value, 4));

  if (!monday || !friday) {
    return '';
  }

  const formatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
  });

  const yearFormatter = new Intl.DateTimeFormat(undefined, { year: 'numeric' });
  const start = formatter.format(monday);
  const end = formatter.format(friday);
  const year = yearFormatter.format(friday);

  return `${start} – ${end}, ${year}`;
});

const weekdayCells = computed(() => {
  const today = formatLocalDate(new Date());

  return WEEKDAY_LABELS.map((label, index) => {
    const value = addDays(weekAnchor.value, index);
    const beforeHistory = props.minDate ? value < props.minDate : false;
    const inRange = isWeekdayInHistoryRange(value, effectiveMinDate.value, props.maxDate);
    const selectable = inRange && !props.disabled;

    return {
      key: value,
      label,
      value,
      day: value.slice(8, 10).replace(/^0/, ''),
      beforeHistory,
      selectable,
      selected: value === props.modelValue,
      today: value === today,
    };
  });
});

const canGoToPreviousWeek = computed(() => {
  if (!props.minDate) {
    return true;
  }

  const previousFriday = addDays(weekAnchor.value, -3);

  return previousFriday !== null && previousFriday >= props.minDate;
});

const canGoToNextWeek = computed(() => {
  if (!props.maxDate) {
    return true;
  }

  const nextMonday = addDays(weekAnchor.value, 7);

  return nextMonday !== null && nextMonday <= props.maxDate;
});

const shiftWeek = (amount) => {
  const next = addDays(weekAnchor.value, amount * 7);

  if (next) {
    weekAnchor.value = next;
  }
};

const selectDay = (day) => {
  if (!day.selectable) {
    return;
  }

  emit('update:modelValue', day.value);
};
</script>

<template>
  <div class="w-full">
    <div class="flex items-center gap-1.5">
      <button
        type="button"
        class="shrink-0 rounded-md border border-gray-200 px-2 py-2 text-sm text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="disabled || !canGoToPreviousWeek"
        aria-label="Previous week"
        @click="shiftWeek(-1)"
      >
        ‹
      </button>

      <div class="grid min-w-0 flex-1 grid-cols-5 gap-1">
        <button
          v-for="day in weekdayCells"
          :key="day.key"
          type="button"
          class="min-w-0 rounded-md border py-1.5 text-center transition"
          :class="[
            day.selected
              ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
              : day.selectable
                ? 'border-gray-200 bg-white text-gray-900 hover:border-gray-300 hover:bg-gray-50'
                : 'cursor-not-allowed border-transparent bg-gray-50 text-gray-300',
            day.beforeHistory ? 'line-through decoration-gray-300' : '',
            day.today && !day.selected ? 'ring-2 ring-indigo-300 ring-offset-1' : '',
          ]"
          :disabled="!day.selectable"
          :title="
            day.beforeHistory
              ? 'Before available activity range'
              : day.selectable
                ? day.value
                : undefined
          "
          @click="selectDay(day)"
        >
          <span
            class="block text-[10px] font-medium uppercase leading-none"
            :class="day.selected ? 'text-indigo-100' : 'text-gray-400'"
          >
            {{ day.label }}
          </span>
          <span class="mt-0.5 block text-sm font-semibold leading-none">{{ day.day }}</span>
        </button>
      </div>

      <button
        type="button"
        class="shrink-0 rounded-md border border-gray-200 px-2 py-2 text-sm text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="disabled || !canGoToNextWeek"
        aria-label="Next week"
        @click="shiftWeek(1)"
      >
        ›
      </button>
    </div>

    <p class="mt-1 text-center text-[11px] text-gray-400">
      {{ weekLabel }}
    </p>
  </div>
</template>
