<script setup>
import { ref } from "vue";
import { Bug, Plus } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import BugCard from "./BugCard.vue";
import BugFormDialog from "./BugFormDialog.vue";
import BugDetailDialog from "./BugDetailDialog.vue";

defineProps({
  projectSlug: { type: String, required: true },
  bugs: { type: Array, default: () => [] },
  canReport: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
  priorities: { type: Object, required: true },
  statuses: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  bugRanks: { type: Array, default: () => [] },
});

const dialogOpen = ref(false);
const detailOpen = ref(false);
const editingBug = ref(null);
const viewingBug = ref(null);

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
            Gérez les bugs signalés par l'équipe
          </template>
          <template v-else>
            Signalez un problème — il sera routé vers les ranks de gestion des bugs
          </template>
        </p>
      </div>
      <Button
        v-if="canReport || canManage"
        size="sm"
        class="gap-1.5"
        @click="openCreate"
      >
        <Plus class="h-3.5 w-3.5" />
        Signaler un bug
      </Button>
    </header>

    <div
      v-if="bugs.length === 0"
      class="flex min-h-[160px] items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-6 py-10 text-center text-sm text-muted-foreground"
    >
      <template v-if="canManage">Aucun bug signalé pour le moment.</template>
      <template v-else-if="canReport">Vous n'avez pas encore signalé de bug.</template>
    </div>

    <div v-else class="flex flex-col gap-3">
      <BugCard
        v-for="bug in bugs"
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
    />
  </div>
</template>
