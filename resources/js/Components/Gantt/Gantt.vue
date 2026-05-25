<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { ChevronLeft, ChevronRight, Minus, Plus } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  projectSlug: { type: String, required: true },
  lists: { type: Array, required: true },
  priorities: { type: Object, default: () => ({}) },
});

const MONTH_LABELS = [
  "Janv.", "Févr.", "Mars", "Avr.", "Mai", "Juin",
  "Juil.", "Août", "Sept.", "Oct.", "Nov.", "Déc.",
];
const ZOOMS = [6, 10, 14, 20, 28, 40, 60];
const PAD_BEFORE = 60;
const PAD_AFTER = 240;
const ROW_HEIGHT = 48;
const HEADER_MONTHS_HEIGHT = 24;
const HEADER_DAYS_HEIGHT = 22;
const HEADER_HEIGHT = HEADER_MONTHS_HEIGHT + HEADER_DAYS_HEIGHT;

const priorityColors = {
  low: "#10b981",
  medium: "#3b82f6",
  high: "#f97316",
  urgent: "#ef4444",
};

function startOfDay(d) {
  const r = new Date(d);
  r.setHours(0, 0, 0, 0);
  return r;
}

function daysBetween(a, b) {
  return Math.round((startOfDay(b) - startOfDay(a)) / 86400000);
}

function addDays(d, n) {
  const r = new Date(d);
  r.setDate(r.getDate() + n);
  return r;
}

function isoDate(d) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const today = computed(() => startOfDay(new Date()));

const localOverrides = ref(new Map());

const allTasks = computed(() => {
  const out = [];
  for (const list of props.lists) {
    for (const task of list.tasks) {
      const override = localOverrides.value.get(task.id) ?? {};
      out.push({
        ...task,
        ...override,
        listName: list.name,
        listColor: list.color,
        listKind: list.status_kind,
      });
    }
  }
  return out.sort((a, b) => {
    const aD = a.start_date ? new Date(a.start_date).getTime() : Infinity;
    const bD = b.start_date ? new Date(b.start_date).getTime() : Infinity;
    return aD - bD;
  });
});

const datedTasks = computed(() =>
  allTasks.value.filter((t) => t.start_date && t.due_date),
);
const undatedTasks = computed(() =>
  allTasks.value.filter((t) => !t.start_date || !t.due_date),
);

const range = computed(() => {
  let min = addDays(today.value, -PAD_BEFORE);
  let max = addDays(today.value, PAD_AFTER);

  for (const t of datedTasks.value) {
    const s = startOfDay(new Date(t.start_date));
    const e = startOfDay(new Date(t.due_date));
    if (s < min) min = s;
    if (e > max) max = e;
  }

  min = new Date(min.getFullYear(), min.getMonth(), 1);
  max = new Date(max.getFullYear(), max.getMonth() + 1, 0);

  return { start: min, end: max };
});

const totalDays = computed(
  () => daysBetween(range.value.start, range.value.end) + 1,
);

const pxPerDay = ref(14);
const totalWidth = computed(() => totalDays.value * pxPerDay.value);

function zoomIn() {
  const idx = ZOOMS.indexOf(pxPerDay.value);
  pxPerDay.value = ZOOMS[Math.min(ZOOMS.length - 1, idx + 1)] ?? pxPerDay.value;
}
function zoomOut() {
  const idx = ZOOMS.indexOf(pxPerDay.value);
  pxPerDay.value = ZOOMS[Math.max(0, idx - 1)] ?? pxPerDay.value;
}

const months = computed(() => {
  const out = [];
  let cursor = new Date(range.value.start);
  while (cursor <= range.value.end) {
    const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const monthEnd = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
    const visibleStart = monthStart < range.value.start ? range.value.start : monthStart;
    const visibleEnd = monthEnd > range.value.end ? range.value.end : monthEnd;
    out.push({
      key: `${cursor.getFullYear()}-${cursor.getMonth()}`,
      label: `${MONTH_LABELS[cursor.getMonth()]} ${cursor.getFullYear()}`,
      leftPx: daysBetween(range.value.start, visibleStart) * pxPerDay.value,
      widthPx: (daysBetween(visibleStart, visibleEnd) + 1) * pxPerDay.value,
    });
    cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
  }
  return out;
});

