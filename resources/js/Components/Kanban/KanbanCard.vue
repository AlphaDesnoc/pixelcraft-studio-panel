<script setup>
import { computed } from "vue";
import { AlignLeft, CalendarDays, GripVertical, ListTodo } from "lucide-vue-next";
import { Progress } from "@/Components/ui/progress";

const props = defineProps({
  task: { type: Object, required: true },
});

const checklistProgress = computed(
  () => props.task.checklist_progress ?? { done: 0, total: 0 },
);

const tagPreview = computed(() => (props.task.tags ?? []).slice(0, 4));

const priorityColors = {
  low: "#10b981",
  medium: "#3b82f6",
  high: "#f97316",
  urgent: "#ef4444",
};

const priorityColor = computed(
  () => priorityColors[props.task.priority] ?? "#3b82f6",
);

const dueLabel = computed(() => {
  if (!props.task.due_date) return null;
  const d = new Date(props.task.due_date);
  if (Number.isNaN(d.getTime())) return null;
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(d);
});
</script>

<template>
  <article
    class="group relative cursor-pointer overflow-hidden rounded-md border bg-card/80 shadow-sm transition-colors hover:border-primary/40"
    :class="task.is_overdue ? 'border-rose-500/60' : 'border-border'"
  >
    <div
      class="absolute inset-x-2 top-1.5 h-1 rounded-full"
      :style="{ backgroundColor: priorityColor }"
    />

    <div class="flex items-start gap-1.5 px-2.5 pb-2.5 pt-3.5">
      <GripVertical
        class="kanban-card-handle mt-0.5 h-3.5 w-3.5 shrink-0 cursor-grab text-muted-foreground/40 active:cursor-grabbing"
      />
      <div class="min-w-0 flex-1">
        <h4 class="text-[13px] font-medium leading-snug text-foreground">
          {{ task.title }}
        </h4>

        <div
          v-if="tagPreview.length"
          class="mt-1.5 flex flex-wrap gap-1"
        >
          <span
            v-for="tag in tagPreview"
            :key="tag.id"
            class="max-w-[7rem] truncate rounded-full border border-border/70 px-1.5 py-px text-[10px] font-medium text-foreground"
            :style="
              tag.color ? { borderColor: tag.color, color: tag.color } : undefined
            "
          >
            {{ tag.name }}
          </span>
          <span
            v-if="(task.tags ?? []).length > 4"
            class="text-[10px] text-muted-foreground"
          >
            +{{ (task.tags ?? []).length - 4 }}
          </span>
        </div>

        <div
          v-if="checklistProgress.total > 0"
          class="mt-1.5 space-y-0.5"
        >
          <div class="flex items-center gap-1 text-[10px] text-muted-foreground">
            <ListTodo class="h-3 w-3 shrink-0" />
            <span>
              {{ checklistProgress.done }}/{{ checklistProgress.total }} sous-tâches
            </span>
          </div>
          <Progress
            class="h-1"
            :value="checklistProgress.done"
            :max="checklistProgress.total"
          />
        </div>

        <div
          v-if="task.description || dueLabel || task.progress > 0"
          class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground"
        >
          <AlignLeft v-if="task.description" class="h-3 w-3" />
          <span v-if="dueLabel" class="inline-flex items-center gap-0.5">
            <CalendarDays class="h-3 w-3" />
            <span :class="task.is_overdue ? 'font-medium text-rose-400' : ''">
              {{ dueLabel }}
            </span>
          </span>
          <span
            v-if="task.is_overdue"
            class="inline-flex items-center rounded-full bg-rose-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-rose-400"
          >
            En retard
          </span>
          <span
            v-if="task.progress > 0"
            class="inline-flex items-center gap-0.5 rounded-full bg-muted px-1.5 py-0.5"
          >
            {{ task.progress }}%
          </span>
        </div>
      </div>
    </div>
  </article>
</template>
