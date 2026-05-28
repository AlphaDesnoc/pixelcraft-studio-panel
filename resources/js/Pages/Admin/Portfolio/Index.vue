<script setup>
import { Link } from "@inertiajs/vue3";
import { AlertTriangle, Briefcase, Clock, Layers } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AdminTabs from "@/Components/AdminTabs.vue";
import { Badge } from "@/Components/ui/badge";

defineProps({
  projects: { type: Array, default: () => [] },
  capacityAlerts: { type: Array, default: () => [] },
  velocityTrend: { type: Array, default: () => [] },
  summary: { type: Object, required: true },
  capacityThreshold: { type: Number, default: 15 },
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
      <AdminTabs active="portfolio" />

      <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-foreground">Portfolio projets</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Vue cross-projets : charge, échéances, bugs SLA et alertes capacité ranks.
          </p>
        </div>
        <a
          :href="route('admin.portfolio.export')"
          class="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-muted"
        >
          Export CSV
        </a>
      </header>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground">Projets</p>
          <p class="mt-1 text-2xl font-semibold">{{ summary.projects }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground">Tâches ouvertes</p>
          <p class="mt-1 text-2xl font-semibold">{{ summary.tasks_open }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground">En retard</p>
          <p class="mt-1 text-2xl font-semibold text-rose-400">{{ summary.tasks_overdue }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground">Bugs ouverts</p>
          <p class="mt-1 text-2xl font-semibold">{{ summary.bugs_open }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground">SLA dépassés</p>
          <p class="mt-1 text-2xl font-semibold text-amber-400">{{ summary.sla_breached }}</p>
        </div>
      </div>

      <section v-if="velocityTrend.length" class="rounded-xl border border-border bg-card p-4">
        <h2 class="mb-3 text-sm font-semibold">Vélocité & SLA par rank</h2>
        <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <li
            v-for="(row, idx) in velocityTrend.slice(0, 12)"
            :key="idx"
            class="rounded-lg border border-border/60 px-3 py-2 text-xs"
          >
            <p class="font-medium">{{ row.project }} · {{ row.rank }}</p>
            <p class="text-muted-foreground">Vélocité {{ row.velocity }} · SLA {{ row.sla_breached }}</p>
          </li>
        </ul>
      </section>

      <section
        v-if="capacityAlerts.length"
        class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4"
      >
        <h2 class="flex items-center gap-2 text-sm font-semibold text-amber-200">
          <AlertTriangle class="h-4 w-4" />
          Ranks surchargés (≥ {{ capacityThreshold }} tâches ouvertes / membre)
        </h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li
            v-for="(alert, index) in capacityAlerts"
            :key="`${alert.project?.slug}-${alert.user_name}-${index}`"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-background/40 px-3 py-2"
          >
            <span>
              <strong>{{ alert.user_name }}</strong>
              · {{ alert.rank_name }}
              · {{ alert.project?.name }}
            </span>
            <Badge variant="secondary">{{ alert.open_tasks }} tâches ouvertes</Badge>
          </li>
        </ul>
      </section>

      <div class="overflow-hidden rounded-xl border border-border bg-card">
        <table class="min-w-full text-sm">
          <thead class="border-b border-border bg-muted/20 text-left text-xs uppercase tracking-wide text-muted-foreground">
            <tr>
              <th class="px-4 py-3">Projet</th>
              <th class="px-4 py-3">Membres</th>
              <th class="px-4 py-3">Ouvertes</th>
              <th class="px-4 py-3">Retard</th>
              <th class="px-4 py-3">Bugs</th>
              <th class="px-4 py-3">SLA</th>
              <th class="px-4 py-3">Capacité</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/50">
            <tr v-for="project in projects" :key="project.id" class="hover:bg-muted/10">
              <td class="px-4 py-3">
                <Link :href="project.url" class="font-medium text-primary hover:underline">
                  {{ project.name }}
                </Link>
              </td>
              <td class="px-4 py-3 tabular-nums">{{ project.members_count }}</td>
              <td class="px-4 py-3 tabular-nums">{{ project.tasks_open }}</td>
              <td class="px-4 py-3 tabular-nums text-rose-400">{{ project.tasks_overdue }}</td>
              <td class="px-4 py-3 tabular-nums">{{ project.bugs_open }}</td>
              <td class="px-4 py-3 tabular-nums">{{ project.sla_breached }}</td>
              <td class="px-4 py-3">
                <Badge v-if="project.capacity_alerts" variant="destructive">
                  {{ project.capacity_alerts }} alerte(s)
                </Badge>
                <span v-else class="text-muted-foreground">OK</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
