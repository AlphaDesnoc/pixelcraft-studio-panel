<script setup>
import { ref } from "vue";
import { Plus } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import NoteCard from "./NoteCard.vue";
import NoteFormDialog from "./NoteFormDialog.vue";

defineProps({
  projectSlug: { type: String, required: true },
  notes: { type: Array, default: () => [] },
  rankId: { type: Number, default: null },
});

const dialogOpen = ref(false);
const editingNote = ref(null);

function openCreate() {
  editingNote.value = null;
  dialogOpen.value = true;
}

function openEdit(note) {
  editingNote.value = note;
  dialogOpen.value = true;
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <header class="flex items-center justify-between gap-2">
      <p class="text-xs text-muted-foreground">Notes de l'espace de travail</p>
      <Button size="sm" class="gap-1.5" @click="openCreate">
        <Plus class="h-3.5 w-3.5" />
        Nouvelle note
      </Button>
    </header>

    <div
      v-if="notes.length === 0"
      class="flex min-h-[120px] items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-6 py-10 text-center text-sm text-muted-foreground"
    >
      Aucune note. Créez votre première note !
    </div>

    <div
      v-else
      class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
    >
      <NoteCard
        v-for="note in notes"
        :key="note.id"
        :project-slug="projectSlug"
        :note="note"
        @edit="openEdit"
      />
    </div>

    <NoteFormDialog
      v-model:open="dialogOpen"
      :project-slug="projectSlug"
      :note="editingNote"
      :rank-id="rankId"
    />
  </div>
</template>
