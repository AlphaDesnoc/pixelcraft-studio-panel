<script setup>
import { computed, ref } from "vue";
import { ChevronLeft, ChevronRight, Plus, Repeat } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { expandRecurringEvents } from "@/lib/calendarRecurrence.js";
import EventFormDialog from "./EventFormDialog.vue";

const props = defineProps({
  projectSlug: { type: String, required: true },
  events: { type: Array, default: () => [] },
  rankId: { type: Number, default: null },
});

const monthLabels = [
  "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
  "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre",
];
const dayLabels = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];

const cursor = ref(startOfMonth(new Date()));

function startOfMonth(d) {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function isSameDay(a, b) {
  return (
    a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate()
  );
}

function isoDate(d) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const today = computed(() => new Date());

const monthLabel = computed(
  () => `${monthLabels[cursor.value.getMonth()]} ${cursor.value.getFullYear()}`,
);

const days = computed(() => {
  const first = startOfMonth(cursor.value);
  const dayOfWeek = (first.getDay() + 6) % 7;
  const gridStart = new Date(first);
  gridStart.setDate(first.getDate() - dayOfWeek);

  const cells = [];
  for (let i = 0; i < 42; i++) {
    const d = new Date(gridStart);
    d.setDate(gridStart.getDate() + i);
    cells.push({
      date: d,
      iso: isoDate(d),
      inMonth: d.getMonth() === cursor.value.getMonth(),
      isToday: isSameDay(d, today.value),
    });
  }
  return cells;
});

const visibleRange = computed(() => ({
  start: days.value[0]?.date ?? new Date(),
  end: days.value[days.value.length - 1]?.date ?? new Date(),
}));

const displayEvents = computed(() =>
  expandRecurringEvents(props.events, visibleRange.value.start, visibleRange.value.end),
);

const eventsByDay = computed(() => {
  const map = new Map();
  for (const ev of displayEvents.value) {
    const start = new Date(ev.start_at);
    const end = new Date(ev.end_at);
    const cursor = new Date(start);
    cursor.setHours(0, 0, 0, 0);
    const endDay = new Date(end);
    endDay.setHours(0, 0, 0, 0);
    while (cursor <= endDay) {
      const k = isoDate(cursor);
      if (!map.has(k)) map.set(k, []);
      map.get(k).push(ev);
      cursor.setDate(cursor.getDate() + 1);
    }
  }
  return map;
});

function eventsForDay(iso) {
  return eventsByDay.value.get(iso) ?? [];
}

function prevMonth() {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() - 1, 1);
}

function nextMonth() {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + 1, 1);
}

function goToday() {
  cursor.value = startOfMonth(new Date());
}

const dialogOpen = ref(false);
const dialogEvent = ref(null);
const dialogOccurrence = ref(null);
const dialogDefaultDate = ref(null);

function openCreate(date = null) {
  dialogEvent.value = null;
  dialogOccurrence.value = null;
  dialogDefaultDate.value = date;
  dialogOpen.value = true;
}

function openEdit(ev) {
  const masterId = ev.series_id ?? ev.id;
  dialogEvent.value = props.events.find((event) => event.id === masterId) ?? ev;
  dialogOccurrence.value = ev.series_id || ev.occurrence_date
    ? {
        occurrence_date: ev.occurrence_date ?? ev.start_at?.slice(0, 10),
        ...ev,
      }
    : null;
  dialogDefaultDate.value = null;
  dialogOpen.value = true;
}

function formatTime(iso) {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(d);
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <header class="flex flex-wrap items-center justify-between gap-2 rounded-t-xl border border-border bg-card/60 px-4 py-3">
      <h2 class="text-lg font-semibold tracking-tight">{{ monthLabel }}</h2>
      <div class="flex items-center gap-1.5">
        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
          @click="prevMonth"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>
        <Button variant="outline" size="sm" class="h-8" @click="goToday">
          Aujourd'hui
        </Button>
        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
          @click="nextMonth"
        >
          <ChevronRight class="h-4 w-4" />
        </button>
        <Button size="sm" class="ml-1 h-8 gap-1.5" @click="openCreate()">
          <Plus class="h-3.5 w-3.5" />
          Événement
        </Button>
        <a
          :href="route('projects.calendar.ical', projectSlug)"
          class="ml-1 inline-flex h-8 items-center rounded-md border border-border px-3 text-xs font-medium text-foreground hover:bg-muted/50"
        >
          Export iCal
        </a>
      </div>
    </header>

    <div class="overflow-hidden rounded-xl border border-border bg-card/40">
      <div class="grid grid-cols-7 border-b border-border bg-card/60">
        <div
          v-for="label in dayLabels"
          :key="label"
          class="px-2 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-muted-foreground"
        >
          {{ label }}
        </div>
      </div>

      <div class="grid grid-cols-7">
        <button
          v-for="(cell, i) in days"
          :key="cell.iso"
          type="button"
          class="flex min-h-[110px] flex-col items-stretch gap-1 border-border bg-transparent p-1.5 text-left transition-colors hover:bg-muted/30"
          :class="[
            i % 7 !== 6 ? 'border-r' : '',
            i < 35 ? 'border-b' : '',
            !cell.inMonth ? 'bg-card/20' : '',
          ]"
          @click="openCreate(cell.iso)"
        >
          <span
            class="inline-flex h-6 w-6 items-center justify-center self-start rounded-full text-xs"
            :class="
              cell.isToday
                ? 'bg-primary font-semibold text-primary-foreground'
                : cell.inMonth
                  ? 'text-foreground'
                  : 'text-muted-foreground/50'
            "
          >
            {{ cell.date.getDate() }}
          </span>

          <ul class="flex flex-col gap-0.5">
            <li
              v-for="ev in eventsForDay(cell.iso).slice(0, 3)"
              :key="ev.id"
              class="flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white"
              :style="{ backgroundColor: ev.color }"
              @click.stop="openEdit(ev)"
            >
              <Repeat
                v-if="ev.recurrence || ev.series_id"
                class="h-3 w-3 shrink-0 opacity-90"
              />
              <span class="truncate">
                <span v-if="!ev.all_day" class="opacity-90">
                  {{ formatTime(ev.start_at) }}
                </span>
                {{ ev.title }}
              </span>
            </li>
            <li
              v-if="eventsForDay(cell.iso).length > 3"
              class="px-1.5 text-[10px] text-muted-foreground"
            >
              +{{ eventsForDay(cell.iso).length - 3 }} de plus
            </li>
          </ul>
        </button>
      </div>
    </div>

    <EventFormDialog
      v-model:open="dialogOpen"
      :project-slug="projectSlug"
      :event="dialogEvent"
      :occurrence="dialogOccurrence"
      :default-date="dialogDefaultDate"
      :rank-id="rankId"
    />
  </div>
</template>
