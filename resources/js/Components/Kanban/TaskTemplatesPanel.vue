<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { LayoutTemplate, Pencil, Plus, Trash2 } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  projectSlug: { type: String, required: true },
  templates: { type: Array, default: () => [] },
  priorities: { type: Object, default: () => ({}) },
  ranks: { type: Array, default: () => [] },
  canWrite: { type: Boolean, default: false },
});

const open = ref(false);
const editing = ref(null);
const form = ref(emptyForm());

function emptyForm() {
  return {
    name: "",
    title: "",
    description: "",
    priority: "medium",
    rank_id: "",
    checklistText: "",
  };
}

const dialogTitle = computed(() =>
  editing.value ? "Modifier le modèle" : "Nouveau modèle de tâche",
);

function openCreate() {
  editing.value = null;
  form.value = emptyForm();
  open.value = true;
}

function openEdit(template) {
  editing.value = template;
  form.value = {
    name: template.name ?? "",
    title: template.title ?? "",
    description: template.description ?? "",
    priority: template.priority ?? "medium",
    rank_id: template.rank_id ? String(template.rank_id) : "",
    checklistText: (template.checklist ?? []).join("\n"),
  };
  open.value = true;
}

function payloadFromForm() {
  const checklist = form.value.checklistText
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);

  return {
    name: form.value.name.trim(),
    title: form.value.title.trim(),
    description: form.value.description.trim() || null,
    priority: form.value.priority || null,
    rank_id: form.value.rank_id ? Number(form.value.rank_id) : null,
    checklist: checklist.length ? checklist : null,
  };
}

function saveTemplate() {
  const data = payloadFromForm();
  if (editing.value) {
    router.put(
      route("projects.tasks.templates.update", [props.projectSlug, editing.value.id]),
      data,
      {
        preserveScroll: true,
        preserveState: true,
        only: ["taskTemplates"],
        onSuccess: () => {
          open.value = false;
        },
      },
    );
    return;
  }

  router.post(route("projects.tasks.templates.store", props.projectSlug), data, {
    preserveScroll: true,
    preserveState: true,
    only: ["taskTemplates"],
    onSuccess: () => {
      open.value = false;
    },
  });
}

function deleteTemplate(template) {
  if (!confirm(`Supprimer le modèle « ${template.name} » ?`)) return;
  router.delete(
    route("projects.tasks.templates.destroy", [props.projectSlug, template.id]),
    {
      preserveScroll: true,
      preserveState: true,
      only: ["taskTemplates"],
    },
  );
}
</script>

<template>
  <section class="rounded-xl border border-border bg-card/40 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="flex items-center gap-2 text-sm font-semibold text-foreground">
          <LayoutTemplate class="h-4 w-4 text-primary" />
          Modèles de tâches
        </h3>
        <p class="mt-1 text-xs text-muted-foreground">
          Pré-remplissez titres, descriptions et checklists pour accélérer la création.
        </p>
      </div>
      <Button v-if="canWrite" size="sm" class="gap-1.5" @click="openCreate">
        <Plus class="h-3.5 w-3.5" />
        Nouveau modèle
      </Button>
    </div>

    <div v-if="!templates.length" class="mt-4 rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
      Aucun modèle pour ce projet.
    </div>

    <ul v-else class="mt-4 divide-y divide-border/50 rounded-lg border border-border/50">
      <li
        v-for="template in templates"
        :key="template.id"
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
      >
        <div class="min-w-0">
          <p class="text-sm font-medium text-foreground">{{ template.name }}</p>
          <p class="truncate text-xs text-muted-foreground">
            {{ template.title }}
            <span v-if="template.priority"> · {{ priorities[template.priority] ?? template.priority }}</span>
          </p>
        </div>
        <div v-if="canWrite" class="flex items-center gap-1">
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Modifier"
            @click="openEdit(template)"
          >
            <Pencil class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
            title="Supprimer"
            @click="deleteTemplate(template)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </li>
    </ul>

    <Dialog v-model:open="open">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ dialogTitle }}</DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="saveTemplate">
          <div class="grid gap-2">
            <Label for="tpl-name">Nom du modèle</Label>
            <Input id="tpl-name" v-model="form.name" required maxlength="80" />
          </div>
          <div class="grid gap-2">
            <Label for="tpl-title">Titre de la tâche</Label>
            <Input id="tpl-title" v-model="form.title" required maxlength="255" />
          </div>
          <div class="grid gap-2">
            <Label for="tpl-desc">Description</Label>
            <Textarea id="tpl-desc" v-model="form.description" rows="3" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
              <Label for="tpl-priority">Priorité</Label>
              <Select id="tpl-priority" v-model="form.priority">
                <option v-for="(label, key) in priorities" :key="key" :value="key">
                  {{ label }}
                </option>
              </Select>
            </div>
            <div class="grid gap-2">
              <Label for="tpl-rank">Rank (optionnel)</Label>
              <Select id="tpl-rank" v-model="form.rank_id">
                <option value="">Tous</option>
                <option v-for="rank in ranks" :key="rank.id" :value="String(rank.id)">
                  {{ rank.name }}
                </option>
              </Select>
            </div>
          </div>
          <div class="grid gap-2">
            <Label for="tpl-checklist">Checklist (une ligne = un item)</Label>
            <Textarea id="tpl-checklist" v-model="form.checklistText" rows="4" placeholder="Préparer les assets&#10;Tester en jeu" />
          </div>
          <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="open = false">Annuler</Button>
            <Button type="submit">{{ editing ? "Enregistrer" : "Créer" }}</Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </section>
</template>
