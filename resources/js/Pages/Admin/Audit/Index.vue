<script setup>
import { reactive, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { Download, Filter } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AdminTabs from "@/Components/AdminTabs.vue";
import { Button } from "@/Components/ui/button";
import { Card } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  logs: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({
      action: "",
      user: "",
      date_from: "",
      date_to: "",
    }),
  },
  actionOptions: { type: Array, default: () => [] },
});

const localFilters = reactive({
  action: props.filters.action ?? "",
  user: props.filters.user ?? "",
  date_from: props.filters.date_from ?? "",
  date_to: props.filters.date_to ?? "",
});

watch(
  () => props.filters,
  (incoming) => {
    localFilters.action = incoming.action ?? "";
    localFilters.user = incoming.user ?? "";
    localFilters.date_from = incoming.date_from ?? "";
    localFilters.date_to = incoming.date_to ?? "";
  },
  { deep: true },
);

function formatDate(iso) {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

function applyFilters() {
  router.get(
    route("admin.audit.index"),
    {
      action: localFilters.action || undefined,
      user: localFilters.user || undefined,
      date_from: localFilters.date_from || undefined,
      date_to: localFilters.date_to || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    },
  );
}

function resetFilters() {
  localFilters.action = "";
  localFilters.user = "";
  localFilters.date_from = "";
  localFilters.date_to = "";
  applyFilters();
}

function exportUrl() {
  const params = new URLSearchParams();
  if (localFilters.action) params.set("action", localFilters.action);
  if (localFilters.user) params.set("user", localFilters.user);
  if (localFilters.date_from) params.set("date_from", localFilters.date_from);
  if (localFilters.date_to) params.set("date_to", localFilters.date_to);
  const query = params.toString();
  return query ? `${route("export.audit")}?${query}` : route("export.audit");
}
</script>

<template>
  <Head title="Journal d'audit" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold tracking-tight">Journal d'audit</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Historique des actions administratives et sensibles
          </p>
        </div>
        <a
          :href="exportUrl()"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm hover:bg-muted/60"
        >
          <Download class="h-3.5 w-3.5" />
          CSV
        </a>
      </div>
    </template>

    <AdminTabs class="mb-4" />

    <Card class="mb-4 p-4">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
        <div class="min-w-[160px] flex-1">
          <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
            Action
          </label>
          <Select v-model="localFilters.action" class="h-9 text-sm">
            <option value="">Toutes</option>
            <option v-for="action in actionOptions" :key="action" :value="action">
              {{ action }}
            </option>
          </Select>
        </div>
        <div class="min-w-[180px] flex-1">
          <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
            Utilisateur
          </label>
          <Input
            v-model="localFilters.user"
            type="search"
            placeholder="Nom ou e-mail…"
            class="h-9 text-sm"
          />
        </div>
        <div class="w-[150px]">
          <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
            Du
          </label>
          <Input v-model="localFilters.date_from" type="date" class="h-9 text-sm" />
        </div>
        <div class="w-[150px]">
          <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
            Au
          </label>
          <Input v-model="localFilters.date_to" type="date" class="h-9 text-sm" />
        </div>
        <div class="flex gap-2">
          <Button type="submit" size="sm" class="h-9 gap-1.5">
            <Filter class="h-3.5 w-3.5" />
            Filtrer
          </Button>
          <Button type="button" size="sm" variant="outline" class="h-9" @click="resetFilters">
            Réinitialiser
          </Button>
        </div>
      </form>
    </Card>

    <Card class="overflow-hidden p-0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border/60 text-left text-muted-foreground">
              <th class="px-5 py-3 text-xs font-medium">Date</th>
              <th class="px-5 py-3 text-xs font-medium">Utilisateur</th>
              <th class="px-5 py-3 text-xs font-medium">Action</th>
              <th class="px-5 py-3 text-xs font-medium">Message</th>
              <th class="px-5 py-3 text-xs font-medium">IP</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="log in logs"
              :key="log.id"
              class="border-b border-border/40 last:border-b-0 hover:bg-muted/30"
            >
              <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-foreground">
                  {{ log.user?.name ?? "Système" }}
                </div>
                <div v-if="log.user?.email" class="text-xs text-muted-foreground">
                  {{ log.user.email }}
                </div>
              </td>
              <td class="px-5 py-4">
                <code class="rounded bg-muted px-1.5 py-0.5 text-xs">
                  {{ log.action }}
                </code>
              </td>
              <td class="px-5 py-4 text-foreground">
                {{ log.message }}
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ log.ip_address ?? "—" }}
              </td>
            </tr>

            <tr v-if="logs.length === 0">
              <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                Aucune entrée d'audit pour l'instant.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
  </AuthenticatedLayout>
</template>
