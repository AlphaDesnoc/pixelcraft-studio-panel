<script setup>
import { reactive, ref, watch } from "vue";
import { Search, UserRound } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";
import { Switch } from "@/Components/ui/switch";

const props = defineProps({
  members: { type: Array, default: () => [] },
  priorities: { type: Object, default: () => ({}) },
  tags: { type: Array, default: () => [] },
  showArchived: { type: Boolean, default: false },
  swimlaneByAssignee: { type: Boolean, default: false },
  currentUserId: { type: Number, default: null },
});

const emit = defineEmits(["update:filters", "update:showArchived", "update:swimlaneByAssignee"]);

const filters = reactive({
  assigneeId: "",
  priority: "",
  due: "all",
  search: "",
  tagIds: [],
  showArchived: props.showArchived,
  swimlaneByAssignee: props.swimlaneByAssignee,
});

function toggleTag(id) {
  const n = Number(id);
  const i = filters.tagIds.indexOf(n);
  if (i >= 0) {
    filters.tagIds.splice(i, 1);
  } else {
    filters.tagIds.push(n);
  }
}

function tagActive(id) {
  return filters.tagIds.includes(Number(id));
}

watch(
  () => props.showArchived,
  (value) => {
    filters.showArchived = value;
  },
);

watch(
  () => props.swimlaneByAssignee,
  (value) => {
    filters.swimlaneByAssignee = value;
  },
);

watch(
  filters,
  (value) => {
    emit("update:filters", { ...value });
    emit("update:showArchived", value.showArchived);
    emit("update:swimlaneByAssignee", value.swimlaneByAssignee);
  },
  { deep: true, immediate: true },
);

const searchInputRef = ref(null);

function focusSearch() {
  searchInputRef.value?.focus?.();
}

function applyMyTasksOnly() {
  if (!props.currentUserId) return;
  filters.assigneeId = String(props.currentUserId);
}

defineExpose({ focusSearch, applyMyTasksOnly });
</script>

<template>
  <div class="flex flex-wrap items-end gap-2 rounded-lg border border-border/60 bg-card/40 p-3">
    <Button
      v-if="currentUserId"
      type="button"
      size="sm"
      variant="outline"
      class="h-8 gap-1 px-2 text-xs"
      @click="applyMyTasksOnly"
    >
      <UserRound class="h-3.5 w-3.5" />
      Mes tâches
    </Button>

    <div class="min-w-[160px] flex-1">
      <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
        Recherche
      </label>
      <div class="relative">
        <Search
          class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          ref="searchInputRef"
          v-model="filters.search"
          type="search"
          placeholder="Titre ou description…"
          class="h-8 pl-8 text-xs"
        />
      </div>
    </div>

    <div class="w-[150px]">
      <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
        Assigné
      </label>
      <Select v-model="filters.assigneeId" class="h-8 text-xs">
        <option value="">Tous</option>
        <option value="none">Non assigné</option>
        <option v-for="member in members" :key="member.id" :value="String(member.id)">
          {{ member.name }}
        </option>
      </Select>
    </div>

    <div class="w-[140px]">
      <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
        Priorité
      </label>
      <Select v-model="filters.priority" class="h-8 text-xs">
        <option value="">Toutes</option>
        <option v-for="(label, key) in priorities" :key="key" :value="key">
          {{ label }}
        </option>
      </Select>
    </div>

    <div class="w-[140px]">
      <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
        Échéance
      </label>
      <Select v-model="filters.due" class="h-8 text-xs">
        <option value="all">Toutes</option>
        <option value="overdue">En retard</option>
        <option value="none">Sans échéance</option>
      </Select>
    </div>

    <div class="flex flex-col gap-1">
      <label class="text-[11px] font-medium text-muted-foreground">
        Archivées
      </label>
      <Switch v-model="filters.showArchived" />
    </div>

    <div class="flex flex-col gap-1">
      <label class="text-[11px] font-medium text-muted-foreground">
        Swimlanes
      </label>
      <Switch v-model="filters.swimlaneByAssignee" />
    </div>

    <div v-if="tags.length" class="w-full min-w-[200px] md:flex-1">
      <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
        Étiquettes
      </label>
      <div class="flex flex-wrap gap-1">
        <button
          v-for="tag in tags"
          :key="tag.id"
          type="button"
          class="rounded-full border px-2 py-0.5 text-[11px] font-medium transition-colors"
          :class="
            tagActive(tag.id)
              ? 'border-primary bg-primary/15 text-foreground'
              : 'border-border/60 bg-background/40 text-muted-foreground hover:bg-muted/50'
          "
          :style="
            tag.color && tagActive(tag.id)
              ? { borderColor: tag.color, color: tag.color }
              : undefined
          "
          @click="toggleTag(tag.id)"
        >
          {{ tag.name }}
        </button>
      </div>
    </div>
  </div>
</template>
