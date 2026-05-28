<script setup>
import { computed } from "vue";
import { Pencil, Plus, GripVertical } from "lucide-vue-next";
import { VueDraggable } from "vue-draggable-plus";
import KanbanCard from "./KanbanCard.vue";

const props = defineProps({
  list: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  swimlaneMode: { type: Boolean, default: false },
  readonlyColumn: { type: Boolean, default: false },
  disableTasksDrag: { type: Boolean, default: false },
  bulkMode: { type: Boolean, default: false },
  selectedTaskIds: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "editList",
  "addCard",
  "openCard",
  "tasksReorder",
  "toggleBulkSelect",
]);

const tasks = computed({
  get: () => props.list.tasks,
  set: (value) => emit("tasksReorder", { listId: props.list.id, tasks: value }),
});

const memberNameById = computed(() => {
  const map = new Map();
  for (const member of props.members) {
    map.set(member.id, member.name);
  }
  return map;
});

const swimlaneGroups = computed(() => {
  if (!props.swimlaneMode) {
    return null;
  }

  const groups = new Map();
  for (const task of props.list.tasks) {
    const key = task.assignee_id ?? "none";
    if (!groups.has(key)) {
      groups.set(key, []);
    }
    groups.get(key).push(task);
  }

  const ordered = [];
  if (groups.has("none")) {
    ordered.push({ key: "none", label: "Non assigné", tasks: groups.get("none") });
    groups.delete("none");
  }

  const memberIds = props.members.map((m) => m.id);
  for (const id of memberIds) {
    if (groups.has(id)) {
      ordered.push({
        key: id,
        label: memberNameById.value.get(id) ?? `Membre #${id}`,
        tasks: groups.get(id),
      });
      groups.delete(id);
    }
  }

  for (const [key, laneTasks] of groups.entries()) {
    ordered.push({
      key,
      label: memberNameById.value.get(key) ?? `Membre #${key}`,
      tasks: laneTasks,
    });
  }

  return ordered;
});

function onDragEnd() {
  emit("tasksReorder", { listId: props.list.id, tasks: tasks.value, sync: true });
}

function onSwimlaneDragEnd(groupKey, laneTasks) {
  const other = props.list.tasks.filter((task) => {
    const key = task.assignee_id ?? "none";
    return key !== groupKey;
  });
  emit("tasksReorder", {
    listId: props.list.id,
    tasks: [...other, ...laneTasks],
    sync: true,
  });
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
        v-if="!readonlyColumn"
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
        v-if="!readonlyColumn"
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

    <div v-if="swimlaneMode" class="flex h-full flex-col gap-2 overflow-y-auto p-2">
      <div
        v-for="group in swimlaneGroups"
        :key="group.key"
        class="rounded-md border border-border/50 bg-background/20"
      >
        <div class="border-b border-border/40 px-2 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
          {{ group.label }}
          <span class="ml-1 font-normal">({{ group.tasks.length }})</span>
        </div>
        <VueDraggable
          :model-value="group.tasks"
          group="kanban-cards"
          :animation="180"
          handle=".kanban-card-handle"
          class="flex flex-col gap-2 p-2"
          ghost-class="kanban-ghost"
          :empty-insert-threshold="20"
          :disabled="disableTasksDrag"
          @update:model-value="(value) => onSwimlaneDragEnd(group.key, value)"
        >
          <KanbanCard
            v-for="task in group.tasks"
            :key="task.id"
            :task="task"
            :bulk-mode="bulkMode"
            :selected="selectedTaskIds.includes(task.id)"
            @click="emit('openCard', task)"
            @toggle-select="emit('toggleBulkSelect', $event)"
          />
        </VueDraggable>
      </div>
    </div>

    <VueDraggable
      v-else
      v-model="tasks"
      group="kanban-cards"
      :animation="180"
      handle=".kanban-card-handle"
      class="flex h-full flex-col gap-2 overflow-y-auto p-2"
      ghost-class="kanban-ghost"
      :empty-insert-threshold="20"
      :disabled="disableTasksDrag"
      @end="onDragEnd"
    >
      <KanbanCard
        v-for="task in tasks"
        :key="task.id"
        :task="task"
        :bulk-mode="bulkMode"
        :selected="selectedTaskIds.includes(task.id)"
        @click="emit('openCard', task)"
        @toggle-select="emit('toggleBulkSelect', $event)"
      />
    </VueDraggable>
  </div>
</template>

<style scoped>
.kanban-ghost {
  opacity: 0.4;
}
</style>
