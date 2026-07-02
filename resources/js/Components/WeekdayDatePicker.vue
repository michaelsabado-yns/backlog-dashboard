<script setup>
import {
  firstWeekdayOnOrAfter,
  formatLocalDate,
  isWeekend,
  isWeekdayInHistoryRange,
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

const visibleMonth = ref(props.modelValue);

watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      visibleMonth.value = value;
    }
  },
);

const effectiveMinDate = computed(() =>
  props.minDate ? firstWeekdayOnOrAfter(props.minDate) : null,
);

const monthDate = computed(() => parseLocalDate(`${visibleMonth.value.slice(0, 7)}-01`));

const monthLabel = computed(() => {
  if (!monthDate.value) {
    return '';
  }

  return new Intl.DateTimeFormat(undefined, {
    month: 'long',
    year: 'numeric',
  }).format(monthDate.value);
});

const rangeLabel = computed(() => {
  if (props.minDate && props.maxDate) {
    return `Selectable ${formatDisplayDate(effectiveMinDate.value ?? props.minDate)} – ${formatDisplayDate(props.maxDate)}`;
  }

  if (props.maxDate) {
    return `Through ${formatDisplayDate(props.maxDate)}`;
  }

  return 'Loading activity range…';
});

const formatDisplayDate = (value) => {
  const date = parseLocalDate(value);

  if (!date) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
};

const calendarDays = computed(() => {
  if (!monthDate.value) {
    return [];
  }

  const year = monthDate.value.getFullYear();
  const month = monthDate.value.getMonth();
  const firstDay = new Date(year, month, 1);
  const startOffset = (firstDay.getDay() + 6) % 7;
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const cells = [];

  for (let index = 0; index < startOffset; index += 1) {
    cells.push({ key: `pad-${index}`, empty: true });
  }

  for (let day = 1; day <= daysInMonth; day += 1) {
    const value = formatLocalDate(new Date(year, month, day));
    const weekend = isWeekend(value);
    const beforeHistory = props.minDate ? value < props.minDate : false;
    const inRange = isWeekdayInHistoryRange(value, effectiveMinDate.value, props.maxDate);
    const selectable = inRange && !props.disabled;

    cells.push({
      key: value,
      empty: false,
      value,
      day,
      weekend,
      beforeHistory,
      inRange,
      selectable,
      selected: value === props.modelValue,
      today: value === formatLocalDate(new Date()),
    });
  }

  return cells;
});

const canGoToPreviousMonth = computed(() => {
  if (!monthDate.value || !props.minDate) {
    return true;
  }

  const previousMonthEnd = formatLocalDate(new Date(monthDate.value.getFullYear(), monthDate.value.getMonth(), 0));

  return previousMonthEnd >= props.minDate;
});

const canGoToNextMonth = computed(() => {
  if (!monthDate.value || !props.maxDate) {
    return true;
  }

  const nextMonthStart = formatLocalDate(
    new Date(monthDate.value.getFullYear(), monthDate.value.getMonth() + 1, 1),
  );

  return nextMonthStart <= props.maxDate;
});

const shiftMonth = (amount) => {
  if (!monthDate.value) {
    return;
  }

  visibleMonth.value = formatLocalDate(
    new Date(monthDate.value.getFullYear(), monthDate.value.getMonth() + amount, 1),
  );
};

const selectDay = (day) => {
  if (!day.selectable) {
    return;
  }

  emit('update:modelValue', day.value);
};
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
    <div class="mb-3 flex items-center justify-between gap-2">
      <button
        type="button"
        class="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="disabled || !canGoToPreviousMonth"
        aria-label="Previous month"
        @click="shiftMonth(-1)"
      >
        ‹
      </button>

      <div class="text-center">
        <p class="text-sm font-semibold text-gray-900">{{ monthLabel }}</p>
        <p class="text-xs text-gray-500">{{ rangeLabel }}</p>
      </div>

      <button
        type="button"
        class="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="disabled || !canGoToNextMonth"
        aria-label="Next month"
        @click="shiftMonth(1)"
      >
        ›
      </button>
    </div>

    <div class="mb-1 grid grid-cols-7 gap-1 text-center text-[11px] font-medium uppercase tracking-wide text-gray-400">
      <span>Mon</span>
      <span>Tue</span>
      <span>Wed</span>
      <span>Thu</span>
      <span>Fri</span>
      <span>Sat</span>
      <span>Sun</span>
    </div>

    <div class="grid grid-cols-7 gap-1">
      <template v-for="day in calendarDays" :key="day.key">
        <span v-if="day.empty" />
        <button
          v-else
          type="button"
          class="relative h-9 rounded-md text-sm transition"
          :class="[
            day.selected
              ? 'bg-indigo-600 font-semibold text-white shadow-sm'
              : day.selectable
                ? 'font-medium text-gray-900 hover:bg-gray-100'
                : day.weekend
                  ? 'cursor-not-allowed bg-gray-50 text-gray-300'
                  : day.beforeHistory
                    ? 'cursor-not-allowed bg-gray-100 text-gray-300 line-through decoration-gray-300'
                    : 'cursor-not-allowed text-gray-300',
            day.today && !day.selected ? 'ring-2 ring-indigo-300' : '',
          ]"
          :disabled="!day.selectable"
          :title="
            day.beforeHistory
              ? 'Before the earliest activity in your latest 100 updates'
              : day.weekend
                ? 'Weekends are not tracked'
                : day.selectable
                  ? 'Selectable weekday'
                  : undefined
          "
          @click="selectDay(day)"
        >
          {{ day.day }}
        </button>
      </template>
    </div>

    <div class="mt-3 text-[11px] text-gray-500">
      <span class="inline-flex items-center gap-1">
        <span class="h-3 w-3 rounded bg-gray-100 line-through" />
        Before available activity range
      </span>
    </div>
  </div>
</template>