const weekSeparators = computed(() => {
  const out = [];
  let cursor = new Date(range.value.start);
  while (cursor <= range.value.end) {
    if (cursor.getDay() === 1) {
      out.push(daysBetween(range.value.start, cursor) * pxPerDay.value);
    }
    cursor = addDays(cursor, 1);
  }
  return out;
});

const dayTicks = computed(() => {
  const px = pxPerDay.value;
  let step;
  if (px >= 28) step = 1;
  else if (px >= 14) step = 2;
  else if (px >= 10) step = 7;
  else step = 14;

  const out = [];
  const total = totalDays.value;
  for (let i = 0; i < total; i += step) {
    const date = addDays(range.value.start, i);
    if (step >= 7 && date.getDay() !== 1) continue;
    out.push({
      left: i * px,
      width: step * px,
      label: String(date.getDate()),
      isToday: daysBetween(date, today.value) === 0,
      isWeekend: date.getDay() === 0 || date.getDay() === 6,
    });
  }
  return out;
});

function barFor(task) {
  const s = startOfDay(new Date(task.start_date));
  const e = startOfDay(new Date(task.due_date));
  const leftDays = daysBetween(range.value.start, s);
  const widthDays = daysBetween(s, e) + 1;
  return {
    left: leftDays * pxPerDay.value,
    width: Math.max(pxPerDay.value, widthDays * pxPerDay.value),
  };
}

const todayLeft = computed(
  () => daysBetween(range.value.start, today.value) * pxPerDay.value,
);

const timelineRef = ref(null);

function scrollToToday(behavior = "smooth") {
  if (!timelineRef.value) return;
  const target = todayLeft.value - timelineRef.value.clientWidth * 0.35;
  timelineRef.value.scrollTo({ left: Math.max(0, target), behavior });
}
function scrollPrev() {
  timelineRef.value?.scrollBy({
    left: -timelineRef.value.clientWidth * 0.7,
    behavior: "smooth",
  });
}
function scrollNext() {
  timelineRef.value?.scrollBy({
    left: timelineRef.value.clientWidth * 0.7,
    behavior: "smooth",
  });
}

onMounted(() => {
  nextTick(() => scrollToToday("auto"));
});

watch(pxPerDay, () => {
  nextTick(() => scrollToToday("auto"));
});

const dragging = ref(null);

function startDrag(task, mode, event) {
  event.preventDefault();
  event.stopPropagation();
  dragging.value = {
    id: task.id,
    mode,
    startMouseX: event.clientX,
    origStart: startOfDay(new Date(task.start_date)),
    origEnd: startOfDay(new Date(task.due_date)),
  };
  document.addEventListener("mousemove", onDragMove);
  document.addEventListener("mouseup", onDragEnd);
  document.body.style.userSelect = "none";
}

function onDragMove(event) {
  if (!dragging.value) return;
  const dx = event.clientX - dragging.value.startMouseX;
  const deltaDays = Math.round(dx / pxPerDay.value);

  let newStart = new Date(dragging.value.origStart);
  let newEnd = new Date(dragging.value.origEnd);

  if (dragging.value.mode === "move") {
    newStart = addDays(dragging.value.origStart, deltaDays);
    newEnd = addDays(dragging.value.origEnd, deltaDays);
  } else if (dragging.value.mode === "left") {
    newStart = addDays(dragging.value.origStart, deltaDays);
    if (newStart > newEnd) newStart = newEnd;
  } else if (dragging.value.mode === "right") {
    newEnd = addDays(dragging.value.origEnd, deltaDays);
    if (newEnd < newStart) newEnd = newStart;
  }

  localOverrides.value.set(dragging.value.id, {
    start_date: isoDate(newStart),
    due_date: isoDate(newEnd),
  });
  localOverrides.value = new Map(localOverrides.value);
}

