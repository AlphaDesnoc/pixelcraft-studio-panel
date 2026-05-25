<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Plus } from "lucide-vue-next";
import { VueDraggable } from "vue-draggable-plus";
import { Button } from "@/Components/ui/button";
import KanbanColumn from "./KanbanColumn.vue";
import ColumnFormDialog from "./ColumnFormDialog.vue";
import TaskFormDialog from "./TaskFormDialog.vue";
import TaskDetailDialog from "./TaskDetailDialog.vue";

const props = defineProps({
  projectSlug: { type: String, required: true },
  lists: { type: Array, required: true },
  members: { type: Array, required: true },
  priorities: { type: Object, required: true },
  statusKinds: { type: Object, required: true },
  rankId: { type: Number, default: null },
});

const localLists = ref(cloneLists(props.lists));

function cloneLists(lists) {
  return lists.map((l) => ({
    ...l,
    tasks: l.tasks.map((t) => ({ ...t })),
  }));
}

watch(
  () => props.lists,
  (next) => {
    localLists.value = cloneLists(next);
  },
  { deep: true },
);

const columnDialogOpen = ref(false);
const columnInEdit = ref(null);

function openCreateColumn() {
  columnInEdit.value = null;
  columnDialogOpen.value = true;
}

function openEditColumn(list) {
  columnInEdit.value = list;
  columnDialogOpen.value = true;
}

const taskFormOpen = ref(false);
const taskFormListId = ref(null);

function openCreateTask(list) {
  taskFormListId.value = list?.id ?? localLists.value[0]?.id ?? null;
  taskFormOpen.value = true;
}

const taskDetailOpen = ref(false);
const openedTask = ref(null);

function openCard(task) {
  openedTask.value = task;
  taskDetailOpen.value = true;
}

watch(
  () => props.lists,
  (next) => {
    if (!openedTask.value) return;
    for (const l of next) {
      const found = l.tasks.find((t) => t.id === openedTask.value.id);
      if (found) {
        openedTask.value = found;
        return;
      }
    }
    taskDetailOpen.value = false;
    openedTask.value = null;
  },
  { deep: true },
);

function onColumnsDragEnd() {
  router.post(
    route("projects.lists.reorder", props.projectSlug),
    { order: localLists.value.map((l) => l.id), rank_id: props.rankId },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
    },
  );
}

function handleTasksReorder({ listId, tasks, sync }) {
  const list = localLists.value.find((l) => l.id === listId);
  if (list) list.tasks = tasks;

  if (!sync) return;

  for (const l of localLists.value) {
    for (const t of l.tasks) {
      if (t.list_id !== l.id) {
        t.list_id = l.id;
        router.post(
          route("projects.tasks.move", [props.projectSlug, t.id]),
          {
            list_id: l.id,
            order: l.tasks.map((tt) => tt.id),
          },
          {
            preserveScroll: true,
            preserveState: true,
            only: ["lists", "stats", "progress", "byStatus", "byPriority"],
          },
        );
        return;
      }
    }
  }

  const moved = localLists.value.find((l) => l.id === listId);
  if (moved) {
    router.post(
      route("projects.lists.reorder", props.projectSlug),
      { order: localLists.value.map((l) => l.id) },
      { preserveScroll: true, preserveState: true, only: [] },
    );

    if (moved.tasks.length > 0) {
      router.post(
        route("projects.tasks.move", [props.projectSlug, moved.tasks[0].id]),
        {
          list_id: moved.id,
          order: moved.tasks.map((t) => t.id),
        },
        {
          preserveScroll: true,
          preserveState: true,
          only: ["lists", "stats", "progress", "byStatus", "byPriority"],
        },
      );
    }
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center justify-between gap-2">
      <p class="text-xs text-muted-foreground">
        Glissez les cartes et colonnes · Cliquez sur une carte pour l'ouvrir
      </p>
      <Button size="sm" variant="outline" class="gap-1.5" @click="openCreateColumn">
        <Plus class="h-3.5 w-3.5" />
        Colonne
      </Button>
    </div>

    <VueDraggable
      v-model="localLists"
      :animation="180"
      handle=".kanban-column-handle"
      class="flex items-stretch gap-3 overflow-x-auto pb-3"
      ghost-class="kanban-ghost"
      @end="onColumnsDragEnd"
    >
      <KanbanColumn
        v-for="list in localLists"
        :key="list.id"
        :list="list"
        class="min-h-[420px]"
        @edit-list="openEditColumn"
        @add-card="openCreateTask"
        @open-card="openCard"
        @tasks-reorder="handleTasksReorder"
      />
    </VueDraggable>

    <ColumnFormDialog
      v-model:open="columnDialogOpen"
      :project-slug="projectSlug"
      :list="columnInEdit"
      :status-kinds="statusKinds"
      :rank-id="rankId"
    />

    <TaskFormDialog
      v-model:open="taskFormOpen"
      :project-slug="projectSlug"
      :default-list-id="taskFormListId"
      :lists="lists"
      :members="members"
      :priorities="priorities"
    />

    <TaskDetailDialog
      v-model:open="taskDetailOpen"
      :project-slug="projectSlug"
      :task="openedTask"
      :lists="lists"
      :members="members"
      :priorities="priorities"
    />
  </div>
</template>

<style scoped>
.kanban-ghost {
  opacity: 0.4;
}
</style>
