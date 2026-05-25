<script setup>
import { computed, ref } from "vue";
import { Bold, Eraser, Italic, Trash2 } from "lucide-vue-next";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import ColorPickerPopover from "@/Components/ui/ColorPickerPopover.vue";

const props = defineProps({
  selectionLabel: { type: String, default: "" },
  selectionCount: { type: Number, default: 0 },
  editorValue: { type: String, default: "" },
  anchorCell: { type: Object, default: null },
});

const emits = defineEmits([
  "apply-bg",
  "apply-fg",
  "toggle-bold",
  "toggle-italic",
  "clear-format",
  "clear-values",
  "update:editorValue",
  "commit-editor",
  "navigate-editor",
]);

const fondBtn = ref(null);
const texteBtn = ref(null);
const fondOpen = ref(false);
const texteOpen = ref(false);

const editorProxy = computed({
  get: () => props.editorValue,
  set: (v) => emits("update:editorValue", v),
});

function onEditorKey(e) {
  if (e.key === "Enter") {
    e.preventDefault();
    emits("commit-editor");
    emits("navigate-editor", { dCol: 0, dRow: 1 });
  } else if (e.key === "Tab") {
    e.preventDefault();
    emits("commit-editor");
    emits("navigate-editor", { dCol: e.shiftKey ? -1 : 1, dRow: 0 });
  } else if (e.key === "Escape") {
    e.preventDefault();
    emits("navigate-editor", { dCol: 0, dRow: 0, cancel: true });
  }
}
</script>

<template>
  <div
    class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card/60 px-2.5 py-2"
  >
    <button
      ref="fondBtn"
      type="button"
      class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background/40 px-2 text-xs text-foreground hover:bg-muted/60"
      :class="fondOpen ? 'ring-2 ring-primary' : ''"
      @click="fondOpen = !fondOpen; texteOpen = false"
    >
      <span class="h-3.5 w-3.5 rounded-sm border border-border/60 bg-muted" />
      Fond
    </button>
    <button
      ref="texteBtn"
      type="button"
      class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background/40 px-2 text-xs text-foreground hover:bg-muted/60"
      :class="texteOpen ? 'ring-2 ring-primary' : ''"
      @click="texteOpen = !texteOpen; fondOpen = false"
    >
      <span class="h-3.5 w-3.5 rounded-sm border border-border/60 bg-muted" />
      Texte
    </button>

    <div class="mx-1 h-5 w-px bg-border" />

    <button
      type="button"
      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input bg-background/40 text-foreground hover:bg-muted/60"
      title="Gras"
      @click="emits('toggle-bold')"
    >
      <Bold class="h-3.5 w-3.5" />
    </button>
    <button
      type="button"
      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input bg-background/40 text-foreground hover:bg-muted/60"
      title="Italique"
      @click="emits('toggle-italic')"
    >
      <Italic class="h-3.5 w-3.5" />
    </button>
    <button
      type="button"
      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input bg-background/40 text-foreground hover:bg-muted/60"
      title="Effacer le formatage"
      @click="emits('clear-format')"
    >
      <Eraser class="h-3.5 w-3.5" />
    </button>
    <button
      type="button"
      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input bg-background/40 text-rose-400 hover:bg-rose-500/10 hover:text-rose-300"
      title="Vider les cellules"
      @click="emits('clear-values')"
    >
      <Trash2 class="h-3.5 w-3.5" />
    </button>

    <div class="mx-1 h-5 w-px bg-border" />

    <span
      class="inline-flex h-7 min-w-[40px] items-center justify-center rounded-md bg-foreground/90 px-2 font-mono text-[11px] font-medium text-background"
    >
      {{ selectionLabel || "—" }}
    </span>
    <Input
      v-model="editorProxy"
      placeholder="Valeur ou formule (=SOMME(A1:A5))"
      class="h-8 flex-1 min-w-[280px]"
      @keydown="onEditorKey"
      @blur="emits('commit-editor')"
    />
    <span
      v-if="selectionCount > 1"
      class="text-[11px] text-muted-foreground"
    >
      {{ selectionCount }} cellules — le format s'applique à toute la sélection
    </span>

    <ColorPickerPopover
      v-model:open="fondOpen"
      title="Fond"
      :trigger-ref="fondBtn"
      @apply="(c) => emits('apply-bg', c)"
      @clear="emits('apply-bg', null)"
    />
    <ColorPickerPopover
      v-model:open="texteOpen"
      title="Texte"
      :trigger-ref="texteBtn"
      @apply="(c) => emits('apply-fg', c)"
      @clear="emits('apply-fg', null)"
    />
  </div>
</template>