function onDragEnd() {
  if (!dragging.value) return;
  const id = dragging.value.id;
  const override = localOverrides.value.get(id);

  document.removeEventListener("mousemove", onDragMove);
  document.removeEventListener("mouseup", onDragEnd);
  document.body.style.userSelect = "";

  dragging.value = null;

  if (!override) return;

  router.put(
    route("projects.tasks.update", [props.projectSlug, id]),
    {
      start_date: override.start_date,
      due_date: override.due_date,
    },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists", "stats", "progress", "byStatus", "byPriority"],
      onFinish: () => {
        localOverrides.value.delete(id);
        localOverrides.value = new Map(localOverrides.value);
      },
    },
  );
}

onBeforeUnmount(() => {
  document.removeEventListener("mousemove", onDragMove);
  document.removeEventListener("mouseup", onDragEnd);
});

function formatDate(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(d);
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <header
      class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-border bg-card/60 px-4 py-3"
    >
      <div class="min-w-0">
        <h2 class="text-base font-semibold tracking-tight">Diagramme de Gantt</h2>
        <p class="text-xs text-muted-foreground">
          S'ouvre sur aujourd'hui · Flèches ou scroll pour voir le passé · Glissez les barres
        </p>
      </div>
      <div class="flex items-center gap-1.5">
        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
          @click="zoomOut"
        >
          <Minus class="h-3.5 w-3.5" />
        </button>
        <span class="min-w-[44px] text-center text-[11px] text-muted-foreground">
          {{ pxPerDay }}px/j
        </span>
        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
          @click="zoomIn"
        >
          <Plus class="h-3.5 w-3.5" />
        </button>
        <Button variant="outline" size="sm" class="h-8" @click="scrollToToday()">
          Aujourd'hui
        </Button>
        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
          @click="scrollPrev"
        >
          <ChevronLeft class="h-3.5 w-3.5" />
        </button>
        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
          @click="scrollNext"
        >
          <ChevronRight class="h-3.5 w-3.5" />
        </button>
      </div>
    </header>

    <div class="overflow-hidden rounded-xl border border-border bg-card/40">
      <div class="flex">
        <div class="w-[260px] shrink-0 border-r border-border bg-card/60">
          <div
            class="flex items-center border-b border-border px-3 text-xs font-semibold text-muted-foreground"
            :style="{ height: `${HEADER_HEIGHT}px` }"
          >
            Tâche
          </div>
          <ul v-if="datedTasks.length > 0">
            <li
              v-for="task in datedTasks"
              :key="task.id"
              class="flex items-center gap-2 border-b border-border px-3"
              :style="{ height: `${ROW_HEIGHT}px` }"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium leading-tight">
                  {{ task.title }}
                </p>
                <div class="mt-0.5 flex items-center gap-1">
                  <span
                    class="rounded-full px-1.5 py-0.5 text-[9px] font-medium text-white"
                    :style="{ backgroundColor: priorityColors[task.priority] }"
                  >
                    {{ priorities[task.priority] ?? task.priority }}
                  </span>
                  <span
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-1.5 py-0.5 text-[9px] text-muted-foreground"
                  >
                    <span
                      class="inline-block h-1.5 w-1.5 rounded-full"
                      :style="{ backgroundColor: task.listColor }"
                    />
                    {{ task.listName }}
                  </span>
                </div>
              </div>
            </li>
          </ul>
          <div
            v-else
            class="flex items-center justify-center px-3 py-12 text-center text-xs text-muted-foreground"
          >
            Aucune tâche avec dates
          </div>
        </div>

        <div ref="timelineRef" class="relative flex-1 overflow-x-auto">
          <div
            class="relative border-b border-border"
            :style="{ width: `${totalWidth}px`, height: `${HEADER_HEIGHT}px` }"
          >
            <div
              class="relative border-b border-border/40"
              :style="{ height: `${HEADER_MONTHS_HEIGHT}px` }"
            >
              <div
                v-for="m in months"
                :key="m.key"
                class="absolute top-0 flex h-full items-center border-r border-border/60 px-2 text-xs font-medium text-muted-foreground"
                :style="{ left: `${m.leftPx}px`, width: `${m.widthPx}px` }"
              >
                {{ m.label }}
              </div>
            </div>
            <div
              class="relative"
              :style="{ height: `${HEADER_DAYS_HEIGHT}px` }"
            >
              <div
                v-for="(t, i) in dayTicks"
                :key="`tick-${i}`"
                class="absolute top-0 flex h-full items-center justify-center text-[10px]"
                :class="
                  t.isToday
                    ? 'font-semibold text-rose-400'
                    : t.isWeekend
                      ? 'text-muted-foreground/40'
                      : 'text-muted-foreground/70'
                "
                :style="{ left: `${t.left}px`, width: `${t.width}px` }"
              >
                {{ t.label }}
              </div>
            </div>
          </div>

          <div class="relative" :style="{ width: `${totalWidth}px` }">
            <div
              v-for="x in weekSeparators"
              :key="`sep-${x}`"
              class="pointer-events-none absolute inset-y-0 w-px bg-border/30"
              :style="{ left: `${x}px` }"
            />

            <div
              v-if="todayLeft >= 0 && todayLeft <= totalWidth && datedTasks.length > 0"
              class="pointer-events-none absolute inset-y-0 z-20 w-px bg-rose-500/90"
              :style="{ left: `${todayLeft}px` }"
            >
              <span
                class="absolute left-0 top-1 -translate-x-1/2 whitespace-nowrap rounded bg-rose-500 px-1.5 py-0.5 text-[10px] font-medium text-white shadow"
              >
                Aujourd'hui
              </span>
            </div>

            <div
              v-for="task in datedTasks"
              :key="task.id"
              class="relative border-b border-border"
              :style="{ height: `${ROW_HEIGHT}px` }"
            >
              <div
                class="absolute top-1/2 z-10 flex h-8 -translate-y-1/2 cursor-grab items-center overflow-hidden rounded-md border shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing"
                :class="dragging?.id === task.id ? 'ring-2 ring-foreground/40' : ''"
                :style="{
                  left: `${barFor(task).left}px`,
                  width: `${barFor(task).width}px`,
                  borderColor: task.listColor,
                  backgroundColor: `${task.listColor}33`,
                }"
                :title="`${formatDate(task.start_date)} → ${formatDate(task.due_date)}`"
                @mousedown="startDrag(task, 'move', $event)"
              >
                <div
                  class="absolute left-0 top-0 z-20 h-full w-1.5 cursor-ew-resize hover:bg-white/10"
                  @mousedown.stop="startDrag(task, 'left', $event)"
                />
                <div
                  class="pointer-events-none absolute inset-y-0 left-0 transition-[width] duration-200"
                  :style="{
                    width: `${task.progress}%`,
                    backgroundColor: task.listColor,
                  }"
                />
                <span
                  class="pointer-events-none relative z-10 mx-auto select-none text-[10px] font-semibold text-white drop-shadow"
                >
                  {{ task.progress }}%
                </span>
                <div
                  class="absolute right-0 top-0 z-20 h-full w-1.5 cursor-ew-resize hover:bg-white/10"
                  @mousedown.stop="startDrag(task, 'right', $event)"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="undatedTasks.length > 0"
        class="border-t border-border bg-card/30 px-4 py-2 text-xs text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ undatedTasks.length }}</span>
        tâche{{ undatedTasks.length > 1 ? "s" : "" }} sans dates ·
        ouvrez-les dans le Kanban pour leur ajouter une période.
      </div>
    </div>

    <p class="text-[11px] text-muted-foreground">
      Couleurs = statut Kanban · Déplacer la barre = dates · Poignées = redimensionner · Progression = colonne Kanban
    </p>
  </div>
</template>
