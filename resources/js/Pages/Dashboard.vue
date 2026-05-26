<script setup>
import { computed, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import {
  AlertTriangle,
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
