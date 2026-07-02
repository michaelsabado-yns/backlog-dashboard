const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

export const parseLocalDate = (value) => {
  if (!value || !DATE_PATTERN.test(value)) {
    return null;
  }

  const [year, month, day] = value.split('-').map(Number);
  const date = new Date(year, month - 1, day);

  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  return date;
};

export const formatLocalDate = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

export const isWeekend = (value) => {
  const date = typeof value === 'string' ? parseLocalDate(value) : value;

  if (!date) {
    return false;
  }

  const day = date.getDay();

  return day === 0 || day === 6;
};

export const addDays = (value, amount) => {
  const date = typeof value === 'string' ? parseLocalDate(value) : new Date(value);

  if (!date) {
    return null;
  }

  date.setDate(date.getDate() + amount);

  return formatLocalDate(date);
};

export const isDateInRange = (value, minDate, maxDate) => {
  if (!DATE_PATTERN.test(value)) {
    return false;
  }

  if (minDate && value < minDate) {
    return false;
  }

  if (maxDate && value > maxDate) {
    return false;
  }

  return true;
};

export const snapToWeekday = (value, { minDate = null, maxDate = null, prefer = 'backward' } = {}) => {
  let current = value;

  if (!DATE_PATTERN.test(current)) {
    return value;
  }

  if (minDate && current < minDate) {
    current = minDate;
  }

  if (maxDate && current > maxDate) {
    current = maxDate;
  }

  let guard = 0;

  while (isWeekend(current) && guard < 14) {
    const step = prefer === 'forward' ? 1 : -1;
    const next = addDays(current, step);

    if (!next || !isDateInRange(next, minDate, maxDate)) {
      const alternate = addDays(current, step * -1);

      if (!alternate || !isDateInRange(alternate, minDate, maxDate)) {
        break;
      }

      current = alternate;
    } else {
      current = next;
    }

    guard += 1;
  }

  return current;
};

export const clampToSelectableDate = (value, minDate, maxDate) =>
  snapToWeekday(value, {
    minDate: minDate ? firstWeekdayOnOrAfter(minDate) : null,
    maxDate,
    prefer: minDate && value < minDate ? 'forward' : 'backward',
  });

export const firstWeekdayOnOrAfter = (value) => {
  if (!DATE_PATTERN.test(value)) {
    return value;
  }

  let current = value;
  let guard = 0;

  while (isWeekend(current) && guard < 7) {
    const next = addDays(current, 1);

    if (!next) {
      break;
    }

    current = next;
    guard += 1;
  }

  return current;
};

export const isWeekdayInHistoryRange = (value, minDate, maxDate) => {
  if (isWeekend(value)) {
    return false;
  }

  if (minDate && value < minDate) {
    return false;
  }

  if (maxDate && value > maxDate) {
    return false;
  }

  return true;
};
