<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import {
  ArrowDownUp,
  ChevronRight,
  Download,
  FolderInput,
  FolderPlus,
  FolderUp,
  Home,
  LayoutGrid,
  List,
  Search,
  Trash,
  Trash2,
  Upload,
  X,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { confirmDialog } from "@/composables/useConfirm.js";
import { useFileUpload } from "@/composables/useFileUpload.js";
import FileNodeCard from "./FileNodeCard.vue";
import NodeFormDialog from "./NodeFormDialog.vue";
import MoveDialog from "./MoveDialog.vue";
import FilePreviewDialog from "./FilePreviewDialog.vue";
import FileContextMenu from "./FileContextMenu.vue";
import FileDetailsPanel from "./FileDetailsPanel.vue";
import TrashDialog from "./TrashDialog.vue";
import ShareDialog from "./ShareDialog.vue";
import { isViewable } from "./fileKind.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  nodes: { type: Array, default: () => [] },
  trashedNodes: { type: Array, default: () => [] },
  storageUsed: { type: Number, default: 0 },
  storageQuota: { type: Number, default: 0 },
  rankId: { type: Number, default: null },
});

const currentParentId = ref(null);
const draggedNode = ref(null);
const dragTargetId = ref(null);
const isDraggingDesktop = ref(false);
const fileInputRef = ref(null);
const folderInputRef = ref(null);
const zoneRef = ref(null);

const dialogMode = ref("create-folder");
const dialogOpen = ref(false);
const dialogNode = ref(null);

const moveOpen = ref(false);
const moveNode = ref(null);
const moveIds = ref(null);

const previewOpen = ref(false);
const previewIndex = ref(0);

const trashOpen = ref(false);
const shareOpen = ref(false);
const shareNode = ref(null);
const detailsOpen = ref(false);

const { uploadState, uploadEntries, uploadFileList, entriesFromDataTransfer, dismiss } =
  useFileUpload(props.projectSlug, () => props.rankId);

// --- Recherche / tri / vue ---
const searchQuery = ref("");
const sortField = ref("name");
const sortDir = ref("asc");
const viewMode = ref("grid");

const sortOptions = [
  { field: "name", label: "Nom" },
  { field: "size", label: "Taille" },
  { field: "date", label: "Date" },
  { field: "type", label: "Type" },
];

function setSort(field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortField.value = field;
    sortDir.value = "asc";
  }
}

// --- Sélection ---
const selectedIds = ref([]);
const lastSelectedId = ref(null);
const activeNodeId = ref(null);
const selectionActive = computed(() => selectedIds.value.length > 0);

function isSelected(id) {
  return selectedIds.value.includes(id);
}

function toggleSelect(node) {
  if (isSelected(node.id)) {
    selectedIds.value = selectedIds.value.filter((id) => id !== node.id);
  } else {
    selectedIds.value = [...selectedIds.value, node.id];
  }
  lastSelectedId.value = node.id;
  activeNodeId.value = node.id;
}

function rangeSelect(node) {
  const list = currentNodes.value;
  const anchorId = lastSelectedId.value ?? node.id;
  const i1 = list.findIndex((n) => n.id === anchorId);
  const i2 = list.findIndex((n) => n.id === node.id);
  if (i1 < 0 || i2 < 0) {
    toggleSelect(node);
    return;
  }
  const [a, b] = i1 < i2 ? [i1, i2] : [i2, i1];
  selectedIds.value = list.slice(a, b + 1).map((n) => n.id);
  activeNodeId.value = node.id;
}

function clearSelection() {
  selectedIds.value = [];
}

function selectAll() {
  selectedIds.value = currentNodes.value.map((n) => n.id);
}

const selectedNodes = computed(() =>
  props.nodes.filter((n) => selectedIds.value.includes(n.id)),
);
const detailsNode = computed(
  () => props.nodes.find((n) => n.id === activeNodeId.value) ?? selectedNodes.value[0] ?? null,
);

// --- Arborescence ---
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

