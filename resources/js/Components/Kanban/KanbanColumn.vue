<script setup>
import { computed } from "vue";
import { Pencil, Plus, GripVertical } from "lucide-vue-next";
import { VueDraggable } from "vue-draggable-plus";
import KanbanCard from "./KanbanCard.vue";

const props = defineProps({
  list: { type: Object, required: true },
});

const emit = defineEmits([
  "editList",
  "addCard",
  "openCard",
  "tasksReorder",
]);

const tasks = computed({
  get: () => props.list.tasks,
  set: (value) => emit("tasksReorder", { listId: props.list.id, tasks: value }),
});

function onDragEnd() {
  emit("tasksReorder", { listId: props.list.id, tasks: tasks.value, sync: true });
}
</script>

<template>
  <div
    class="kanban-column flex h-full w-[280px] shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-card/40"
  >
    <header
      class="flex items-center gap-1.5 border-b border-border px-2.5 py-2"
    >
      <GripVertical
        class="kanban-column-handle h-3.5 w-3.5 shrink-0 cursor-grab text-muted-foreground/50 active:cursor-grabbing"
      />
      <span
        class="inline-block h-2 w-2 shrink-0 rounded-full"
        :style="{ backgroundColor: list.color }"
      />
      <h3 class="flex-1 truncate text-sm font-semibold">{{ list.name }}</h3>
      <span
        class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
      >
        {{ list.tasks.length }}
      </span>
      <button
        type="button"
        class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
        @click="emit('editList', list)"
      >
        <Pencil class="h-3.5 w-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
        @click="emit('addCard', list)"
      >
        <Plus class="h-3.5 w-3.5" />
      </button>
    </header>

    <VueDraggable
      v-model="tasks"
      group="kanban-cards"
      :animation="180"
      handle=".kanban-card-handle"
      class="flex h-full flex-col gap-2 overflow-y-auto p-2"
      ghost-class="kanban-ghost"
      :empty-insert-threshold="20"
      @end="onDragEnd"
    >
      <KanbanCard
        v-for="task in tasks"
        :key="task.id"
        :task="task"
        @click="emit('openCard', task)"
      />
    </VueDraggable>
  </div>
</template>

<style scoped>
.kanban-ghost {
  opacity: 0.4;
}
</style>
