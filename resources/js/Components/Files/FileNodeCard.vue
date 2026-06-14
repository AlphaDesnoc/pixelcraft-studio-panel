<script setup>
import { computed, nextTick, ref, watch } from "vue";
import {
  Check,
  File,
  FileArchive,
  FileAudio,
  FileImage,
  FileText,
  FileVideo,
  Folder,
  Lock,
  Move,
  Pencil,
  Trash2,
} from "lucide-vue-next";

const props = defineProps({
  node: { type: Object, required: true },
  isDragTarget: { type: Boolean, default: false },
  view: { type: String, default: "grid" },
  selected: { type: Boolean, default: false },
  selectionActive: { type: Boolean, default: false },
  // Palier d'accréditation requis ({value,name,color}) si le nœud est verrouillé.
  accessLevelInfo: { type: Object, default: null },
  // Affiche le bouton de suppression (gestionnaires uniquement).
  canDelete: { type: Boolean, default: false },
});

const emits = defineEmits([
  "open",
  "rename",
  "rename-inline",
  "move",
  "delete",
  "toggle-select",
  "range-select",
  "contextmenu",
  "drag-start",
  "drag-end",
  "drop-on-folder",
  "dragover-folder",
]);

const hovered = ref(false);
const thumbFailed = ref(false);
const isFolder = computed(() => props.node.type === "folder");
const isList = computed(() => props.view === "list");

const showThumbnail = computed(
  () =>
    !isFolder.value &&
    (props.node.mime ?? "").startsWith("image/") &&
    props.node.url &&
    !thumbFailed.value,
);

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

