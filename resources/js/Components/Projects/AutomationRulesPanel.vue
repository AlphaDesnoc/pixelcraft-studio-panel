<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Plus, Trash2, Zap } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  projectSlug: { type: String, required: true },
  rules: { type: Array, default: () => [] },
  ranks: { type: Array, default: () => [] },
  canWrite: { type: Boolean, default: false },
});

const name = ref("");
const trigger = ref("task_done");
const action = ref("log_activity");
const rankId = ref("");

function createRule() {
  if (!name.value.trim()) return;
  router.post(route("projects.automations.store", props.projectSlug), {
    name: name.value.trim(),
    trigger: trigger.value,
    action: action.value,
    action_config: action.value === "assign_rank" ? { rank_id: Number(rankId.value) } : {},
    is_active: true,
  }, { preserveScroll: true, onSuccess: () => { name.value = ""; } });
}

function removeRule(id) {
  router.delete(route("projects.automations.destroy", [props.projectSlug, id]), { preserveScroll: true });
}
</script>

<template>
  <div class="rounded-xl border border-border bg-card p-4">
    <div class="mb-3 flex items-center gap-2">
      <Zap class="h-4 w-4 text-amber-400" />
      <h3 class="text-sm font-semibold">Automatisations</h3>
    </div>
    <div v-if="canWrite" class="mb-3 grid gap-2 sm:grid-cols-2">
      <Input v-model="name" placeholder="Nom de la règle" class="h-8 text-sm sm:col-span-2" />
      <Select v-model="trigger" class="h-8 text-xs">
        <option value="task_done">Tâche terminée</option>
        <option value="bug_critical">Bug urgent</option>
        <option value="bug_sla_breach">SLA dépassé</option>
      </Select>
      <Select v-model="action" class="h-8 text-xs">
        <option value="log_activity">Journaliser</option>
        <option value="notify_manager">Notifier manager</option>
        <option value="assign_rank">Assigner rank</option>
      </Select>
      <Select v-if="action === 'assign_rank'" v-model="rankId" class="h-8 text-xs sm:col-span-2">
        <option value="">Rank cible…</option>
        <option v-for="r in ranks" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
      </Select>
      <Button type="button" size="sm" class="h-8 gap-1 sm:col-span-2" @click="createRule"><Plus class="h-3 w-3" /> Ajouter</Button>
    </div>
    <ul class="space-y-2 text-sm">
      <li v-for="rule in rules" :key="rule.id" class="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2">
        <span>{{ rule.name }} <span class="text-xs text-muted-foreground">({{ rule.trigger }} → {{ rule.action }})</span></span>
        <Button v-if="canWrite" type="button" size="sm" variant="ghost" class="h-7 w-7 p-0" @click="removeRule(rule.id)">
          <Trash2 class="h-3.5 w-3.5" />
        </Button>
      </li>
    </ul>
  </div>
</template>
