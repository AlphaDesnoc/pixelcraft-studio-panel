<script setup>
import { computed } from "vue";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emits = defineEmits(["update:open"]);

const shortcuts = computed(() => [
  { keys: "Ctrl+K", action: "Recherche globale" },
  { keys: "Ctrl+Shift+M", action: "Ouvrir / fermer le messenger" },
  { keys: "Échap", action: "Fermer modale ou dialogue" },
  { keys: "Ctrl+Entrée", action: "Envoyer un message (chat)" },
  { keys: "N", action: "Nouvelle tâche (page projet)" },
  { keys: "G puis K", action: "Aller au kanban" },
  { keys: "G puis C", action: "Aller au calendrier" },
  { keys: "G puis B", action: "Aller aux bugs" },
  { keys: "/", action: "Focus filtre kanban" },
  { keys: "?", action: "Afficher cette aide" },
]);
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>Raccourcis clavier</DialogTitle>
      </DialogHeader>
      <ul class="divide-y divide-border/60">
        <li
          v-for="row in shortcuts"
          :key="row.keys"
          class="flex items-center justify-between gap-3 py-2.5 text-sm"
        >
          <span class="text-muted-foreground">{{ row.action }}</span>
          <kbd
            class="rounded border border-border bg-muted/50 px-2 py-0.5 font-mono text-[11px] text-foreground"
          >
            {{ row.keys }}
          </kbd>
        </li>
      </ul>
    </DialogContent>
  </Dialog>
</template>
