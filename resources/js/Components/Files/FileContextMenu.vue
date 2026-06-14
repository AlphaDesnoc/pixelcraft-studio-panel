<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from "vue";
import {
  Copy,
  Download,
  FolderInput,
  Info,
  Link2,
  Lock,
  Pencil,
  Trash2,
} from "lucide-vue-next";
import { isViewable } from "./fileKind.js";

const props = defineProps({
  open: { type: Boolean, default: false },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  node: { type: Object, default: null },
  selectionCount: { type: Number, default: 0 },
  canSetAccessLevel: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
});

const emits = defineEmits(["update:open", "action"]);

const menuRef = ref(null);
const pos = ref({ x: 0, y: 0 });

const isFolder = computed(() => props.node?.type === "folder");
const isFile = computed(() => props.node?.type === "file");
const multiple = computed(() => props.selectionCount > 1);

const items = computed(() => {
  if (!props.node) return [];
  const list = [];
  if (!multiple.value) {
    list.push({
      key: "open",
      label: isFolder.value ? "Ouvrir" : isViewable(props.node) ? "Aperçu" : "Télécharger",
      icon: isFolder.value ? FolderInput : Download,
    });
    list.push({ key: "rename", label: "Renommer", icon: Pencil });
  }
  list.push({ key: "move", label: "Déplacer", icon: FolderInput });
  list.push({ key: "duplicate", label: "Dupliquer", icon: Copy });
  if (isFile.value && !multiple.value) {
    list.push({ key: "download", label: "Télécharger", icon: Download });
    list.push({ key: "share", label: "Lien de partage", icon: Link2 });
  }
  if (multiple.value) {
    list.push({ key: "download-zip", label: "Télécharger (zip)", icon: Download });
  }
  if (!multiple.value) {
    list.push({ key: "details", label: "Détails", icon: Info });
  }
  if (props.canSetAccessLevel && !multiple.value) {
    list.push({ key: "access-level", label: "Niveau d'accès", icon: Lock });
  }
  if (props.canDelete) {
    list.push({ key: "sep" });
    list.push({ key: "delete", label: "Supprimer", icon: Trash2, danger: true });
  }
  return list;
});

function pick(key) {
  emits("action", key);
  emits("update:open", false);
}

function close() {
  emits("update:open", false);
}

function onDocClick() {
  if (props.open) close();
}

function onKeydown(e) {
  if (props.open && e.key === "Escape") close();
}

// Repositionne le menu pour qu'il reste dans la fenêtre.
watch(
  () => props.open,
  (open) => {
    if (!open) return;
    pos.value = { x: props.x, y: props.y };
    nextTick(() => {
      const el = menuRef.value;
      if (!el) return;
      const rect = el.getBoundingClientRect();
      let nx = props.x;
      let ny = props.y;
      if (nx + rect.width > window.innerWidth) nx = window.innerWidth - rect.width - 8;
      if (ny + rect.height > window.innerHeight)
        ny = window.innerHeight - rect.height - 8;
      pos.value = { x: Math.max(8, nx), y: Math.max(8, ny) };
    });
  },
);

onMounted(() => {
  document.addEventListener("click", onDocClick);
  document.addEventListener("contextmenu", onDocClick);
  window.addEventListener("keydown", onKeydown);
});
onUnmounted(() => {
  document.removeEventListener("click", onDocClick);
  document.removeEventListener("contextmenu", onDocClick);
  window.removeEventListener("keydown", onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && node"
      ref="menuRef"
      class="fixed z-[120] min-w-[190px] overflow-hidden rounded-lg border border-border bg-popover p-1 shadow-xl"
      :style="{ left: pos.x + 'px', top: pos.y + 'px' }"
      @click.stop
      @contextmenu.prevent.stop
    >
      <template v-for="(item, idx) in items" :key="idx">
        <div v-if="item.key === 'sep'" class="my-1 h-px bg-border" />
        <button
          v-else
          type="button"
          class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left text-sm transition-colors"
          :class="
            item.danger
              ? 'text-rose-400 hover:bg-rose-500/10 hover:text-rose-300'
              : 'text-foreground hover:bg-muted'
          "
          @click="pick(item.key)"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" />
          {{ item.label }}
        </button>
      </template>
    </div>
  </Teleport>
</template>
