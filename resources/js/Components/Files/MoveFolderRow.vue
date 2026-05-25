<script setup>
import { computed } from "vue";
import { ChevronRight, Folder } from "lucide-vue-next";

const props = defineProps({
  folder: { type: Object, required: true },
  folders: { type: Array, required: true },
  expanded: { type: Set, required: true },
  selected: { default: null },
  depth: { type: Number, default: 0 },
  excludeId: { type: Number, default: null },
});

const emits = defineEmits(["toggle", "select"]);

const children = computed(() =>
  props.folders.filter(
    (f) =>
      (f.parent_id ?? null) === props.folder.id &&
      f.id !== props.excludeId,
  ),
);

const isExpanded = computed(() => props.expanded.has(props.folder.id));
const isSelected = computed(() => props.selected === props.folder.id);
</script>

<template>
  <div class="flex flex-col">
    <div
      class="group flex items-center gap-1 rounded-md py-1 text-sm transition-colors hover:bg-muted/60"
      :class="isSelected ? 'bg-primary/15 text-primary' : 'text-foreground'"
      :style="{ paddingLeft: depth * 12 + 4 + 'px' }"
    >
      <button
        type="button"
        class="inline-flex h-5 w-5 items-center justify-center rounded text-muted-foreground transition-transform"
        :class="[
          isExpanded ? 'rotate-90' : '',
          children.length === 0 ? 'opacity-30' : '',
        ]"
        @click.stop="children.length > 0 && emits('toggle', folder.id)"
      >
        <ChevronRight class="h-3 w-3" />
      </button>
      <button
        type="button"
        class="flex flex-1 items-center gap-1.5 text-left"
        @click="emits('select', folder.id)"
      >
        <Folder class="h-3.5 w-3.5" style="color: #f59e0b" />
        <span class="truncate">{{ folder.name }}</span>
      </button>
    </div>
    <ul v-if="isExpanded && children.length > 0" class="flex flex-col gap-0.5">
      <li v-for="child in children" :key="child.id">
        <MoveFolderRow
          :folder="child"
          :folders="folders"
          :expanded="expanded"
          :selected="selected"
          :depth="depth + 1"
          :exclude-id="excludeId"
          @toggle="(id) => emits('toggle', id)"
          @select="(id) => emits('select', id)"
        />
      </li>
    </ul>
  </div>
</template>
