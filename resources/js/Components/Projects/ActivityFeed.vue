<script setup>
import { Activity, Download } from "lucide-vue-next";

const props = defineProps({
  activityLogs: { type: Array, default: () => [] },
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
</script>

<template>
  <div class="rounded-xl border border-border bg-card">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
      <div class="flex items-center gap-2">
        <Activity class="h-4 w-4 text-primary" />
        <h3 class="text-sm font-semibold text-foreground">Activité récente</h3>
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

    <ul v-if="activityLogs.length > 0" class="divide-y divide-border/40">
      <li
        v-for="log in activityLogs"
        :key="log.id"
        class="flex gap-3 px-4 py-3"
      >
        <div
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
        >
          {{ initials(log.user?.name) }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm text-foreground">
            <span class="font-medium">{{ log.user?.name ?? "Système" }}</span>
            <span class="text-muted-foreground"> · </span>
            <span>{{ log.message }}</span>
          </p>
          <p class="mt-0.5 text-[11px] text-muted-foreground">
            {{ formatRelative(log.created_at) }}
          </p>
        </div>
      </li>
    </ul>

    <p v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
      Aucune activité pour le moment
    </p>
  </div>
</template>
