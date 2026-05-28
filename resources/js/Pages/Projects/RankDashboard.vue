<script setup>
import { Head, Link } from "@inertiajs/vue3";
import {
  ArrowLeft,
  Download,
  Gauge,
  Shield,
  Timer,
  TriangleAlert,
  Users as UsersIcon,
} from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Badge } from "@/Components/ui/badge";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";

defineProps({
  project: {
    type: Object,
    required: true,
  },
  ranks: {
    type: Array,
    default: () => [],
  },
  capacity_threshold: {
    type: Number,
    default: 15,
  },
});

function formatHours(value) {
  if (value == null || Number.isNaN(Number(value))) {
    return "—";
  }
  const hours = Number(value);
  if (hours < 1) {
    return `${Math.round(hours * 60)} min`;
  }
  return `${hours.toFixed(1)} h`;
}
</script>

<template>
  <Head :title="`Synthèse des ranks · ${project.name}`" />

  <AuthenticatedLayout>
    <div class="flex flex-col gap-5">
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex flex-col gap-1">
          <Link
            :href="route('projects.show', project.slug)"
            class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
          >
            <ArrowLeft class="h-3 w-3" />
            Retour au projet
          </Link>
          <h1 class="text-2xl font-semibold tracking-tight">
            Synthèse des ranks
          </h1>
          <p class="text-sm text-muted-foreground">{{ project.name }}</p>
        </div>
        <a
          :href="route('projects.ranks.dashboard.export', project.slug)"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border px-3 text-xs font-medium hover:bg-muted/50"
        >
          <Download class="h-3.5 w-3.5" />
          Export CSV
        </a>
      </header>

      <div
        v-if="ranks.length === 0"
        class="rounded-xl border border-dashed border-border bg-card/30 px-6 py-12 text-center text-sm text-muted-foreground"
      >
        Aucun rank défini pour ce projet.
      </div>

      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Card
          v-for="rank in ranks"
          :key="rank.id"
          class="overflow-hidden border-border/60"
        >
          <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: rank.color || '#6366f1' }"
                />
                <CardTitle class="text-base">{{ rank.name }}</CardTitle>
              </div>
              <Badge variant="outline" class="gap-1 text-[10px]">
                <UsersIcon class="h-3 w-3" />
                {{ rank.members_count }}
              </Badge>
            </div>
            <CardDescription class="text-xs">
              <template v-if="rank.responsible">
                Responsable : {{ rank.responsible.name }}
              </template>
              <template v-else>Aucun responsable désigné</template>
              <template v-if="rank.manages_bugs">
                · Gestion des bugs
              </template>
            </CardDescription>
          </CardHeader>
          <CardContent class="grid grid-cols-2 gap-2 text-sm">
            <div class="rounded-lg bg-muted/30 px-3 py-2">
              <p class="text-[10px] uppercase text-muted-foreground">Tâches ouvertes</p>
              <p class="mt-1 text-lg font-semibold">{{ rank.stats.open_tasks }}</p>
            </div>
            <div
              class="rounded-lg px-3 py-2"
              :class="rank.stats.overdue_tasks > 0 ? 'bg-rose-500/15' : 'bg-muted/30'"
            >
              <p class="flex items-center gap-1 text-[10px] uppercase text-muted-foreground">
                <TriangleAlert
                  class="h-3 w-3"
                  :class="rank.stats.overdue_tasks > 0 ? 'text-rose-400' : ''"
                />
                En retard
              </p>
              <p
                class="mt-1 text-lg font-semibold"
                :class="rank.stats.overdue_tasks > 0 ? 'text-rose-400' : ''"
              >
                {{ rank.stats.overdue_tasks }}
              </p>
            </div>
            <div
              v-if="rank.stats.velocity != null"
              class="rounded-lg bg-muted/30 px-3 py-2"
            >
              <p class="flex items-center gap-1 text-[10px] uppercase text-muted-foreground">
                <Gauge class="h-3 w-3" />
                Vélocité (7j)
              </p>
              <p class="mt-1 text-lg font-semibold">{{ rank.stats.velocity }}</p>
            </div>
            <div
              v-if="rank.stats.avg_bug_resolution_hours != null"
              class="rounded-lg bg-muted/30 px-3 py-2"
            >
              <p class="flex items-center gap-1 text-[10px] uppercase text-muted-foreground">
                <Timer class="h-3 w-3" />
                Résolution bugs
              </p>
              <p class="mt-1 text-lg font-semibold">
                {{ formatHours(rank.stats.avg_bug_resolution_hours) }}
              </p>
            </div>
            <div
              v-if="rank.stats.sla_breached != null"
              class="rounded-lg px-3 py-2"
              :class="rank.stats.sla_breached > 0 ? 'bg-rose-500/15' : 'bg-muted/30'"
            >
              <p class="flex items-center gap-1 text-[10px] uppercase text-muted-foreground">
                <TriangleAlert
                  class="h-3 w-3"
                  :class="rank.stats.sla_breached > 0 ? 'text-rose-400' : ''"
                />
                SLA dépassés
              </p>
              <p
                class="mt-1 text-lg font-semibold"
                :class="rank.stats.sla_breached > 0 ? 'text-rose-400' : ''"
              >
                {{ rank.stats.sla_breached }}
              </p>
            </div>
            <div v-if="rank.manages_bugs" class="col-span-2 rounded-lg bg-muted/30 px-3 py-2">
              <p class="text-[10px] uppercase text-muted-foreground">Bugs ouverts</p>
              <p class="mt-1 text-lg font-semibold">{{ rank.stats.open_bugs }}</p>
            </div>
            <div
              v-if="rank.burndown?.length"
              class="col-span-2 space-y-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2"
            >
              <p class="text-[10px] font-medium uppercase text-muted-foreground">
                Burndown (4 semaines)
              </p>
              <div class="flex items-end gap-1.5" style="height: 4rem">
                <div
                  v-for="week in rank.burndown"
                  :key="week.label"
                  class="flex flex-1 flex-col items-center gap-1"
                >
                  <div
                    class="w-full rounded-t bg-primary/70"
                    :style="{ height: `${Math.max(4, week.completed * 8)}px` }"
                    :title="`${week.completed} terminées`"
                  />
                  <span class="text-[9px] text-muted-foreground">{{ week.label }}</span>
                </div>
              </div>
            </div>
            <div
              v-if="rank.capacity_alerts?.length"
              class="col-span-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200"
            >
              Charge élevée (&gt;{{ capacity_threshold }} tâches) :
              {{ rank.capacity_alerts.map((m) => m.name).join(", ") }}
            </div>
            <div
              v-if="rank.member_workload?.length"
              class="col-span-2 space-y-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2"
            >
              <p class="text-[10px] font-medium uppercase text-muted-foreground">
                Charge par membre
              </p>
              <div
                v-for="member in rank.member_workload"
                :key="member.id"
                class="flex items-center justify-between gap-2 text-xs"
              >
                <span class="truncate font-medium text-foreground">{{ member.name }}</span>
                <span class="shrink-0 tabular-nums text-muted-foreground">
                  {{ member.open_tasks }} ouvertes
                  <span v-if="member.overdue_tasks > 0" class="text-rose-400">
                    · {{ member.overdue_tasks }} retard
                  </span>
                  <span v-if="member.stale_tasks > 0" class="text-amber-400">
                    · {{ member.stale_tasks }} inactives
                  </span>
                  <span v-if="member.over_capacity" class="text-amber-400"> · surcharge</span>
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <footer class="text-xs text-muted-foreground">
        <Link
          :href="route('projects.ranks.index', project.slug)"
          class="inline-flex items-center gap-1 text-primary hover:underline"
        >
          <Shield class="h-3 w-3" />
          Modifier la configuration des ranks
        </Link>
      </footer>
    </div>
  </AuthenticatedLayout>
</template>
