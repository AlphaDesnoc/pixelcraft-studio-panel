<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { Activity, Download, Filter } from "lucide-vue-next";
import { Select } from "@/Components/ui/select";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  projectSlug: { type: String, required: true },
  members: { type: Array, default: () => [] },
  initialLogs: { type: Array, default: () => [] },
});

const logs = ref([...props.initialLogs]);
const loading = ref(false);
const actionFilter = ref("");
const userFilter = ref("");
const page = ref(1);
const lastPage = ref(1);

const actionOptions = [
  { value: "", label: "Toutes les actions" },
  { value: "task_moved", label: "Déplacements kanban" },
  { value: "task_updated", label: "Modifications tâche" },
  { value: "task_created", label: "Créations tâche" },
  { value: "bug_status_changed", label: "Changements statut bug" },
  { value: "bug_created", label: "Bugs signalés" },
  { value: "rank_updated", label: "Modifications rank" },
];

async function fetchLogs() {
  loading.value = true;
  try {
    const { data } = await axios.get(
      route("projects.activity-logs.index", props.projectSlug),
      {
        params: {
          action: actionFilter.value || undefined,
          user_id: userFilter.value || undefined,
          page: page.value,
          limit: 50,
        },
      },
    );
    logs.value = data.logs?.data ?? data.logs ?? [];
    lastPage.value = data.meta?.last_page ?? 1;
  } finally {
    loading.value = false;
  }
}

watch([actionFilter, userFilter], () => {
  page.value = 1;
  fetchLogs();
});

function formatRelative(iso) {
  if (!iso) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

const exportUrl = computed(() =>
  route("projects.export.activity", props.projectSlug),
);
</script>

<template>
  <div class="rounded-xl border border-border bg-card">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
      <div class="flex items-center gap-2">
        <Activity class="h-4 w-4 text-primary" />
        <h3 class="text-sm font-semibold text-foreground">Historique du projet</h3>
      </div>
      <a
        :href="exportUrl"
        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
      >
        <Download class="h-3.5 w-3.5" />
        Export CSV
      </a>
    </div>

    <div class="flex flex-wrap items-end gap-2 border-b border-border/60 px-4 py-3">
      <div class="w-[180px]">
        <label class="mb-1 flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
          <Filter class="h-3 w-3" />
          Action
        </label>
        <Select v-model="actionFilter" class="h-8 text-xs">
          <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </Select>
      </div>
      <div class="w-[160px]">
        <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
          Utilisateur
        </label>
        <Select v-model="userFilter" class="h-8 text-xs">
          <option value="">Tous</option>
          <option v-for="member in members" :key="member.id" :value="String(member.id)">
            {{ member.name }}
          </option>
        </Select>
      </div>
      <Button type="button" size="sm" variant="outline" class="h-8" :disabled="loading" @click="fetchLogs">
        Actualiser
      </Button>
    </div>

    <ul v-if="logs.length" class="divide-y divide-border/40">
      <li v-for="log in logs" :key="log.id" class="px-4 py-3">
        <p class="text-sm text-foreground">
          <span class="font-medium">{{ log.user?.name ?? "Système" }}</span>
          <span class="text-muted-foreground"> · </span>
          <span>{{ log.message }}</span>
        </p>
        <p class="mt-0.5 text-[11px] text-muted-foreground">
          {{ formatRelative(log.created_at) }}
          <span v-if="log.action" class="ml-2 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">
            {{ log.action }}
          </span>
        </p>
      </li>
    </ul>
    <p v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
      {{ loading ? "Chargement…" : "Aucune entrée pour ces filtres" }}
    </p>

    <div v-if="lastPage > 1" class="flex justify-center gap-2 border-t border-border px-4 py-3">
      <Button
        type="button"
        size="sm"
        variant="outline"
        :disabled="page <= 1 || loading"
        @click="page -= 1; fetchLogs()"
      >
        Précédent
      </Button>
      <span class="self-center text-xs text-muted-foreground">{{ page }} / {{ lastPage }}</span>
      <Button
        type="button"
        size="sm"
        variant="outline"
        :disabled="page >= lastPage || loading"
        @click="page += 1; fetchLogs()"
      >
        Suivant
      </Button>
    </div>
  </div>
</template>
