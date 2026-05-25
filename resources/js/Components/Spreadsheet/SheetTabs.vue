<script setup>
import { ref } from "vue";
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from "lucide-vue-next";

const props = defineProps({
  sheets: { type: Array, required: true },
  activeId: { type: [Number, String], default: null },
});

const emits = defineEmits(["select", "rename", "move", "delete"]);

const editingId = ref(null);
const editingValue = ref("");

function startRename(sheet) {
  editingId.value = sheet.id;
  editingValue.value = sheet.name;
}

function commitRename(sheet) {
  if (editingId.value !== sheet.id) return;
  const value = editingValue.value.trim();
  editingId.value = null;
  if (value && value !== sheet.name) {
    emits("rename", { id: sheet.id, name: value });
  }
}

function cancelRename() {
  editingId.value = null;
}
</script>

<template>
  <div class="flex items-center gap-1.5 overflow-x-auto py-1">
    <div
      v-for="(sheet, idx) in sheets"
      :key="sheet.id"
      class="flex shrink-0 items-center gap-1 rounded-md border border-border bg-card/60 px-2 py-1"
      :class="
        sheet.id === activeId
          ? 'border-primary/60 bg-primary/10 text-primary'
          : 'text-foreground'
      "
    >
      <input
        v-if="editingId === sheet.id"
        v-model="editingValue"
        class="h-6 w-[100px] rounded border border-input bg-background px-1 text-xs outline-none"
        autofocus
        @keydown.enter.prevent="commitRename(sheet)"
        @keydown.escape="cancelRename"
        @blur="commitRename(sheet)"
      />
      <button
        v-else
        type="button"
        class="text-xs font-medium"
        @click="emits('select', sheet.id)"
        @dblclick="startRename(sheet)"
      >
        {{ sheet.name }}
      </button>

      <div class="ml-1 flex items-center gap-0.5">
        <button
          type="button"
          class="inline-flex h-5 w-5 items-center justify-center rounded text-muted-foreground hover:bg-muted/60 hover:text-foreground"
          title="Renommer"
          @click="startRename(sheet)"
        >
          <Pencil class="h-3 w-3" />
        </button>
        <button
          type="button"
          class="inline-flex h-5 w-5 items-center justify-center rounded text-muted-foreground hover:bg-muted/60 hover:text-foreground disabled:opacity-30"
          :disabled="idx === 0"
          title="Déplacer à gauche"
          @click="emits('move', { id: sheet.id, dir: -1 })"
        >
          <ChevronLeft class="h-3 w-3" />
        </button>
        <button
          type="button"
          class="inline-flex h-5 w-5 items-center justify-center rounded text-muted-foreground hover:bg-muted/60 hover:text-foreground disabled:opacity-30"
          :disabled="idx === sheets.length - 1"
          title="Déplacer à droite"
          @click="emits('move', { id: sheet.id, dir: 1 })"
        >
          <ChevronRight class="h-3 w-3" />
        </button>
        <button
          type="button"
          class="inline-flex h-5 w-5 items-center justify-center rounded text-rose-400 hover:bg-rose-500/10 disabled:opacity-30"
          :disabled="sheets.length <= 1"
          title="Supprimer"
          @click="emits('delete', sheet.id)"
        >
          <Trash2 class="h-3 w-3" />
        </button>
      </div>
    </div>
  </div>
</template>
