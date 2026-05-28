<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Bookmark, Save, Trash2 } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  projectSlug: { type: String, required: true },
  views: { type: Array, default: () => [] },
  currentFilters: { type: Object, required: true },
});

const emit = defineEmits(["apply"]);

const viewName = ref("");
const selectedViewId = ref("");

function saveView() {
  if (!viewName.value.trim()) return;
  router.post(route("projects.kanban.views.store", props.projectSlug), {
    name: viewName.value.trim(),
    filters: props.currentFilters,
    is_shared: true,
  }, { preserveScroll: true, onSuccess: () => { viewName.value = ""; } });
}

function applyView() {
  const view = props.views.find((v) => String(v.id) === selectedViewId.value);
  if (view) emit("apply", view.filters ?? {});
}

function deleteView() {
  if (!selectedViewId.value) return;
  router.delete(route("projects.kanban.views.destroy", [props.projectSlug, selectedViewId.value]), {
    preserveScroll: true,
    onSuccess: () => { selectedViewId.value = ""; },
  });
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-2 rounded-lg border border-border/60 bg-card/40 p-2">
    <Bookmark class="h-4 w-4 text-muted-foreground" />
    <Select v-model="selectedViewId" class="h-8 min-w-[140px] text-xs">
      <option value="">Vue sauvegardée…</option>
      <option v-for="view in views" :key="view.id" :value="String(view.id)">{{ view.name }}</option>
    </Select>
    <Button type="button" size="sm" variant="outline" class="h-8 text-xs" :disabled="!selectedViewId" @click="applyView">Appliquer</Button>
    <Button type="button" size="sm" variant="ghost" class="h-8 px-2" :disabled="!selectedViewId" @click="deleteView">
      <Trash2 class="h-3.5 w-3.5" />
    </Button>
    <Input v-model="viewName" placeholder="Nom de la vue" class="h-8 w-36 text-xs" />
    <Button type="button" size="sm" variant="outline" class="h-8 gap-1 text-xs" @click="saveView">
      <Save class="h-3 w-3" /> Sauver filtres
    </Button>
  </div>
</template>