const dateLabel = computed(() => {
  if (!props.node.created_at) return "";
  try {
    return new Date(props.node.created_at).toLocaleDateString("fr-FR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "";
  }
});

// --- Renommage inline ---
const editing = ref(false);
const draftName = ref("");
const inputRef = ref(null);

function startEditing() {
  draftName.value = props.node.name;
  editing.value = true;
  nextTick(() => {
    inputRef.value?.focus();
    inputRef.value?.select();
  });
}

function commitEditing() {
  if (!editing.value) return;
  editing.value = false;
  const name = draftName.value.trim();
  if (name && name !== props.node.name) {
    emits("rename-inline", { node: props.node, name });
  }
}

function cancelEditing() {
  editing.value = false;
}

watch(
  () => props.node.id,
  () => {
    editing.value = false;
  },
);

let clickTimer = null;

function onClick(e) {
  if (editing.value) return;

  // Sélection par plage (Shift) ou bascule (Ctrl/Cmd) : pas d'ouverture.
  if (e?.shiftKey) {
    emits("range-select", props.node);
    return;
  }
  if (e?.ctrlKey || e?.metaKey) {
    emits("toggle-select", props.node);
    return;
  }

  // Temporise l'ouverture pour laisser une chance au double-clic (renommage).
  if (clickTimer) return;
  clickTimer = setTimeout(() => {
    clickTimer = null;
    emits("open", props.node);
  }, 220);
}

function onDblClick() {
  if (clickTimer) {
    clearTimeout(clickTimer);
    clickTimer = null;
  }
  startEditing();
}

function onContextMenu(e) {
  emits("contextmenu", { node: props.node, event: e });
}

function onToggleSelect() {
  emits("toggle-select", props.node);
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
  <!-- ===================== Vue grille ===================== -->
  <div
    v-if="!isList"
    :data-node-id="node.id"
    class="group relative flex h-[120px] w-[130px] shrink-0 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-transparent p-2 transition-all hover:border-border hover:bg-card/50"
    :class="[
      isDragTarget ? 'border-primary bg-primary/10' : '',
      selected ? 'border-primary bg-primary/10' : '',
    ]"
    :draggable="!editing"
    @click="onClick"
    @dblclick.stop="onDblClick"
    @contextmenu.prevent.stop="onContextMenu"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover="onDragOver"
    @drop="onDrop"
  >
    <!-- Case de sélection -->
    <button
      type="button"
      class="absolute left-1 top-1 z-10 inline-flex h-5 w-5 items-center justify-center rounded border bg-background/80 backdrop-blur transition-opacity"
      :class="[
        selected
          ? 'border-primary bg-primary text-primary-foreground opacity-100'
          : 'border-border text-transparent opacity-0 group-hover:opacity-100',
        selectionActive ? 'opacity-100' : '',
      ]"
      title="Sélectionner"
      @click.stop="onToggleSelect"
    >
      <Check class="h-3.5 w-3.5" />
    </button>

    <!-- Badge de verrouillage (niveau d'accréditation) -->
    <div
      v-if="accessLevelInfo"
      class="absolute right-1 top-1 inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[9px] font-semibold group-hover:opacity-0"
      :style="{ backgroundColor: accessLevelInfo.color + '22', color: accessLevelInfo.color }"
      :title="`Accès : ${accessLevelInfo.name}`"
    >
      <Lock class="h-2.5 w-2.5" />
    </div>

    <img
      v-if="showThumbnail"
      :src="node.url"
      :alt="node.name"
      class="h-[60px] w-[100px] rounded-md object-cover"
      draggable="false"
      loading="lazy"
      @error="thumbFailed = true"
    />
    <component v-else :is="Icon" :size="60" :stroke-width="1.5" :color="iconColor" />

    <input
      v-if="editing"
      ref="inputRef"
      v-model="draftName"
      class="w-full rounded border border-primary bg-background px-1 py-0.5 text-center text-xs text-foreground outline-none"
      @click.stop
      @keydown.enter.prevent="commitEditing"
      @keydown.esc.prevent="cancelEditing"
      @blur="commitEditing"
    />
    <span
      v-else
      class="line-clamp-2 break-all text-center text-xs font-medium text-foreground"
      :title="node.name"
    >
      {{ node.name }}
    </span>
    <span v-if="!isFolder && sizeLabel && !editing" class="text-[10px] text-muted-foreground">
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
        v-if="canDelete"
        type="button"
        class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-background/80 text-rose-400 backdrop-blur hover:bg-rose-500/15 hover:text-rose-300"
        title="Supprimer"
        @click.stop="emits('delete', node)"
      >
        <Trash2 class="h-3 w-3" />
      </button>
    </div>
  </div>

  <!-- ===================== Vue liste ===================== -->
  <div
    v-else
    :data-node-id="node.id"
    class="group relative flex cursor-pointer items-center gap-3 rounded-lg border border-transparent px-2 py-1.5 transition-colors hover:border-border hover:bg-card/50"
    :class="[
      isDragTarget ? 'border-primary bg-primary/10' : '',
      selected ? 'border-primary bg-primary/10' : '',
    ]"
    :draggable="!editing"
    @click="onClick"
    @dblclick.stop="onDblClick"
    @contextmenu.prevent.stop="onContextMenu"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover="onDragOver"
    @drop="onDrop"
  >
    <button
      type="button"
      class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border bg-background/80 transition-opacity"
      :class="[
        selected
          ? 'border-primary bg-primary text-primary-foreground opacity-100'
          : 'border-border text-transparent opacity-0 group-hover:opacity-100',
        selectionActive ? 'opacity-100' : '',
      ]"
      title="Sélectionner"
      @click.stop="onToggleSelect"
    >
      <Check class="h-3.5 w-3.5" />
    </button>

    <img
      v-if="showThumbnail"
      :src="node.url"
      :alt="node.name"
      class="h-8 w-8 shrink-0 rounded object-cover"
      draggable="false"
      loading="lazy"
      @error="thumbFailed = true"
    />
    <component
      v-else
      :is="Icon"
      :size="28"
      :stroke-width="1.5"
      :color="iconColor"
      class="shrink-0"
    />

    <div class="min-w-0 flex-1">
      <input
        v-if="editing"
        ref="inputRef"
        v-model="draftName"
        class="w-full max-w-xs rounded border border-primary bg-background px-1.5 py-0.5 text-sm text-foreground outline-none"
        @click.stop
        @keydown.enter.prevent="commitEditing"
        @keydown.esc.prevent="cancelEditing"
        @blur="commitEditing"
      />
      <span
        v-else
        class="block truncate text-sm font-medium text-foreground"
        :title="node.name"
      >
        {{ node.name }}
      </span>
    </div>

    <span
      v-if="accessLevelInfo"
      class="hidden shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold sm:inline-flex"
      :style="{ backgroundColor: accessLevelInfo.color + '22', color: accessLevelInfo.color }"
      :title="`Accès : ${accessLevelInfo.name}`"
    >
      <Lock class="h-3 w-3" />
      {{ accessLevelInfo.name }}
    </span>

    <span class="hidden w-20 shrink-0 text-right text-xs text-muted-foreground sm:block">
      {{ isFolder ? "" : sizeLabel }}
    </span>
    <span class="hidden w-28 shrink-0 text-right text-xs text-muted-foreground md:block">
      {{ dateLabel }}
    </span>

    <div
      class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
    >
      <button
        type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/80 hover:text-foreground"
        title="Déplacer"
        @click.stop="emits('move', node)"
      >
        <Move class="h-3.5 w-3.5" />
      </button>
      <button
        type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/80 hover:text-foreground"
        title="Renommer"
        @click.stop="emits('rename', node)"
      >
        <Pencil class="h-3.5 w-3.5" />
      </button>
      <button
        v-if="canDelete"
        type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-rose-400 hover:bg-rose-500/15 hover:text-rose-300"
        title="Supprimer"
        @click.stop="emits('delete', node)"
      >
        <Trash2 class="h-3.5 w-3.5" />
      </button>
    </div>
  </div>
</template>
