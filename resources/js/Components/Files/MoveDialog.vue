<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Home } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import MoveFolderRow from "./MoveFolderRow.vue";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  nodes: { type: Array, required: true },
  node: { type: Object, default: null },
  nodeIds: { type: Array, default: null },
});

const isBulk = computed(() => Array.isArray(props.nodeIds) && props.nodeIds.length > 0);

const emits = defineEmits(["update:open"]);

const selected = ref(null);
const processing = ref(false);
const expanded = ref(new Set());

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    selected.value = null;
    processing.value = false;
    expanded.value = new Set();
  },
);

const folders = computed(() => props.nodes.filter((n) => n.type === "folder"));

const excludeId = computed(() =>
  !isBulk.value && props.node && props.node.type === "folder" ? props.node.id : null,
);

const rootChildren = computed(() =>
  folders.value.filter(
    (n) => (n.parent_id ?? null) === null && n.id !== excludeId.value,
  ),
);

function toggle(id) {
  const next = new Set(expanded.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  expanded.value = next;
}

function select(id) {
  selected.value = id;
}

function close() {
  emits("update:open", false);
}

function submit() {
  const options = {
    preserveScroll: true,
    preserveState: true,
    only: ["fileNodes"],
    onSuccess: close,
    onFinish: () => (processing.value = false),
  };

  if (isBulk.value) {
    processing.value = true;
    router.post(
      route("projects.files.bulk-move", props.projectSlug),
      { ids: props.nodeIds, parent_id: selected.value },
      options,
    );
    return;
  }

  if (!props.node) return;
  if (selected.value === (props.node.parent_id ?? null)) {
    close();
    return;
  }
  processing.value = true;
  router.post(
    route("projects.files.move", [props.projectSlug, props.node.id]),
    { parent_id: selected.value },
    options,
  );
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>
          Déplacer
          <span v-if="isBulk" class="text-muted-foreground">— {{ nodeIds.length }} éléments</span>
          <span v-else-if="node" class="text-muted-foreground">— {{ node.name }}</span>
        </DialogTitle>
      </DialogHeader>

      <div
        class="max-h-[320px] overflow-y-auto rounded-md border border-border bg-card/30 p-1"
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-muted/60"
          :class="selected === null ? 'bg-primary/15 text-primary' : 'text-foreground'"
          @click="select(null)"
        >
          <Home class="h-3.5 w-3.5" />
          Racine
        </button>

        <ul class="ml-2 mt-1 flex flex-col gap-0.5">
          <li v-for="folder in rootChildren" :key="folder.id">
            <MoveFolderRow
              :folder="folder"
              :folders="folders"
              :expanded="expanded"
              :selected="selected"
              :depth="0"
              :exclude-id="excludeId"
              @toggle="toggle"
              @select="select"
            />
          </li>
        </ul>
      </div>

      <div class="flex items-center justify-end gap-2 pt-1">
        <Button type="button" variant="ghost" @click="close">Annuler</Button>
        <Button :disabled="processing" @click="submit">
          {{ processing ? "…" : "Déplacer ici" }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
