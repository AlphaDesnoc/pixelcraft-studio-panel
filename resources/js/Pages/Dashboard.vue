<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import {
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  FolderKanban,
  LayoutGrid,
  ListTodo,
} from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ProjectCard from "@/Components/ProjectCard.vue";
import StatCard from "@/Components/StatCard.vue";
import { Button } from "@/Components/ui/button";
import { Switch } from "@/Components/ui/switch";

const props = defineProps({
  stats: { type: Object, required: true },
  projects: { type: Array, required: true },
  dashboardWidgets: { type: Object, default: () => ({ stats: true, projects: true }) },
  availableWidgets: { type: Object, default: () => ({}) },
  weekEvents: { type: Array, default: () => [] },
  myOpenTasks: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const customizeOpen = ref(false);
const localWidgets = ref({ ...props.dashboardWidgets });

const showStats = computed(() => props.dashboardWidgets.stats !== false);
const showProjects = computed(() => props.dashboardWidgets.projects !== false);

function saveWidgets() {
  router.put(route("profile.dashboard-widgets"), { widgets: localWidgets.value }, {
    preserveScroll: true,
    onSuccess: () => {
      customizeOpen.value = false;
    },
  });
}
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight">
              Bonjour, {{ user?.name }}
            </h1>
            <span aria-hidden="true">👋</span>
          </div>
          <p class="mt-1 text-sm text-muted-foreground">
            Voici un aperçu de vos projets
          </p>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="gap-1.5"
          @click="customizeOpen = !customizeOpen"
        >
          <LayoutGrid class="h-3.5 w-3.5" />
          Personnaliser
        </Button>
      </div>
    </template>

    <div
      v-if="customizeOpen"
      class="mb-4 rounded-lg border border-border bg-muted/20 p-4"
    >
      <p class="mb-3 text-sm font-medium text-foreground">Widgets affichés</p>
      <div class="space-y-2">
        <label
          v-for="(label, key) in availableWidgets"
          :key="key"
          class="flex items-center justify-between gap-3 text-sm"
        >
          <span>{{ label }}</span>
          <Switch v-model="localWidgets[key]" />
        </label>
      </div>
      <Button type="button" size="sm" class="mt-3" @click="saveWidgets">
        Enregistrer
      </Button>
    </div>

    <div
      v-if="showStats"
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
    >
      <StatCard label="Projets" :value="stats.projects" color="violet">
        <template #icon><FolderKanban /></template>
      </StatCard>
      <StatCard label="Tâches" :value="stats.tasks" color="blue">
        <template #icon><ListTodo /></template>
      </StatCard>
      <StatCard label="Terminées" :value="stats.completed" color="emerald">
        <template #icon><CheckCircle2 /></template>
      </StatCard>
      <StatCard label="En retard" :value="stats.overdue" color="rose">
        <template #icon><AlertTriangle /></template>
      </StatCard>
    </div>

    <section v-if="weekEvents.length || myOpenTasks.length" class="grid gap-4 lg:grid-cols-2">
      <div v-if="weekEvents.length" class="rounded-xl border border-border bg-card p-4">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-tight">
          <CalendarDays class="h-4 w-4 text-primary" />
          Ma semaine
        </h2>
        <ul class="space-y-2">
          <li v-for="(event, idx) in weekEvents" :key="`${event.id}-${idx}`">
            <Link
              :href="event.url"
              class="flex items-start justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-muted/40"
            >
              <span class="font-medium text-foreground">{{ event.title }}</span>
              <span class="shrink-0 text-xs text-muted-foreground">
                {{ new Date(event.start_at).toLocaleDateString("fr-FR", { weekday: "short", day: "numeric", month: "short" }) }}
              </span>
            </Link>
            <p class="px-2 text-[11px] text-muted-foreground">{{ event.project?.name }}</p>
          </li>
        </ul>
      </div>

      <div v-if="myOpenTasks.length" class="rounded-xl border border-border bg-card p-4">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-tight">
          <ListTodo class="h-4 w-4 text-primary" />
          Mes tâches ouvertes
        </h2>
        <ul class="space-y-2">
          <li v-for="task in myOpenTasks" :key="task.id">
            <Link
              v-if="task.url"
              :href="task.url"
              class="block rounded-lg px-2 py-1.5 text-sm hover:bg-muted/40"
            >
              <span class="font-medium text-foreground">{{ task.title }}</span>
              <span class="mt-0.5 block text-[11px] text-muted-foreground">
                {{ task.project?.name }}
                <template v-if="task.due_date"> · {{ task.due_date }}</template>
              </span>
            </Link>
          </li>
        </ul>
      </div>
    </section>

    <section v-if="showProjects" class="mt-8">
      <h2 class="mb-3 text-sm font-semibold tracking-tight">Mes projets</h2>

      <div
        v-if="projects.length > 0"
        class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
      >
        <ProjectCard
          v-for="project in projects"
          :key="project.id"
          :project="project"
        />
      </div>

      <p v-else class="text-sm text-muted-foreground">
        Vous n'avez aucun projet pour le moment.
      </p>
    </section>
  </AuthenticatedLayout>
</template>
