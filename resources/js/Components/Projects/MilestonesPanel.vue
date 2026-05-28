<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Flag, Plus, Trash2 } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";

const props = defineProps({
  projectSlug: { type: String, required: true },
  milestones: { type: Array, default: () => [] },
  canWrite: { type: Boolean, default: false },
});

const name = ref("");

function createMilestone() {
  if (!name.value.trim()) return;
  router.post(route("projects.milestones.store", props.projectSlug), { name: name.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => { name.value = ""; },
  });
}

function removeMilestone(id) {
  router.delete(route("projects.milestones.destroy", [props.projectSlug, id]), { preserveScroll: true });
}
</script>

<template>
  <div class="rounded-xl border border-border bg-card p-4">
    <div class="mb-3 flex items-center gap-2">
      <Flag class="h-4 w-4 text-primary" />
      <h3 class="text-sm font-semibold">Jalons / sprints</h3>
    </div>
    <div v-if="canWrite" class="mb-3 flex gap-2">
      <Input v-model="name" placeholder="Nouveau jalon" class="h-8 text-sm" />
      <Button type="button" size="sm" class="h-8 gap-1" @click="createMilestone"><Plus class="h-3 w-3" /> Ajouter</Button>
    </div>
    <ul class="space-y-2">
      <li v-for="m in milestones" :key="m.id" class="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2 text-sm">
        <div>
          <p class="font-medium">{{ m.name }}</p>
          <p class="text-xs text-muted-foreground">
            {{ m.burndown?.done ?? 0 }}/{{ m.burndown?.total ?? 0 }} tâches · {{ m.burndown?.open ?? 0 }} ouvertes
          </p>
        </div>
        <Button v-if="canWrite" type="button" size="sm" variant="ghost" class="h-7 w-7 p-0" @click="removeMilestone(m.id)">
          <Trash2 class="h-3.5 w-3.5" />
        </Button>
      </li>
    </ul>
    <p v-if="!milestones.length" class="text-sm text-muted-foreground">Aucun jalon</p>
  </div>
</template>
