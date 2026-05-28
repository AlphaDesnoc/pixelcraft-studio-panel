<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Archive, Tag, UserRound } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  projectSlug: { type: String, required: true },
  selectedIds: { type: Array, required: true },
  members: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
});

const emit = defineEmits(["clear"]);

const assigneeId = ref("");
const tagId = ref("");

function bulk(action) {
  router.post(route("projects.tasks.bulk", props.projectSlug), {
    action,
    task_ids: props.selectedIds,
    assignee_id: assigneeId.value ? Number(assigneeId.value) : null,
    tag_id: tagId.value ? Number(tagId.value) : null,
  }, {
    preserveScroll: true,
    only: ["lists"],
    onSuccess: () => emit("clear"),
  });
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/10 px-3 py-2 text-sm">
    <span class="font-medium">{{ selectedIds.length }} sélectionnée(s)</span>
    <Button type="button" size="sm" variant="outline" class="h-7 gap-1 text-xs" @click="bulk('archive')">
      <Archive class="h-3 w-3" /> Archiver
    </Button>
    <Select v-model="assigneeId" class="h-7 w-32 text-xs">
      <option value="">Assigner…</option>
      <option v-for="m in members" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
    </Select>
    <Button type="button" size="sm" variant="outline" class="h-7 text-xs" :disabled="!assigneeId" @click="bulk('assign')">
      <UserRound class="h-3 w-3" />
    </Button>
    <Select v-model="tagId" class="h-7 w-28 text-xs">
      <option value="">Tag…</option>
      <option v-for="t in tags" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
    </Select>
    <Button type="button" size="sm" variant="outline" class="h-7 text-xs" :disabled="!tagId" @click="bulk('tag')">
      <Tag class="h-3 w-3" />
    </Button>
    <Button type="button" size="sm" variant="ghost" class="h-7 text-xs" @click="emit('clear')">Annuler</Button>
  </div>
</template>
