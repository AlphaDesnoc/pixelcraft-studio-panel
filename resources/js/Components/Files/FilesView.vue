<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { ChevronRight, FolderPlus, Home, Upload } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { confirmDialog } from "@/composables/useConfirm.js";
import FileNodeCard from "./FileNodeCard.vue";
import NodeFormDialog from "./NodeFormDialog.vue";
import MoveDialog from "./MoveDialog.vue";

const props = defineProps({
  projectSlug: { type: String, required: true },
  nodes: { type: Array, default: () => [] },
  rankId: { type: Number, default: null },
});

const currentParentId = ref(null);
const draggedNode = ref(null);
const dragTargetId = ref(null);
const isDraggingDesktop = ref(false);
const fileInputRef = ref(null);

const dialogMode = ref("create-folder");
const dialogOpen = ref(false);
const dialogNode = ref(null);

const moveOpen = ref(false);
const moveNode = ref(null);

const breadcrumb = computed(() => {
  const path = [];
  let cur = currentParentId.value;
  while (cur != null) {
    const node = props.nodes.find((n) => n.id === cur);
    if (!node) break;
    path.unshift(node);
    cur = node.parent_id;
  }
  return path;
});

const currentNodes = computed(() =>
  props.nodes.filter((n) => (n.parent_id ?? null) === currentParentId.value),
);

function openNode(node) {
  if (node.type === "folder") {
    currentParentId.value = node.id;
  } else {
    window.open(
      route("projects.files.download", [props.projectSlug, node.id]),
      "_blank",
    );
  }
}

function navigateTo(parentId) {
  currentParentId.value = parentId;
}

function openCreateFolder() {
  dialogMode.value = "create-folder";
  dialogNode.value = null;
  dialogOpen.value = true;
}

function openRename(node) {
  dialogMode.value = "rename";
  dialogNode.value = node;
  dialogOpen.value = true;
}

function openMove(node) {
  moveNode.value = node;
  moveOpen.value = true;
}

async function deleteNode(node) {
  const message =
    node.type === "folder"
      ? "Ce dossier et tout son contenu seront définitivement supprimés."
      : "Ce fichier sera définitivement supprimé.";
  if (
    !(await confirmDialog({
      title: node.type === "folder" ? "Supprimer le dossier" : "Supprimer le fichier",
      message,
    }))
  )
    return;
  router.delete(
    route("projects.files.destroy", [props.projectSlug, node.id]),
    { preserveScroll: true, preserveState: true, only: ["fileNodes"] },
  );
}

function moveNodeTo(nodeId, parentId) {
  router.post(
    route("projects.files.move", [props.projectSlug, nodeId]),
    { parent_id: parentId },
    { preserveScroll: true, preserveState: true, only: ["fileNodes"] },
  );
}

function uploadFiles(filesList, parentId = null) {
  if (!filesList || filesList.length === 0) return;
  const form = new FormData();
  Array.from(filesList).forEach((f) => form.append("files[]", f));
  if (parentId != null) form.append("parent_id", String(parentId));
  if (props.rankId != null) form.append("rank_id", String(props.rankId));
  router.post(route("projects.files.upload", props.projectSlug), form, {
    preserveScroll: true,
    preserveState: true,
    only: ["fileNodes"],
    forceFormData: true,
  });
}

function onImportClick() {
  fileInputRef.value?.click();
}

function onFileInputChange(e) {
  const files = e.target.files;
  uploadFiles(files, currentParentId.value);
  e.target.value = "";
}

function onZoneDragOver(e) {
  if (!e.dataTransfer) return;
  if (e.dataTransfer.types.includes("Files")) {
    e.preventDefault();
    e.dataTransfer.dropEffect = "copy";
    isDraggingDesktop.value = true;
    dragTargetId.value = null;
  } else if (e.dataTransfer.types.includes("application/x-file-node-id")) {
    e.preventDefault();
    e.dataTransfer.dropEffect = "move";
  }
}

function onZoneDragLeave(e) {
  if (e.currentTarget === e.target) {
    isDraggingDesktop.value = false;
  }
}

function onZoneDrop(e) {
  e.preventDefault();
  isDraggingDesktop.value = false;
  dragTargetId.value = null;
  if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    uploadFiles(e.dataTransfer.files, currentParentId.value);
    return;
  }
  const id = e.dataTransfer.getData("application/x-file-node-id");
  if (id) {
    const nodeId = parseInt(id, 10);
    const node = props.nodes.find((n) => n.id === nodeId);
    if (!node) return;
    if ((node.parent_id ?? null) !== currentParentId.value) {
      moveNodeTo(nodeId, currentParentId.value);
    }
  }
}