const currentNodes = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  let list = props.nodes.filter(
    (n) => (n.parent_id ?? null) === currentParentId.value,
  );
  if (q) list = list.filter((n) => n.name.toLowerCase().includes(q));

  const dir = sortDir.value === "asc" ? 1 : -1;
  return [...list].sort((a, b) => {
    if (a.type !== b.type) return a.type === "folder" ? -1 : 1;
    let cmp = 0;
    switch (sortField.value) {
      case "size":
        cmp = (a.size ?? 0) - (b.size ?? 0);
        break;
      case "date":
        cmp = (a.created_at ?? "").localeCompare(b.created_at ?? "");
        break;
      case "type":
        cmp = (a.mime ?? "").localeCompare(b.mime ?? "");
        break;
      default:
        cmp = 0;
    }
    if (cmp === 0) cmp = a.name.localeCompare(b.name);
    return cmp * dir;
  });
});

const viewableNodes = computed(() => currentNodes.value.filter(isViewable));

// --- Virtualisation (vue liste) ---
const ROW_HEIGHT = 44;
const VIRTUAL_THRESHOLD = 80;
const scrollTop = ref(0);
const viewportH = ref(600);
const virtualActive = computed(
  () => viewMode.value === "list" && currentNodes.value.length > VIRTUAL_THRESHOLD,
);
const visibleRange = computed(() => {
  if (!virtualActive.value) return { start: 0, end: currentNodes.value.length, padTop: 0, padBottom: 0 };
  const overscan = 6;
  const start = Math.max(0, Math.floor(scrollTop.value / ROW_HEIGHT) - overscan);
  const count = Math.ceil(viewportH.value / ROW_HEIGHT) + overscan * 2;
  const end = Math.min(currentNodes.value.length, start + count);
  return {
    start,
    end,
    padTop: start * ROW_HEIGHT,
    padBottom: (currentNodes.value.length - end) * ROW_HEIGHT,
  };
});
const visibleNodes = computed(() =>
  currentNodes.value.slice(visibleRange.value.start, visibleRange.value.end),
);
function onListScroll(e) {
  scrollTop.value = e.target.scrollTop;
  viewportH.value = e.target.clientHeight;
}

// --- Quota ---
function formatBytes(bytes) {
  if (!bytes) return "0 o";
  if (bytes < 1024) return `${bytes} o`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} Mo`;
  return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} Go`;
}
const quotaPercent = computed(() =>
  props.storageQuota > 0
    ? Math.min(100, Math.round((props.storageUsed / props.storageQuota) * 100))
    : 0,
);

// --- Navigation ---
function openNode(node) {
  if (node.type === "folder") {
    currentParentId.value = node.id;
  } else if (isViewable(node)) {
    const idx = viewableNodes.value.findIndex((n) => n.id === node.id);
    previewIndex.value = idx < 0 ? 0 : idx;
    previewOpen.value = true;
  } else {
    downloadNode(node);
  }
}

function downloadNode(node) {
  window.open(route("projects.files.download", [props.projectSlug, node.id]), "_blank");
}

function navigateTo(parentId) {
  currentParentId.value = parentId;
}

watch(currentParentId, () => {
  clearSelection();
  searchQuery.value = "";
});

// --- CRUD ---
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

function renameInline({ node, name }) {
  router.put(
    route("projects.files.update", [props.projectSlug, node.id]),
    { name },
    { preserveScroll: true, preserveState: true, only: ["fileNodes"] },
  );
}

function openMove(node) {
  moveNode.value = node;
  moveIds.value = null;
  moveOpen.value = true;
}

function duplicate(node) {
  router.post(
    route("projects.files.duplicate", [props.projectSlug, node.id]),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      only: ["fileNodes", "storageUsed", "storageQuota"],
    },
  );
}

function openShare(node) {
  shareNode.value = node;
  shareOpen.value = true;
}

