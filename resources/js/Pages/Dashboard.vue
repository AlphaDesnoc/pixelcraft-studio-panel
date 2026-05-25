<script setup>
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import {
  AlertTriangle,
  CheckCircle2,
  FolderKanban,
  ListTodo,
} from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ProjectCard from "@/Components/ProjectCard.vue";
import StatCard from "@/Components/StatCard.vue";

defineProps({
  stats: {
    type: Object,
    required: true,
  },
  projects: {
    type: Array,
    required: true,
  },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-2">
        <h1 class="text-xl font-semibold tracking-tight">
          Bonjour, {{ user?.name }}
        </h1>
        <span aria-hidden="true">👋</span>
      </div>
      <p class="mt-1 text-sm text-muted-foreground">
        Voici un aperçu de vos projets
      </p>
    </template>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

    <section class="mt-8">
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