function onCardDragStart(node) {
  draggedNode.value = node;
}

function onCardDragEnd() {
  draggedNode.value = null;
  dragTargetId.value = null;
}

function onCardDragOverFolder(targetNode) {
  if (!targetNode) return;
  if (draggedNode.value && draggedNode.value.id === targetNode.id) return;
  dragTargetId.value = targetNode.id;
}

function onCardDropOnFolder({ target, event }) {
  dragTargetId.value = null;
  if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
    uploadFiles(event.dataTransfer.files, target.id);
    return;
  }
  const id = event.dataTransfer.getData("application/x-file-node-id");
  if (id) {
    const nodeId = parseInt(id, 10);
    if (nodeId === target.id) return;
    moveNodeTo(nodeId, target.id);
  }
}

function onBreadcrumbDragOver(e, parentId) {
  if (!draggedNode.value && !e.dataTransfer.types.includes("Files")) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = e.dataTransfer.types.includes("Files")
    ? "copy"
    : "move";
}

function onBreadcrumbDrop(e, parentId) {
  e.preventDefault();
  if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    uploadFiles(e.dataTransfer.files, parentId);
    return;
  }
  const id = e.dataTransfer.getData("application/x-file-node-id");
  if (id) {
    const nodeId = parseInt(id, 10);
    const node = props.nodes.find((n) => n.id === nodeId);
    if (!node) return;
    if ((node.parent_id ?? null) !== parentId) {
      moveNodeTo(nodeId, parentId);
    }
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="flex items-center gap-2 text-base font-semibold text-foreground">
          <span>Fichiers</span>
        </h2>
        <p class="text-xs text-muted-foreground">
          Glissez un fichier sur un dossier · ou utilisez l'icône Déplacer
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" class="gap-1.5" @click="openCreateFolder">
          <FolderPlus class="h-3.5 w-3.5" />
          Nouveau dossier
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="onImportClick">
          <Upload class="h-3.5 w-3.5" />
          Importer
        </Button>
        <input
          ref="fileInputRef"
          type="file"
          multiple
          class="hidden"
          @change="onFileInputChange"
        />
      </div>
    </header>

    <nav class="flex items-center gap-1 text-sm">
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-primary transition-colors hover:bg-muted/60"
        :class="currentParentId === null ? 'bg-muted/40' : ''"
        @click="navigateTo(null)"
        @dragover="(e) => onBreadcrumbDragOver(e, null)"
        @drop="(e) => onBreadcrumbDrop(e, null)"
      >
        <Home class="h-3.5 w-3.5" />
        Racine
      </button>
      <template v-for="(node, idx) in breadcrumb" :key="node.id">
        <ChevronRight class="h-3.5 w-3.5 text-muted-foreground" />
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-md px-2 py-1 transition-colors hover:bg-muted/60"
          :class="idx === breadcrumb.length - 1 ? 'text-primary' : 'text-foreground'"
          @click="navigateTo(node.id)"
          @dragover="(e) => onBreadcrumbDragOver(e, node.id)"
          @drop="(e) => onBreadcrumbDrop(e, node.id)"
        >
          {{ node.name }}
        </button>
      </template>
    </nav>

    <div
      class="relative flex min-h-[420px] flex-wrap gap-3 rounded-xl border border-border bg-card/30 p-4 transition-colors"
      :class="isDraggingDesktop ? 'border-primary bg-primary/5' : ''"
      @dragover="onZoneDragOver"
      @dragleave="onZoneDragLeave"
      @drop="onZoneDrop"
    >
      <FileNodeCard
        v-for="node in currentNodes"
        :key="node.id"
        :node="node"
        :is-drag-target="dragTargetId === node.id"
        @open="openNode"
        @rename="openRename"
        @move="openMove"
        @delete="deleteNode"
        @drag-start="onCardDragStart"
        @drag-end="onCardDragEnd"
        @dragover-folder="onCardDragOverFolder"
        @drop-on-folder="onCardDropOnFolder"
      />

      <p
        v-if="currentNodes.length === 0"
        class="pointer-events-none absolute inset-0 flex items-center justify-center text-center text-sm text-muted-foreground"
      >
        Glissez-déposez des fichiers ou créez un dossier
      </p>
    </div>

    <NodeFormDialog
      v-model:open="dialogOpen"
      :mode="dialogMode"
      :project-slug="projectSlug"
      :node="dialogNode"
      :parent-id="currentParentId"
      :rank-id="rankId"
    />

    <MoveDialog
      v-model:open="moveOpen"
      :project-slug="projectSlug"
      :nodes="nodes"
      :node="moveNode"
    />
  </div>
</template>