async function deleteNode(node) {
  const message =
    node.type === "folder"
      ? "Ce dossier et tout son contenu seront déplacés dans la corbeille."
      : "Ce fichier sera déplacé dans la corbeille.";
  if (
    !(await confirmDialog({
      title: node.type === "folder" ? "Supprimer le dossier" : "Supprimer le fichier",
      message,
    }))
  )
    return;
  router.delete(route("projects.files.destroy", [props.projectSlug, node.id]), {
    preserveScroll: true,
    preserveState: true,
    only: ["fileNodes", "trashedFileNodes", "storageUsed", "storageQuota"],
  });
}

// --- Actions groupées ---
function openBulkMove() {
  moveNode.value = null;
  moveIds.value = [...selectedIds.value];
  moveOpen.value = true;
}

async function bulkDelete() {
  if (
    !(await confirmDialog({
      title: "Supprimer la sélection",
      message: `${selectedIds.value.length} élément(s) seront déplacés dans la corbeille.`,
    }))
  )
    return;
  router.delete(route("projects.files.bulk-destroy", props.projectSlug), {
    data: { ids: selectedIds.value },
    preserveScroll: true,
    preserveState: true,
    only: ["fileNodes", "trashedFileNodes", "storageUsed", "storageQuota"],
    onSuccess: clearSelection,
  });
}

function bulkDownload() {
  if (selectedIds.value.length === 0) return;
  const params = selectedIds.value.map((id) => `ids[]=${id}`).join("&");
  window.open(
    `${route("projects.files.download-zip", props.projectSlug)}?${params}`,
    "_blank",
  );
}

function moveNodeTo(nodeId, parentId) {
  router.post(
    route("projects.files.move", [props.projectSlug, nodeId]),
    { parent_id: parentId },
    { preserveScroll: true, preserveState: true, only: ["fileNodes"] },
  );
}

// --- Menu contextuel ---
const contextMenu = ref({ open: false, x: 0, y: 0, node: null });

function onCardContextMenu({ node, event }) {
  if (!isSelected(node.id)) {
    selectedIds.value = [node.id];
    lastSelectedId.value = node.id;
  }
  activeNodeId.value = node.id;
  contextMenu.value = { open: true, x: event.clientX, y: event.clientY, node };
}

const contextSelectionCount = computed(() => {
  const node = contextMenu.value.node;
  if (!node) return 0;
  return isSelected(node.id) ? selectedIds.value.length : 1;
});

function onContextAction(key) {
  const node = contextMenu.value.node;
  if (!node) return;
  const many = selectedIds.value.length > 1 && isSelected(node.id);
  switch (key) {
    case "open":
      openNode(node);
      break;
    case "rename":
      openRename(node);
      break;
    case "move":
      many ? openBulkMove() : openMove(node);
      break;
    case "duplicate":
      duplicate(node);
      break;
    case "download":
      downloadNode(node);
      break;
    case "download-zip":
      bulkDownload();
      break;
    case "share":
      openShare(node);
      break;
    case "details":
      activeNodeId.value = node.id;
      detailsOpen.value = true;
      break;
    case "delete":
      many ? bulkDelete() : deleteNode(node);
      break;
  }
}

// --- Raccourcis clavier ---
function isTyping() {
  const el = document.activeElement;
  if (!el) return false;
  const tag = el.tagName;
  return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable;
}

function currentColumns() {
  const el = zoneRef.value;
  if (!el) return 4;
  return Math.max(1, Math.floor(el.clientWidth / 142));
}

function moveActive(delta) {
  const list = currentNodes.value;
  if (list.length === 0) return;
  let idx = list.findIndex((n) => n.id === activeNodeId.value);
  if (idx < 0) idx = 0;
  else idx = Math.min(list.length - 1, Math.max(0, idx + delta));
  const target = list[idx];
  activeNodeId.value = target.id;
  selectedIds.value = [target.id];
  lastSelectedId.value = target.id;
}

