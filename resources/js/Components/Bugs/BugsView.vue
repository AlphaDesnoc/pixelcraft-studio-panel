<script setup>
import { computed, ref } from "vue";
import { Bug, Download, Filter, Plus } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import BugCard from "./BugCard.vue";
import BugFormDialog from "./BugFormDialog.vue";
import BugDetailDialog from "./BugDetailDialog.vue";

const props = defineProps({
  projectSlug: { type: String, required: true },
  bugs: { type: Array, default: () => [] },
  canReport: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
  priorities: { type: Object, required: true },
  statuses: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  bugRanks: { type: Array, default: () => [] },
  taskOptions: { type: Array, default: () => [] },
});

const dialogOpen = ref(false);
const detailOpen = ref(false);
const editingBug = ref(null);
const viewingBug = ref(null);
const onlyWithoutTask = ref(false);

const visibleBugs = computed(() =>
  onlyWithoutTask.value ? props.bugs.filter((b) => !b.task_id) : props.bugs,
);

function openCreate() {
  editingBug.value = null;
  dialogOpen.value = true;
}

function openEdit(bug) {
  editingBug.value = bug;
  dialogOpen.value = true;
}

function openDetail(bug) {
  viewingBug.value = bug;
  detailOpen.value = true;
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="flex items-center gap-2 text-base font-semibold text-foreground">
          <Bug class="h-4 w-4" />
          Signalement de bugs
        </h2>
        <p class="text-xs text-muted-foreground">
          <template v-if="canManage">
            Gérez les bugs assignés à ce rank
          </template>
          <template v-else-if="canReport">
            Les bugs non assignés sont visibles par tous. Une fois routés vers un rank, seul le rapporteur les voit ici.
          </template>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border px-3 text-xs font-medium transition-colors"
          :class="
            onlyWithoutTask
              ? 'border-primary bg-primary/10 text-foreground'
              : 'border-border bg-card text-muted-foreground hover:bg-muted/50'
          "
          @click="onlyWithoutTask = !onlyWithoutTask"
        >
          <Filter class="h-3.5 w-3.5" />
          Sans tâche liée
        </button>
        <a
          v-if="bugs.length"
          :href="route('projects.export.bugs', projectSlug)"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground hover:bg-muted/50"
        >
          <Download class="h-3.5 w-3.5" />
          Export CSV
        </a>
        <Button
          v-if="canReport || canManage"
          size="sm"
          class="gap-1.5"
          @click="openCreate"
        >
          <Plus class="h-3.5 w-3.5" />
          Signaler un bug
        </Button>
      </div>
    </header>

    <div
      v-if="visibleBugs.length === 0"
      class="flex min-h-[160px] items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-6 py-10 text-center text-sm text-muted-foreground"
    >
      <template v-if="canManage">Aucun bug assigné à ce rank.</template>
      <template v-else-if="canReport">Aucun bug en attente de routage.</template>
    </div>

    <div v-else class="flex flex-col gap-3">
      <BugCard
        v-for="bug in visibleBugs"
        :key="bug.id"
        :project-slug="projectSlug"
        :bug="bug"
        :can-manage="canManage"
        :priorities="priorities"
        :statuses="statuses"
        @edit="openEdit"
        @open="openDetail"
      />
    </div>

    <BugFormDialog
      v-model:open="dialogOpen"
      :project-slug="projectSlug"
      :bug="editingBug"
      :can-manage="canManage"
      :priorities="priorities"
      :statuses="statuses"
      :members="members"
      :bug-ranks="bugRanks"
    />

    <BugDetailDialog
      v-model:open="detailOpen"
      :project-slug="projectSlug"
      :bug="viewingBug"
      :priorities="priorities"
      :statuses="statuses"
      :task-options="taskOptions"
      :can-manage="canManage"
    />
  </div>
</template>
