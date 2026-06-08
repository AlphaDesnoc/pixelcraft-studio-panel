<script setup>
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { confirmDialog } from "@/composables/useConfirm.js";
import {
  ExternalLink,
  FolderKanban,
  Pencil,
  Plus,
  Trash2,
  Users,
  ListTodo,
  StickyNote,
  Calendar,
  Search,
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
const search = ref("");

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

const statusDot = (status) =>
  ({
    active: "bg-emerald-400",
    completed: "bg-sky-400",
    archived: "bg-muted-foreground",
  })[status] ?? "bg-muted-foreground";

const initials = (name) =>
  name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();

const filteredProjects = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.projects;
  return props.projects.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      (p.description ?? "").toLowerCase().includes(q),
  );
});

const stats = computed(() => ({
  total: props.projects.length,
  active: props.projects.filter((p) => p.status === "active").length,
  members: props.projects.reduce((sum, p) => sum + (p.members_count ?? 0), 0),
}));
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

    <!-- Bandeau de synthèse -->
    <div class="mb-4 grid grid-cols-3 gap-3">
      <Card class="flex items-center gap-3 p-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <FolderKanban class="h-5 w-5" />
        </span>
        <div>
          <p class="text-2xl font-semibold leading-none tabular-nums">{{ stats.total }}</p>
          <p class="mt-1 text-xs text-muted-foreground">Projets</p>
        </div>
      </Card>
      <Card class="flex items-center gap-3 p-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400">
          <span class="h-2.5 w-2.5 rounded-full bg-emerald-400" />
        </span>
        <div>
          <p class="text-2xl font-semibold leading-none tabular-nums">{{ stats.active }}</p>
          <p class="mt-1 text-xs text-muted-foreground">Actifs</p>
        </div>
      </Card>
      <Card class="flex items-center gap-3 p-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-400">
          <Users class="h-5 w-5" />
        </span>
        <div>
          <p class="text-2xl font-semibold leading-none tabular-nums">{{ stats.members }}</p>
          <p class="mt-1 text-xs text-muted-foreground">Membres au total</p>
        </div>
      </Card>
    </div>

    <Card class="overflow-hidden p-0">
      <!-- Barre d'outils -->
      <div class="flex items-center justify-between gap-3 border-b border-border/60 px-4 py-3">
        <div class="relative w-full max-w-xs">
          <Search
            class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
          />
          <input
            v-model="search"
            type="search"
            placeholder="Rechercher un projet…"
            class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring"
          />
        </div>
        <span class="shrink-0 text-xs text-muted-foreground">
          {{ filteredProjects.length }} / {{ projects.length }}
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border/60 text-left text-muted-foreground">
              <th class="px-5 py-3 text-xs font-medium">Projet</th>
              <th class="px-5 py-3 text-xs font-medium">Statut</th>
              <th class="px-4 py-3 text-xs font-medium">
                <span class="inline-flex items-center gap-1"><Users class="h-3.5 w-3.5" /> Membres</span>
              </th>
              <th class="px-4 py-3 text-xs font-medium">
                <span class="inline-flex items-center gap-1"><ListTodo class="h-3.5 w-3.5" /> Tâches</span>
              </th>
              <th class="px-4 py-3 text-xs font-medium">
                <span class="inline-flex items-center gap-1"><StickyNote class="h-3.5 w-3.5" /> Notes</span>
              </th>
              <th class="px-4 py-3 text-xs font-medium">
                <span class="inline-flex items-center gap-1"><Calendar class="h-3.5 w-3.5" /> Events</span>
              </th>
              <th class="px-5 py-3 text-right text-xs font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="project in filteredProjects"
              :key="project.id"
              class="group border-b border-border/40 transition-colors last:border-b-0 hover:bg-muted/30"
            >
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <Avatar
                    :src="project.image_url ?? ''"
                    :fallback="initials(project.name)"
                    size="md"
                    rounded="lg"
                  />
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold leading-tight text-foreground">
                      {{ project.name }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                      {{ project.description || "Pas de description" }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3">
                <Badge :variant="statusVariant(project.status)" class="gap-1.5">
                  <span class="h-1.5 w-1.5 rounded-full" :class="statusDot(project.status)" />
                  {{ statuses[project.status] ?? project.status }}
                </Badge>
              </td>
              <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ project.members_count }}</td>
              <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ project.tasks_count }}</td>
              <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ project.notes_count }}</td>
              <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ project.events_count }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-1.5">
                  <Link
                    :href="route('projects.show', project.slug)"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-background/40 px-2.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                  >
                    <ExternalLink class="h-3.5 w-3.5" />
                    Ouvrir
                  </Link>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background/40 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Modifier"
                    @click="openEdit(project)"
                  >
                    <Pencil class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-rose-500/30 bg-rose-500/10 text-rose-300 transition-colors hover:bg-rose-500/20 hover:text-rose-200"
                    aria-label="Supprimer"
                    @click="confirmDelete(project)"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="projects.length === 0">
              <td colspan="7" class="px-5 py-12 text-center text-sm text-muted-foreground">
                Aucun projet pour l'instant. Cliquez sur
                <span class="font-medium text-foreground">+ Nouveau projet</span>
                pour commencer.
              </td>
            </tr>
            <tr v-else-if="filteredProjects.length === 0">
              <td colspan="7" class="px-5 py-12 text-center text-sm text-muted-foreground">
                Aucun projet ne correspond à « {{ search }} ».
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <ProjectFormDialog
      v-model:open="dialogOpen"
      :project="editingProject"
      :statuses="statuses"
      :project-templates="projectTemplates"
    />
  </AuthenticatedLayout>
</template>
