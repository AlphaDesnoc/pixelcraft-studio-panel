const MAX_OCCURRENCES = 500;

function startOfDay(date) {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  return d;
}

function isoDate(d) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function addYears(date, years) {
  const d = new Date(date);
  d.setFullYear(d.getFullYear() + years);
  return d;
}

function occurrenceDateTime(originalStart, dayDate) {
  const source = new Date(originalStart);
  const day = startOfDay(dayDate);
  return new Date(
    day.getFullYear(),
    day.getMonth(),
    day.getDate(),
    source.getHours(),
    source.getMinutes(),
    source.getSeconds(),
    source.getMilliseconds(),
  );
}

function shouldOccurOnDay(day, event, seriesStart) {
  const dayStart = startOfDay(day);
  const seriesStartDay = startOfDay(seriesStart);

  if (dayStart < seriesStartDay) {
    return false;
  }

  switch (event.recurrence) {
    case "daily":
      return true;
    case "weekly": {
      const weekdays = event.recurrence_weekdays?.length
        ? event.recurrence_weekdays
        : [seriesStart.getDay()];
      return weekdays.includes(dayStart.getDay());
    }
    case "monthly":
      return dayStart.getDate() === seriesStartDay.getDate();
    default:
      return false;
  }
}

function expandSingleEvent(event, rangeStart, rangeEnd) {
  if (!event.recurrence) {
    return [event];
  }

  const exceptions = new Map(
    (event.exceptions ?? []).map((ex) => [ex.occurrence_date, ex]),
  );

  const seriesStart = new Date(event.start_at);
  const seriesEnd = new Date(event.end_at);
  if (Number.isNaN(seriesStart.getTime()) || Number.isNaN(seriesEnd.getTime())) {
    return [event];
  }

  const durationMs = seriesEnd.getTime() - seriesStart.getTime();
  const until = event.recurrence_until
    ? startOfDay(event.recurrence_until)
    : startOfDay(addYears(seriesStart, 2));

  const viewStart = startOfDay(rangeStart);
  const viewEnd = startOfDay(rangeEnd);

  const occurrences = [];
  const cursor = new Date(viewStart);
  let guard = 0;

  while (cursor <= viewEnd && cursor <= until && guard < MAX_OCCURRENCES) {
    guard += 1;

    if (shouldOccurOnDay(cursor, event, seriesStart)) {
      const dateKey = isoDate(cursor);
      const exception = exceptions.get(dateKey);

      if (exception?.type === "cancelled") {
        cursor.setDate(cursor.getDate() + 1);
        continue;
      }

      let occurrenceStart = occurrenceDateTime(seriesStart, cursor);
      let occurrenceEnd = new Date(occurrenceStart.getTime() + durationMs);
      let occurrenceTitle = event.title;
      let occurrenceDescription = event.description;
      let occurrenceColor = event.color;
      let occurrenceAllDay = event.all_day;

      if (exception?.type === "modified") {
        if (exception.start_at) {
          occurrenceStart = new Date(exception.start_at);
        }
        if (exception.end_at) {
          occurrenceEnd = new Date(exception.end_at);
        }
        if (exception.title) {
          occurrenceTitle = exception.title;
        }
        if (exception.description != null) {
          occurrenceDescription = exception.description;
        }
        if (exception.color) {
          occurrenceColor = exception.color;
        }
        if (exception.all_day != null) {
          occurrenceAllDay = exception.all_day;
        }
      }

      if (occurrenceEnd >= viewStart && occurrenceStart <= addDay(viewEnd, 1)) {
        occurrences.push({
          ...event,
          id: `${event.id}-${dateKey}`,
          series_id: event.id,
          title: occurrenceTitle,
          description: occurrenceDescription,
          color: occurrenceColor,
          all_day: occurrenceAllDay,
          start_at: occurrenceStart.toISOString(),
          end_at: occurrenceEnd.toISOString(),
          occurrence_date: dateKey,
        });
      }
    }

    cursor.setDate(cursor.getDate() + 1);
  }

  return occurrences;
}

function addDay(date, days) {
  const d = new Date(date);
  d.setDate(d.getDate() + days);
  return d;
}

export function expandRecurringEvents(events, rangeStart, rangeEnd) {
  if (!rangeStart || !rangeEnd) {
    return events ?? [];
  }

  return (events ?? []).flatMap((event) =>
    expandSingleEvent(event, rangeStart, rangeEnd),
  );
}

export const RECURRENCE_OPTIONS = [
  { value: "", label: "Ne se répète pas" },
  { value: "daily", label: "Tous les jours" },
  { value: "weekly", label: "Toutes les semaines" },
  { value: "monthly", label: "Tous les mois" },
];

export const WEEKDAY_OPTIONS = [
  { value: 1, label: "Lun", full: "Lundi" },
  { value: 2, label: "Mar", full: "Mardi" },
  { value: 3, label: "Mer", full: "Mercredi" },
  { value: 4, label: "Jeu", full: "Jeudi" },
  { value: 5, label: "Ven", full: "Vendredi" },
  { value: 6, label: "Sam", full: "Samedi" },
  { value: 0, label: "Dim", full: "Dimanche" },
];

export function weekdayFromStartInput(value) {
  if (!value) {
    return new Date().getDay();
  }
  const dateOnly = value.length <= 10;
  const d = new Date(dateOnly ? `${value}T12:00:00` : value);
  return Number.isNaN(d.getTime()) ? new Date().getDay() : d.getDay();
}

export function recurrenceSummary(event) {
  if (!event?.recurrence) {
    return "";
  }

  switch (event.recurrence) {
    case "daily":
      return "Tous les jours";
    case "weekly": {
      const days = (event.recurrence_weekdays ?? [])
        .map((day) => WEEKDAY_OPTIONS.find((option) => option.value === day)?.full)
        .filter(Boolean);
      if (days.length === 1) {
        return `Chaque ${days[0].toLowerCase()}`;
      }
      if (days.length > 1) {
        return `Chaque ${days.map((d) => d.toLowerCase()).join(", ")}`;
      }
      return "Toutes les semaines";
    }
    case "monthly":
      return "Tous les mois";
    default:
      return "";
  }
}
