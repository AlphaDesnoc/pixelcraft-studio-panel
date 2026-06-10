<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { RotateCcw, Trash2, Folder, File } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { confirmDialog } from "@/composables/useConfirm.js";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  nodes: { type: Array, default: () => [] },
});

const emits = defineEmits(["update:open"]);

const sorted = computed(() =>
  [...props.nodes].sort((a, b) => (b.deleted_at ?? "").localeCompare(a.deleted_at ?? "")),
);

const reloadOpts = {
  preserveScroll: true,
  preserveState: true,
  only: ["fileNodes", "trashedFileNodes", "storageUsed", "storageQuota"],
};

function sizeLabel(bytes) {
  if (!bytes) return "";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function fmtDate(iso) {
  if (!iso) return "";
  try {
    return new Date(iso).toLocaleDateString("fr-FR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "";
  }
}

function restore(node) {
  router.post(
    route("projects.files.restore", [props.projectSlug, node.id]),
    {},
    reloadOpts,
  );
}

async function forceDelete(node) {
  if (
    !(await confirmDialog({
      title: "Supprimer définitivement",
      message: `« ${node.name} » sera définitivement supprimé. Cette action est irréversible.`,
    }))
  )
    return;
  router.delete(route("projects.files.force-destroy", [props.projectSlug, node.id]), reloadOpts);
}

async function emptyTrash() {
  if (
    !(await confirmDialog({
      title: "Vider la corbeille",
      message: "Tout le contenu de la corbeille sera définitivement supprimé.",
    }))
  )
    return;
  router.delete(route("projects.files.empty-trash", props.projectSlug), {
    ...reloadOpts,
    onSuccess: () => emits("update:open", false),
  });
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>Corbeille</DialogTitle>
      </DialogHeader>

      <div
        v-if="sorted.length === 0"
        class="py-10 text-center text-sm text-muted-foreground"
      >
        La corbeille est vide.
      </div>

      <div v-else class="max-h-[360px] overflow-y-auto rounded-md border border-border">
        <div
          v-for="node in sorted"
          :key="node.id"
          class="flex items-center gap-3 border-b border-border/60 px-3 py-2 last:border-0"
        >
          <component
            :is="node.type === 'folder' ? Folder : File"
            class="h-4 w-4 shrink-0 text-muted-foreground"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm text-foreground" :title="node.name">
              {{ node.name }}
            </p>
            <p class="text-xs text-muted-foreground">
              Supprimé le {{ fmtDate(node.deleted_at) }}
              <span v-if="node.deleted_by"> · par {{ node.deleted_by.name }}</span>
              <span v-if="sizeLabel(node.size)"> · {{ sizeLabel(node.size) }}</span>
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Restaurer"
            @click="restore(node)"
          >
            <RotateCcw class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-rose-400 hover:bg-rose-500/15 hover:text-rose-300"
            title="Supprimer définitivement"
            @click="forceDelete(node)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between pt-1">
        <Button
          v-if="sorted.length"
          variant="ghost"
          size="sm"
          class="text-rose-400 hover:text-rose-300"
          @click="emptyTrash"
        >
          Vider la corbeille
        </Button>
        <span v-else />
        <Button variant="outline" size="sm" @click="emits('update:open', false)">
          Fermer
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