function onKeydown(e) {
  if (previewOpen.value || dialogOpen.value || moveOpen.value || trashOpen.value || shareOpen.value)
    return;
  if (isTyping()) return;

  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "a") {
    e.preventDefault();
    selectAll();
    return;
  }
  if (e.key === "Escape") {
    if (selectionActive.value) {
      clearSelection();
      e.preventDefault();
    }
    return;
  }
  if (e.key === "Delete" && selectionActive.value) {
    e.preventDefault();
    bulkDelete();
    return;
  }
  if (e.key === "F2") {
    const node = detailsNode.value;
    if (node) {
      e.preventDefault();
      openRename(node);
    }
    return;
  }
  if (e.key === "Enter" && detailsNode.value) {
    e.preventDefault();
    openNode(detailsNode.value);
    return;
  }
  const cols = viewMode.value === "grid" ? currentColumns() : 1;
  if (e.key === "ArrowRight") {
    e.preventDefault();
    moveActive(1);
  } else if (e.key === "ArrowLeft") {
    e.preventDefault();
    moveActive(-1);
  } else if (e.key === "ArrowDown") {
    e.preventDefault();
    moveActive(cols);
  } else if (e.key === "ArrowUp") {
    e.preventDefault();
    moveActive(-cols);
  }
}

// --- Sélection rectangle (vue grille) ---
const rubber = ref({ active: false, x1: 0, y1: 0, x2: 0, y2: 0 });
const rubberStyle = computed(() => {
  const r = rubber.value;
  const left = Math.min(r.x1, r.x2);
  const top = Math.min(r.y1, r.y2);
  return {
    left: left + "px",
    top: top + "px",
    width: Math.abs(r.x2 - r.x1) + "px",
    height: Math.abs(r.y2 - r.y1) + "px",
  };
});

function onZoneMouseDown(e) {
  if (viewMode.value !== "grid") return;
  if (e.button !== 0) return;
  // Seulement sur le fond (pas sur une carte).
  if (e.target.closest("[data-node-id]")) return;
  const rect = zoneRef.value.getBoundingClientRect();
  rubber.value = {
    active: true,
    x1: e.clientX - rect.left,
    y1: e.clientY - rect.top,
    x2: e.clientX - rect.left,
    y2: e.clientY - rect.top,
  };
  if (!e.shiftKey && !e.ctrlKey && !e.metaKey) clearSelection();
  window.addEventListener("mousemove", onRubberMove);
  window.addEventListener("mouseup", onRubberUp);
}

function onRubberMove(e) {
  if (!rubber.value.active) return;
  const rect = zoneRef.value.getBoundingClientRect();
  rubber.value.x2 = e.clientX - rect.left;
  rubber.value.y2 = e.clientY - rect.top;

  const selLeft = Math.min(rubber.value.x1, rubber.value.x2) + rect.left;
  const selTop = Math.min(rubber.value.y1, rubber.value.y2) + rect.top;
  const selRight = Math.max(rubber.value.x1, rubber.value.x2) + rect.left;
  const selBottom = Math.max(rubber.value.y1, rubber.value.y2) + rect.top;

  const hits = [];
  zoneRef.value.querySelectorAll("[data-node-id]").forEach((el) => {
    const b = el.getBoundingClientRect();
    const overlap =
      b.left < selRight && b.right > selLeft && b.top < selBottom && b.bottom > selTop;
    if (overlap) hits.push(parseInt(el.getAttribute("data-node-id"), 10));
  });
  selectedIds.value = hits;
}

function onRubberUp() {
  rubber.value.active = false;
  window.removeEventListener("mousemove", onRubberMove);
  window.removeEventListener("mouseup", onRubberUp);
}

// --- Upload ---
function onImportClick() {
  fileInputRef.value?.click();
}
function onImportFolderClick() {
  folderInputRef.value?.click();
}
function onFileInputChange(e) {
  uploadFileList(e.target.files, currentParentId.value);
  e.target.value = "";
}
function onFolderInputChange(e) {
  uploadFileList(e.target.files, currentParentId.value);
  e.target.value = "";
}

async function uploadFromDataTransfer(dt, parentId) {
  const entries = await entriesFromDataTransfer(dt);
  uploadEntries(entries, parentId);
}

