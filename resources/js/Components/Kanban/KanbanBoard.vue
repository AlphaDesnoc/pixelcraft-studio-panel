<script setup>
import { computed, onMounted, ref, toRef, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Plus } from "lucide-vue-next";
import { VueDraggable } from "vue-draggable-plus";
import { Button } from "@/Components/ui/button";
import KanbanColumn from "./KanbanColumn.vue";
import KanbanFilters from "./KanbanFilters.vue";
import ColumnFormDialog from "./ColumnFormDialog.vue";
import TaskFormDialog from "./TaskFormDialog.vue";
import TaskDetailDialog from "./TaskDetailDialog.vue";
import { canWriteFeature } from "@/lib/projectPermissions.js";
import { useKanbanRealtime } from "@/composables/useKanbanRealtime.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectId: { type: Number, required: true },
  lists: { type: Array, required: true },
  members: { type: Array, required: true },
  priorities: { type: Object, required: true },
  statusKinds: { type: Object, required: true },
  rankId: { type: Number, default: null },
  globalKanban: { type: Boolean, default: false },
  tags: { type: Array, default: () => [] },
  taskTemplates: { type: Array, default: () => [] },
  swimlaneMode: { type: Boolean, default: false },
  myPermissions: { type: Object, default: () => ({}) },
  currentUserId: { type: Number, default: null },
});

const emit = defineEmits(["update:swimlaneMode"]);

const canWrite = computed(() => canWriteFeature(props.myPermissions, "kanban"));

const localLists = ref(cloneLists(props.lists));
const projectIdRef = toRef(props, "projectId");
const { connected: kanbanLive } = useKanbanRealtime(projectIdRef, localLists);
const showArchived = ref(false);
const activeFilters = ref({
  assigneeId: "",
  priority: "",
  due: "all",
  search: "",
  tagIds: [],
  showArchived: false,
  swimlaneByAssignee: false,
});

const filtersStorageKey = computed(
  () => `kanban-filters:${props.projectId}:${props.rankId ?? "global"}`,
);

function loadSavedFilters() {
  try {
    const raw = localStorage.getItem(filtersStorageKey.value);
    if (!raw) return;
    const saved = JSON.parse(raw);
    activeFilters.value = { ...activeFilters.value, ...saved };
    showArchived.value = Boolean(saved.showArchived);
  } catch {
    // ignore
  }
}

onMounted(loadSavedFilters);

watch(
  activeFilters,
  (value) => {
    try {
      localStorage.setItem(filtersStorageKey.value, JSON.stringify(value));
    } catch {
      // ignore
    }
  },
  { deep: true },
);

const kanbanFiltersRef = ref(null);

function taskMatchesFilters(task, filters) {
  if (task.archived_at && !filters.showArchived) {
    return false;
  }

  if (filters.search) {
    const query = filters.search.toLowerCase();
    const haystack = `${task.title ?? ""} ${task.description ?? ""}`.toLowerCase();
    if (!haystack.includes(query)) return false;
  }

  if (filters.assigneeId === "none") {
    if (task.assignee_id) return false;
  } else if (filters.assigneeId) {
    if (String(task.assignee_id) !== String(filters.assigneeId)) return false;
  }

  if (filters.priority && task.priority !== filters.priority) {
    return false;
  }

  if (filters.due === "overdue" && !task.is_overdue) {
    return false;
  }
  if (filters.due === "none" && task.due_date) {
    return false;
  }

  if (filters.tagIds?.length) {
    const taskTagIds = new Set((task.tags ?? []).map((t) => t.id));
    const any = filters.tagIds.some((id) => taskTagIds.has(Number(id)));
    if (!any) return false;
  }

  return true;
}

const filteredLists = computed(() =>
  localLists.value.map((list) => ({
    ...list,
    tasks: list.tasks.filter((task) => taskMatchesFilters(task, activeFilters.value)),
  })),
);

const effectiveSwimlaneMode = computed(
  () => props.swimlaneMode || activeFilters.value.swimlaneByAssignee,
);

watch(
  () => activeFilters.value.swimlaneByAssignee,
  (value) => {
    emit("update:swimlaneMode", value);
  },
);

watch(
  () => props.swimlaneMode,
  (value) => {
    if (value !== activeFilters.value.swimlaneByAssignee) {
      activeFilters.value = {
        ...activeFilters.value,
        swimlaneByAssignee: value,
      };
    }
  },
);

