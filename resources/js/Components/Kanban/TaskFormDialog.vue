<script setup>
import { ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  defaultListId: { type: [Number, String], default: null },
  lists: { type: Array, required: true },
  members: { type: Array, required: true },
  priorities: { type: Object, required: true },
  taskTemplates: { type: Array, default: () => [] },
});

const emits = defineEmits(["update:open"]);

const selectedTemplateId = ref("");

const form = useForm({
  list_id: "",
  title: "",
  description: "",
  priority: "medium",
  assignee_id: "",
  start_date: "",
  due_date: "",
});

function reset() {
  form.list_id = props.defaultListId ?? props.lists[0]?.id ?? "";
  form.title = "";
  form.description = "";
  form.priority = "medium";
  form.assignee_id = "";
  const today = new Date();
  const inAWeek = new Date();
  inAWeek.setDate(today.getDate() + 7);
  form.start_date = today.toISOString().substring(0, 10);
  form.due_date = inAWeek.toISOString().substring(0, 10);
  selectedTemplateId.value = "";
  form.clearErrors();
}

function applyTemplate() {
  const tpl = props.taskTemplates.find((t) => String(t.id) === selectedTemplateId.value);
  if (!tpl) return;
  form.title = tpl.title ?? form.title;
  form.description = tpl.description ?? form.description;
  form.priority = tpl.priority ?? form.priority;
}

watch(
  () => [props.open, props.defaultListId],
  ([open]) => {
    if (open) reset();
  },
);

function submit() {
  form.post(route("projects.tasks.store", props.projectSlug), {
    preserveScroll: true,
    onSuccess: () => emits("update:open", false),
  });
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>Nouvelle carte</DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
        <div v-if="taskTemplates.length" class="flex flex-col gap-1">
          <label class="text-xs font-medium text-muted-foreground">Modèle</label>
          <div class="flex gap-2">
            <Select v-model="selectedTemplateId" class="flex-1">
              <option value="">— Aucun —</option>
              <option v-for="tpl in taskTemplates" :key="tpl.id" :value="String(tpl.id)">
                {{ tpl.name }}
              </option>
            </Select>
            <Button type="button" variant="outline" size="sm" :disabled="!selectedTemplateId" @click="applyTemplate">
              Appliquer
            </Button>
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <Input
            v-model="form.title"
            type="text"
            placeholder="Titre"
            required
            autofocus
          />
          <InputError :message="form.errors.title" />
        </div>

        <div class="flex flex-col gap-1">
          <Textarea
            v-model="form.description"
            placeholder="Description"
            rows="3"
          />
          <InputError :message="form.errors.description" />
        </div>

        <div class="flex flex-col gap-1">
          <Select v-model="form.list_id">
            <option v-for="list in lists" :key="list.id" :value="list.id">
              {{ list.name }}
            </option>
          </Select>
          <InputError :message="form.errors.list_id" />
        </div>

        <div class="grid grid-cols-2 gap-2">
          <Select v-model="form.priority">
            <option v-for="(label, key) in priorities" :key="key" :value="key">
              {{ label }}
            </option>
          </Select>
          <Select v-model="form.assignee_id">
            <option value="">Non assigné</option>
            <option v-for="m in members" :key="m.id" :value="m.id">
              {{ m.name }}
            </option>
          </Select>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div class="flex flex-col gap-1">
            <label class="text-[11px] text-muted-foreground">
              Date début (Gantt)
            </label>
            <Input v-model="form.start_date" type="date" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[11px] text-muted-foreground">
              Date fin (Gantt)
            </label>
            <Input v-model="form.due_date" type="date" />
          </div>
        </div>

        <p class="rounded-md bg-muted/30 px-3 py-2 text-[11px] text-muted-foreground">
          La progression est calculée automatiquement selon la colonne du Kanban.
        </p>

        <Button
          type="submit"
          class="h-10 w-full"
          :disabled="form.processing"
        >
          {{ form.processing ? "Enregistrement…" : "Enregistrer" }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>
