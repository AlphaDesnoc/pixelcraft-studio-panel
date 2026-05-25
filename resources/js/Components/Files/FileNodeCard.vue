<script setup>
import { computed, ref } from "vue";
import {
  File,
  FileArchive,
  FileAudio,
  FileImage,
  FileText,
  FileVideo,
  Folder,
  Move,
  Pencil,
  Trash2,
} from "lucide-vue-next";

const props = defineProps({
  node: { type: Object, required: true },
  isDragTarget: { type: Boolean, default: false },
});

const emits = defineEmits([
  "open",
  "rename",
  "move",
  "delete",
  "drag-start",
  "drag-end",
  "drop-on-folder",
  "dragover-folder",
]);

const hovered = ref(false);
const isFolder = computed(() => props.node.type === "folder");

const Icon = computed(() => {
  if (isFolder.value) return Folder;
  const mime = props.node.mime ?? "";
  if (mime.startsWith("image/")) return FileImage;
  if (mime.startsWith("video/")) return FileVideo;
  if (mime.startsWith("audio/")) return FileAudio;
  if (
    mime.startsWith("application/zip") ||
    mime.includes("rar") ||
    mime.includes("7z") ||
    mime.includes("tar") ||
    mime.includes("gzip")
  )
    return FileArchive;
  if (mime.startsWith("text/") || mime.includes("pdf") || mime.includes("document"))
    return FileText;
  return File;
});

const iconColor = computed(() => {
  if (isFolder.value) return "#f59e0b";
  const mime = props.node.mime ?? "";
  if (mime.startsWith("image/")) return "#34d399";
  if (mime.startsWith("video/")) return "#f472b6";
  if (mime.startsWith("audio/")) return "#a78bfa";
  if (mime.includes("zip") || mime.includes("rar") || mime.includes("7z"))
    return "#fbbf24";
  if (mime.includes("pdf")) return "#f87171";
  return "#94a3b8";
});

const sizeLabel = computed(() => {
  if (!props.node.size) return "";
  const bytes = props.node.size;
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
});

function onClick() {
  emits("open", props.node);
}

function onDragStart(e) {
  e.dataTransfer.effectAllowed = "move";
  e.dataTransfer.setData("application/x-file-node-id", String(props.node.id));
  emits("drag-start", props.node);
}

function onDragEnd() {
  emits("drag-end", props.node);
}

function onDragOver(e) {
  if (!isFolder.value) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = e.dataTransfer.types.includes("Files")
    ? "copy"
    : "move";
  emits("dragover-folder", props.node);
}

function onDrop(e) {
  if (!isFolder.value) return;
  e.preventDefault();
  e.stopPropagation();
  emits("drop-on-folder", { target: props.node, event: e });
}
</script>

<template>
  <div
    class="group relative flex h-[120px] w-[130px] shrink-0 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-transparent p-2 transition-all hover:border-border hover:bg-card/50"
    :class="isDragTarget ? 'border-primary bg-primary/10' : ''"
    :draggable="true"
    @click="onClick"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover="onDragOver"
    @drop="onDrop"
  >
    <component :is="Icon" :size="60" :stroke-width="1.5" :color="iconColor" />
    <span
      class="line-clamp-2 break-all text-center text-xs font-medium text-foreground"
      :title="node.name"
    >
      {{ node.name }}
    </span>
    <span v-if="!isFolder && sizeLabel" class="text-[10px] text-muted-foreground">
      {{ sizeLabel }}
    </span>

    <div
      class="pointer-events-none absolute right-1 top-1 flex items-center gap-0.5 opacity-0 transition-opacity group-hover:pointer-events-auto group-hover:opacity-100"
    >
      <button
        type="button"
        class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-background/80 text-muted-foreground backdrop-blur hover:bg-muted/80 hover:text-foreground"
        title="Déplacer"
        @click.stop="emits('move', node)"
      >
        <Move class="h-3 w-3" />
      </button>
      <button
        type="button"
        class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-background/80 text-muted-foreground backdrop-blur hover:bg-muted/80 hover:text-foreground"
        title="Renommer"
        @click.stop="emits('rename', node)"
      >
        <Pencil class="h-3 w-3" />
      </button>
      <button
        type="button"
        class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-background/80 text-rose-400 backdrop-blur hover:bg-rose-500/15 hover:text-rose-300"
        title="Supprimer"
        @click.stop="emits('delete', node)"
      >
        <Trash2 class="h-3 w-3" />
      </button>
    </div>
  </div>
</template>
