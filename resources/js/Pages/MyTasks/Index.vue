<script setup>
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { CalendarDays, ExternalLink, TriangleAlert } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Badge } from "@/Components/ui/badge";
import { Card } from "@/Components/ui/card";

const props = defineProps({
  tasks: { type: Array, default: () => [] },
  priorities: { type: Object, default: () => ({}) },
});

const priorityVariants = {
  low: "secondary",
  medium: "default",
  high: "default",
  urgent: "destructive",
};

function formatDueDate(iso) {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(new Date(iso));
}

const overdueCount = computed(
  () => props.tasks.filter((task) => task.is_overdue).length,
);
</script>

<template>
  <Head title="Mes tâches" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h1 class="text-xl font-semibold tracking-tight">Mes tâches</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Tâches qui vous sont assignées et non terminées
          <span v-if="overdueCount > 0" class="text-rose-400">
            · {{ overdueCount }} en retard
          </span>
        </p>
      </div>
    </template>

    <Card class="overflow-hidden p-0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border/60 text-left text-muted-foreground">
              <th class="px-5 py-3 text-xs font-medium">Tâche</th>
              <th class="px-5 py-3 text-xs font-medium">Projet</th>
              <th class="px-5 py-3 text-xs font-medium">Liste</th>
              <th class="px-5 py-3 text-xs font-medium">Priorité</th>
              <th class="px-5 py-3 text-xs font-medium">Échéance</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="task in props.tasks"
              :key="task.id"
              class="border-b border-border/40 last:border-b-0 hover:bg-muted/30"
              :class="task.is_overdue ? 'bg-rose-500/5' : ''"
            >
              <td class="px-5 py-4">
                <div class="flex items-center gap-2">
                  <span
                    class="font-semibold text-foreground"
                    :class="task.is_overdue ? 'text-rose-300' : ''"
                  >
                    {{ task.title }}
                  </span>
                  <Badge
                    v-if="task.is_overdue"
                    variant="destructive"
                    class="gap-1 text-[10px]"
                  >
                    <TriangleAlert class="h-3 w-3" />
                    En retard
                  </Badge>
                </div>
              </td>
              <td class="px-5 py-4">
                <Link
                  v-if="task.project"
                  :href="route('projects.show', task.project.slug)"
                  class="inline-flex items-center gap-1 text-primary hover:underline"
                >
                  {{ task.project.name }}
                  <ExternalLink class="h-3 w-3" />
                </Link>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ task.list_name ?? "—" }}
              </td>
              <td class="px-5 py-4">
                <Badge :variant="priorityVariants[task.priority] ?? 'secondary'">
                  {{ priorities[task.priority] ?? task.priority }}
                </Badge>
              </td>
              <td class="px-5 py-4">
                <span
                  class="inline-flex items-center gap-1"
                  :class="task.is_overdue ? 'font-medium text-rose-400' : 'text-muted-foreground'"
                >
                  <CalendarDays class="h-3.5 w-3.5" />
                  {{ formatDueDate(task.due_date) }}
                </span>
              </td>
            </tr>

            <tr v-if="props.tasks.length === 0">
              <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                Aucune tâche assignée pour le moment.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
  </AuthenticatedLayout>
</template>
