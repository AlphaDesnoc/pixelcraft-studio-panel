<script setup>
import { Activity, Download } from "lucide-vue-next";

const props = defineProps({
  groups: { type: Array, default: () => [] },
  exportUrl: { type: String, default: null },
});

function formatRelative(iso) {
  if (!iso) return "";
  const date = new Date(iso);
  const diffMs = date.getTime() - Date.now();
  const rtf = new Intl.RelativeTimeFormat("fr", { numeric: "auto" });
  const minutes = Math.round(diffMs / 60000);
  if (Math.abs(minutes) < 60) return rtf.format(minutes, "minute");
  const hours = Math.round(minutes / 60);
  if (Math.abs(hours) < 24) return rtf.format(hours, "hour");
  const days = Math.round(hours / 24);
  if (Math.abs(days) < 30) return rtf.format(days, "day");
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(date);
}

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

function actionLabel(action) {
  return (
    {
      task_created: "Création",
      task_updated: "Modification",
      task_deleted: "Suppression",
      task_moved: "Déplacement",
      task_archived: "Archivage",
      task_unarchived: "Désarchivage",
      task_duplicated: "Duplication",
      task_commented: "Commentaire",
    }[action] ?? "Activité"
  );
}
</script>

<template>
  <div class="flex max-h-[min(520px,58dvh)] min-h-0 flex-col overflow-hidden rounded-xl border border-border bg-card">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
      <div class="flex items-center gap-2">
        <Activity class="h-4 w-4 text-primary" />
        <h3 class="text-sm font-semibold text-foreground">Activité des tâches</h3>
      </div>
      <a
        v-if="exportUrl"
        :href="exportUrl"
        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
      >
        <Download class="h-3.5 w-3.5" />
        Exporter CSV
      </a>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
      <div v-if="groups.length" class="divide-y divide-border/40">
        <section
          v-for="group in groups"
          :key="group.rank?.id ?? 'global'"
          class="px-4 py-3"
        >
        <div class="mb-2 flex items-center gap-2">
          <span
            class="h-2 w-2 shrink-0 rounded-full"
            :style="{ backgroundColor: group.rank?.color ?? '#6366f1' }"
          />
          <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {{ group.rank?.name ?? "Rank" }}
          </h4>
        </div>

        <ul v-if="(group.logs ?? []).length" class="flex flex-col gap-2">
          <li
            v-for="log in group.logs"
            :key="log.id"
            class="flex gap-3 rounded-lg border border-border/40 bg-muted/10 px-3 py-2.5"
          >
            <div
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
            >
              {{ initials(log.user?.name) }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                >
                  {{ actionLabel(log.action) }}
                </span>
                <span class="text-[11px] text-muted-foreground">
                  {{ formatRelative(log.created_at) }}
                </span>
              </div>
              <p class="mt-1 text-sm text-foreground">
                <span class="font-medium">{{ log.user?.name ?? "Système" }}</span>
                <span class="text-muted-foreground"> · </span>
                <span>{{ log.message }}</span>
              </p>
            </div>
          </li>
        </ul>

        <p v-else class="text-xs text-muted-foreground">
          Aucune activité récente pour cet espace.
        </p>
        </section>
      </div>

      <p v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
        Aucune activité de tâche pour le moment
      </p>
    </div>
  </div>
</template>