function onPaste(e) {
  if (isTyping()) return;
  const items = e.clipboardData?.items;
  if (!items) return;
  const files = [];
  for (const item of items) {
    if (item.kind === "file") {
      const f = item.getAsFile();
      if (f) files.push(f);
    }
  }
  if (files.length === 0) return;
  e.preventDefault();
  const named = files.map((f, i) => {
    if (f.name && f.name !== "image.png") return { file: f, relativePath: null };
    const ext = (f.type.split("/")[1] || "png").replace("jpeg", "jpg");
    const renamed = new File([f], `capture-${Date.now()}-${i}.${ext}`, { type: f.type });
    return { file: renamed, relativePath: null };
  });
  uploadEntries(named, currentParentId.value);
}

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
  window.addEventListener("paste", onPaste);
});
onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown);
  window.removeEventListener("paste", onPaste);
  window.removeEventListener("mousemove", onRubberMove);
  window.removeEventListener("mouseup", onRubberUp);
});

// --- Drag & drop bureau ---
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
  if (e.currentTarget === e.target) isDraggingDesktop.value = false;
}

function onZoneDrop(e) {
  e.preventDefault();
  isDraggingDesktop.value = false;
  dragTargetId.value = null;
  if (e.dataTransfer.items && e.dataTransfer.items.length > 0 && e.dataTransfer.types.includes("Files")) {
    uploadFromDataTransfer(e.dataTransfer, currentParentId.value);
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
  if (event.dataTransfer.items && event.dataTransfer.items.length > 0 && event.dataTransfer.types.includes("Files")) {
    uploadFromDataTransfer(event.dataTransfer, target.id);
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
  e.dataTransfer.dropEffect = e.dataTransfer.types.includes("Files") ? "copy" : "move";
}
function onBreadcrumbDrop(e, parentId) {
  e.preventDefault();
  if (e.dataTransfer.items && e.dataTransfer.items.length > 0 && e.dataTransfer.types.includes("Files")) {
    uploadFromDataTransfer(e.dataTransfer, parentId);
    return;
  }
  const id = e.dataTransfer.getData("application/x-file-node-id");
  if (id) {
    const nodeId = parseInt(id, 10);
    const node = props.nodes.find((n) => n.id === nodeId);
    if (!node) return;
    if ((node.parent_id ?? null) !== parentId) moveNodeTo(nodeId, parentId);
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
          Glissez des fichiers/dossiers · clic droit pour les actions · Ctrl+V pour coller
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" class="gap-1.5" @click="trashOpen = true">
          <Trash class="h-3.5 w-3.5" />
          Corbeille
          <span
            v-if="trashedNodes.length"
            class="ml-0.5 rounded bg-muted px-1 text-[10px] font-semibold"
            >{{ trashedNodes.length }}</span
          >
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="openCreateFolder">
          <FolderPlus class="h-3.5 w-3.5" />
          Nouveau dossier
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="onImportFolderClick">
          <FolderUp class="h-3.5 w-3.5" />
          Dossier
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="onImportClick">
          <Upload class="h-3.5 w-3.5" />
          Importer
        </Button>
        <input ref="fileInputRef" type="file" multiple class="hidden" @change="onFileInputChange" />
        <input
          ref="folderInputRef"
          type="file"
          webkitdirectory
          directory
          multiple
          class="hidden"
          @change="onFolderInputChange"
        />
      </div>
    </header>

    <!-- Jauge de quota -->
    <div class="flex items-center gap-3 text-xs text-muted-foreground">
      <div class="h-1.5 w-40 overflow-hidden rounded-full bg-muted">
        <div
          class="h-full rounded-full transition-all"
          :class="quotaPercent >= 90 ? 'bg-rose-500' : 'bg-primary'"
          :style="{ width: quotaPercent + '%' }"
        />
      </div>
      <span>{{ formatBytes(storageUsed) }} / {{ formatBytes(storageQuota) }}</span>
    </div>

    <!-- Barre d'outils -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative min-w-[180px] flex-1">
        <Search
          class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher dans ce dossier…"
          class="h-9 w-full rounded-md border border-border bg-background pl-8 pr-8 text-sm text-foreground outline-none transition-colors focus:border-primary"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
          title="Effacer"
          @click="searchQuery = ''"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </div>

      <div class="flex items-center rounded-md border border-border bg-background p-0.5">
        <button
          v-for="opt in sortOptions"
          :key="opt.field"
          type="button"
          class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium transition-colors"
          :class="
            sortField === opt.field
              ? 'bg-muted text-foreground'
              : 'text-muted-foreground hover:text-foreground'
          "
          @click="setSort(opt.field)"
        >
          {{ opt.label }}
          <ArrowDownUp
            v-if="sortField === opt.field"
            class="h-3 w-3"
            :class="sortDir === 'desc' ? 'rotate-180' : ''"
          />
        </button>
      </div>

      <div class="flex items-center rounded-md border border-border bg-background p-0.5">
        <button
          type="button"
          class="inline-flex h-7 w-7 items-center justify-center rounded transition-colors"
          :class="viewMode === 'grid' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
          title="Vue grille"
          @click="viewMode = 'grid'"
        >
          <LayoutGrid class="h-4 w-4" />
        </button>
        <button
          type="button"
          class="inline-flex h-7 w-7 items-center justify-center rounded transition-colors"
          :class="viewMode === 'list' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
          title="Vue liste"
          @click="viewMode = 'list'"
        >
          <List class="h-4 w-4" />
        </button>
      </div>
    </div>

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

    <!-- Barre de sélection -->
    <div
      v-if="selectionActive"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2"
    >
      <span class="text-sm font-medium text-foreground">{{ selectedIds.length }} sélectionné(s)</span>
      <div class="ml-auto flex items-center gap-1.5">
        <Button variant="outline" size="sm" class="gap-1.5" @click="bulkDownload">
          <Download class="h-3.5 w-3.5" />
          Télécharger (zip)
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="openBulkMove">
          <FolderInput class="h-3.5 w-3.5" />
          Déplacer
        </Button>
        <Button
          variant="outline"
          size="sm"
          class="gap-1.5 text-rose-400 hover:text-rose-300"
          @click="bulkDelete"
        >
          <Trash2 class="h-3.5 w-3.5" />
          Supprimer
        </Button>
        <Button variant="ghost" size="sm" @click="clearSelection">Annuler</Button>
      </div>
    </div>

    <div class="flex gap-3">
      <!-- Zone principale -->
      <div
        ref="zoneRef"
        class="relative min-h-[420px] flex-1 rounded-xl border border-border bg-card/30 p-4 transition-colors"
        :class="[
          isDraggingDesktop ? 'border-primary bg-primary/5' : '',
          viewMode === 'grid' && !virtualActive ? 'flex flex-wrap gap-3 content-start' : '',
          viewMode === 'list' && !virtualActive ? 'flex flex-col gap-1' : '',
        ]"
        @dragover="onZoneDragOver"
        @dragleave="onZoneDragLeave"
        @drop="onZoneDrop"
        @mousedown="onZoneMouseDown"
      >
        <!-- Rendu standard (grille + liste courte) -->
        <template v-if="!virtualActive">
          <FileNodeCard
            v-for="node in currentNodes"
            :key="node.id"
            :node="node"
            :view="viewMode"
            :selected="isSelected(node.id)"
            :selection-active="selectionActive"
            :is-drag-target="dragTargetId === node.id"
            @open="openNode"
            @rename="openRename"
            @rename-inline="renameInline"
            @move="openMove"
            @delete="deleteNode"
            @toggle-select="toggleSelect"
            @range-select="rangeSelect"
            @contextmenu="onCardContextMenu"
            @drag-start="onCardDragStart"
            @drag-end="onCardDragEnd"
            @dragover-folder="onCardDragOverFolder"
            @drop-on-folder="onCardDropOnFolder"
          />
        </template>

        <!-- Rendu virtualisé (liste longue) -->
        <div
          v-else
          class="max-h-[60vh] overflow-y-auto"
          @scroll="onListScroll"
        >
          <div :style="{ height: visibleRange.padTop + 'px' }" />
          <FileNodeCard
            v-for="node in visibleNodes"
            :key="node.id"
            :node="node"
            view="list"
            :selected="isSelected(node.id)"
            :selection-active="selectionActive"
            :is-drag-target="dragTargetId === node.id"
            @open="openNode"
            @rename="openRename"
            @rename-inline="renameInline"
            @move="openMove"
            @delete="deleteNode"
            @toggle-select="toggleSelect"
            @range-select="rangeSelect"
            @contextmenu="onCardContextMenu"
            @drag-start="onCardDragStart"
            @drag-end="onCardDragEnd"
            @dragover-folder="onCardDragOverFolder"
            @drop-on-folder="onCardDropOnFolder"
          />
          <div :style="{ height: visibleRange.padBottom + 'px' }" />
        </div>

        <p
          v-if="currentNodes.length === 0"
          class="pointer-events-none absolute inset-0 flex items-center justify-center text-center text-sm text-muted-foreground"
        >
          {{
            searchQuery
              ? "Aucun fichier ne correspond à la recherche"
              : "Glissez-déposez des fichiers ou créez un dossier"
          }}
        </p>

        <!-- Rectangle de sélection -->
        <div
          v-if="rubber.active"
          class="pointer-events-none absolute z-10 rounded border border-primary bg-primary/10"
          :style="rubberStyle"
        />
      </div>

      <!-- Panneau détails -->
      <FileDetailsPanel
        v-if="detailsOpen && detailsNode"
        :node="detailsNode"
        :project-slug="projectSlug"
        @close="detailsOpen = false"
        @preview="openNode"
        @share="openShare"
      />
    </div>

    <!-- Progression d'upload -->
    <Teleport to="body">
      <div
        v-if="uploadState.active"
        class="fixed bottom-4 right-4 z-[110] w-80 overflow-hidden rounded-xl border border-border bg-popover shadow-2xl"
      >
        <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
          <span class="text-sm font-medium text-foreground">
            Envoi · {{ uploadState.overall }}%
          </span>
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground"
            title="Fermer"
            @click="dismiss"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="h-1 w-full bg-muted">
          <div class="h-full bg-primary transition-all" :style="{ width: uploadState.overall + '%' }" />
        </div>
        <div class="max-h-48 overflow-y-auto p-2">
          <div
            v-for="(item, i) in uploadState.items"
            :key="i"
            class="flex items-center gap-2 px-1 py-1 text-xs"
          >
            <span class="min-w-0 flex-1 truncate text-foreground" :title="item.name">{{ item.name }}</span>
            <span
              class="shrink-0"
              :class="item.status === 'error' ? 'text-rose-400' : 'text-muted-foreground'"
            >
              {{ item.status === "error" ? "échec" : item.status === "done" ? "✓" : item.progress + "%" }}
            </span>
          </div>
        </div>
        <p v-if="uploadState.error" class="px-3 pb-2 text-xs text-rose-400">
          {{ uploadState.error }}
        </p>
      </div>
    </Teleport>

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
      :node-ids="moveIds"
    />

    <FilePreviewDialog
      v-model:open="previewOpen"
      v-model:index="previewIndex"
      :project-slug="projectSlug"
      :files="viewableNodes"
    />

    <FileContextMenu
      v-model:open="contextMenu.open"
      :x="contextMenu.x"
      :y="contextMenu.y"
      :node="contextMenu.node"
      :selection-count="contextSelectionCount"
      @action="onContextAction"
    />

    <TrashDialog
      v-model:open="trashOpen"
      :project-slug="projectSlug"
      :nodes="trashedNodes"
    />

    <ShareDialog
      v-model:open="shareOpen"
      :project-slug="projectSlug"
      :node="shareNode"
    />
  </div>
</template>