const hasActiveFilters = computed(() => {
  const filters = activeFilters.value;
  return Boolean(
    filters.search ||
      filters.assigneeId ||
      filters.priority ||
      filters.due !== "all" ||
      filters.showArchived ||
      (filters.tagIds?.length ?? 0) > 0,
  );
});

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
  if (!canWrite.value) return;
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
  if (!list) return;

  if (hasActiveFilters.value) {
    const visibleIds = new Set(tasks.map((t) => t.id));
    const hidden = list.tasks.filter((t) => !visibleIds.has(t.id));
    list.tasks = [...tasks, ...hidden];
  } else {
    list.tasks = tasks;
  }

  if (!sync || !canWrite.value) return;

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

function onFiltersUpdate(filters) {
  activeFilters.value = filters;
  showArchived.value = filters.showArchived;
}

function applyMyTasksPreset() {
  if (!props.currentUserId) return;
  activeFilters.value = {
    ...activeFilters.value,
    assigneeId: String(props.currentUserId),
  };
}

defineExpose({
  openNewTask: () => openCreateTask(null),
  focusFilters: () => kanbanFiltersRef.value?.focusSearch?.(),
  applyMyTasksPreset,
});
</script>

<template>
  <div class="flex flex-col gap-3">
    <KanbanFilters
      ref="kanbanFiltersRef"
      :members="members"
      :priorities="priorities"
      :tags="tags"
      :show-archived="showArchived"
      :swimlane-by-assignee="effectiveSwimlaneMode"
      :current-user-id="currentUserId"
      @update:filters="onFiltersUpdate"
      @update:show-archived="showArchived = $event"
      @update:swimlane-by-assignee="emit('update:swimlaneMode', $event)"
    />

    <div class="flex items-center justify-between gap-2">
      <p class="text-xs text-muted-foreground">
        <span
          v-if="kanbanLive"
          class="mr-2 inline-flex items-center gap-1 text-emerald-500"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
          Live
        </span>
        <template v-if="!canWrite">
          Lecture seule — vous pouvez consulter le board sans le modifier.
        </template>
        <template v-else-if="globalKanban">
          Toutes les équipes · 4 colonnes unifiées · Glissez les cartes entre colonnes
        </template>
        <template v-else-if="effectiveSwimlaneMode">
          Swimlanes par assigné · Glissez les cartes entre colonnes
        </template>
        <template v-else-if="showArchived">
          Affichage des cartes archivées inclus
        </template>
        <template v-else>
          Glissez les cartes et colonnes · Cliquez sur une carte pour l'ouvrir
        </template>
      </p>
      <Button
        v-if="!globalKanban && canWrite"
        size="sm"
        variant="outline"
        class="gap-1.5"
        @click="openCreateColumn"
      >
        <Plus class="h-3.5 w-3.5" />
        Colonne
      </Button>
    </div>

    <VueDraggable
      v-if="!globalKanban && canWrite"
      v-model="localLists"
      :animation="180"
      handle=".kanban-column-handle"
      class="flex items-stretch gap-3 overflow-x-auto pb-3"
      ghost-class="kanban-ghost"
      @end="onColumnsDragEnd"
    >
      <KanbanColumn
        v-for="list in filteredLists"
        :key="list.id"
        :list="list"
        :members="members"
        :swimlane-mode="effectiveSwimlaneMode"
        :disable-tasks-drag="hasActiveFilters || !canWrite"
        class="min-h-[420px]"
        @edit-list="openEditColumn"
        @add-card="openCreateTask"
        @open-card="openCard"
        @tasks-reorder="handleTasksReorder"
      />
    </VueDraggable>

    <div
      v-else
      class="flex items-stretch gap-3 overflow-x-auto pb-3"
    >
      <KanbanColumn
        v-for="list in filteredLists"
        :key="list.id"
        :list="list"
        :members="members"
        :swimlane-mode="effectiveSwimlaneMode"
        :readonly-column="globalKanban || !canWrite"
        :disable-tasks-drag="hasActiveFilters || !canWrite"
        class="min-h-[420px]"
        @edit-list="openEditColumn"
        @add-card="openCreateTask"
        @open-card="openCard"
        @tasks-reorder="handleTasksReorder"
      />
    </div>

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
      :task-templates="taskTemplates"
    />

    <TaskDetailDialog
      v-model:open="taskDetailOpen"
      :project-slug="projectSlug"
      :task="openedTask"
      :lists="lists"
      :members="members"
      :priorities="priorities"
      :tags="tags"
      :task-templates="taskTemplates"
      :all-tasks="lists.flatMap((l) => l.tasks ?? [])"
      :read-only="!canWrite"
    />
  </div>
</template>

<style scoped>
.kanban-ghost {
  opacity: 0.4;
}
</style>
