<script setup>
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { confirmDialog } from "@/composables/useConfirm.js";
import {
  ExternalLink,
  Pencil,
  Plus,
  Trash2,
  Users,
  ListTodo,
  StickyNote,
  Calendar,
} from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AdminTabs from "@/Components/AdminTabs.vue";
import ProjectFormDialog from "@/Components/Admin/ProjectFormDialog.vue";
import { Avatar } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card } from "@/Components/ui/card";

const props = defineProps({
  projects: { type: Array, required: true },
  statuses: { type: Object, required: true },
  projectTemplates: { type: Array, default: () => [] },
});

const dialogOpen = ref(false);
const editingProject = ref(null);

const openCreate = () => {
  editingProject.value = null;
  dialogOpen.value = true;
};

const openEdit = (project) => {
  editingProject.value = project;
  dialogOpen.value = true;
};

const confirmDelete = async (project) => {
  if (
    !(await confirmDialog({
      title: "Supprimer le projet",
      message: `Le projet "${project.name}" et toutes ses données seront définitivement supprimés.`,
    }))
  ) {
    return;
  }
  router.delete(route("admin.projects.destroy", project.slug), {
    preserveScroll: true,
  });
};

const statusVariant = (status) =>
  ({
    active: "success",
    completed: "default",
    archived: "secondary",
  })[status] ?? "secondary";

const initials = (name) =>
  name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
</script>

<template>
  <Head title="Projets" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold tracking-tight">Projets</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Créer et gérer tous les projets
          </p>
        </div>

        <Button class="gap-1.5" @click="openCreate">
          <Plus class="h-4 w-4" />
          Nouveau projet
        </Button>
      </div>
    </template>

    <AdminTabs class="mb-4" />

    <div
      v-if="projects.length > 0"
      class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
    >
      <Card v-for="project in projects" :key="project.id" class="p-4">
        <div class="flex items-start gap-3">
          <Avatar
            :src="project.image_url ?? ''"
            :fallback="initials(project.name)"
            size="lg"
            rounded="lg"
          />
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <h3 class="truncate text-sm font-semibold leading-tight">
                {{ project.name }}
              </h3>
              <Badge :variant="statusVariant(project.status)">
                {{ statuses[project.status] ?? project.status }}
              </Badge>
            </div>
            <p class="mt-1 line-clamp-1 text-xs text-muted-foreground">
              {{ project.description || "Pas de description" }}
            </p>
          </div>
        </div>

        <div
          class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted-foreground"
        >
          <span class="inline-flex items-center gap-1">
            <Users class="h-3 w-3" />
            {{ project.members_count }} membres
          </span>
          <span class="inline-flex items-center gap-1">
            <ListTodo class="h-3 w-3" />
            {{ project.tasks_count }} tâches
          </span>
          <span class="inline-flex items-center gap-1">
            <StickyNote class="h-3 w-3" />
            {{ project.notes_count }} notes
          </span>
          <span class="inline-flex items-center gap-1">
            <Calendar class="h-3 w-3" />
            {{ project.events_count }} events
          </span>
        </div>

        <div class="mt-3 flex items-center gap-2">
          <Link
            :href="route('projects.show', project.slug)"
            class="inline-flex h-9 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-md border border-border bg-background/40 px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted"
          >
            <ExternalLink class="h-3.5 w-3.5" />
            Ouvrir
          </Link>
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background/40 px-3 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Modifier"
            @click="openEdit(project)"
          >
            <Pencil class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md border border-rose-500/30 bg-rose-500/10 px-3 text-rose-300 transition-colors hover:bg-rose-500/20 hover:text-rose-200"
            aria-label="Supprimer"
            @click="confirmDelete(project)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </Card>
    </div>

    <Card v-else class="p-10 text-center text-sm text-muted-foreground">
      Aucun projet pour l'instant. Cliquez sur
      <span class="font-medium text-foreground">+ Nouveau projet</span> pour
      commencer.
    </Card>

    <ProjectFormDialog
      v-model:open="dialogOpen"
      :project="editingProject"
      :statuses="statuses"
      :project-templates="projectTemplates"
    />
  </AuthenticatedLayout>
</template>
