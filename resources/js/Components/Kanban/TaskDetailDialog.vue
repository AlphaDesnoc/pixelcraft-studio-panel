<script setup>
import { computed, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import {
  AlignLeft,
  CalendarClock,
  CalendarDays,
  CheckSquare,
  Clock,
  ListChecks,
  Paperclip,
  Tag,
  Trash2,
  User as UserIcon,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import Checklist from "./Checklist.vue";
import TaskComments from "./TaskComments.vue";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  task: { type: Object, default: null },
  lists: { type: Array, required: true },
  members: { type: Array, required: true },
  priorities: { type: Object, required: true },
});

const emits = defineEmits(["update:open"]);

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const form = useForm({
  title: "",
  description: "",
  list_id: "",
  priority: "medium",
  assignee_id: "",
  start_date: "",
  due_date: "",
});

const editingDescription = ref(false);
const titleEditing = ref(false);
const uploading = ref(false);
const fileInputRef = ref(null);

const addingChecklist = ref(false);
const checklistName = ref("");

function syncFromTask() {
  if (!props.task) return;
  form.title = props.task.title ?? "";
  form.description = props.task.description ?? "";
  form.list_id = props.task.list_id ?? "";
  form.priority = props.task.priority ?? "medium";
  form.assignee_id = props.task.assignee_id ?? "";
  form.start_date = props.task.start_date ?? "";
  form.due_date = props.task.due_date ?? "";
  form.clearErrors();
  editingDescription.value = false;
  titleEditing.value = false;
  addingChecklist.value = false;
  checklistName.value = "";
}

const checklists = computed(() => props.task?.checklists ?? []);
const comments = computed(() => props.task?.comments ?? []);
const attachments = computed(() => props.task?.attachments ?? []);

function openChecklistForm() {
  addingChecklist.value = true;
  checklistName.value = "Checklist";
}

function submitChecklist() {
  const name = checklistName.value.trim();
  if (!name || !props.task) return;
  router.post(
    route("projects.tasks.checklists.store", [
      props.projectSlug,
      props.task.id,
    ]),
    { name },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
      onSuccess: () => {
        addingChecklist.value = false;
        checklistName.value = "";
      },
    },
  );
}

watch(
  () => [props.open, props.task?.id],
  ([open]) => {
    if (open) syncFromTask();
  },
);

const currentList = computed(
  () => props.lists.find((l) => l.id === Number(form.list_id)) ?? null,
);

const priorityColors = {
  low: "#10b981",
  medium: "#3b82f6",
  high: "#f97316",
  urgent: "#ef4444",
};

const priorityColor = computed(
  () => priorityColors[form.priority] ?? "#3b82f6",
);

const dueLabel = computed(() => {
  if (!form.due_date) return null;
  const d = new Date(form.due_date);
  if (Number.isNaN(d.getTime())) return null;
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(d);
});

function patch(field, value) {
  if (!props.task) return;
  form[field] = value;
  form
    .transform((data) => ({ [field]: data[field] }))
    .put(route("projects.tasks.update", [props.projectSlug, props.task.id]), {
      preserveScroll: true,
      preserveState: true,
    });
}

function saveTitle() {
  titleEditing.value = false;
  if (form.title !== props.task.title) patch("title", form.title);
}

function saveDescription() {
  editingDescription.value = false;
  if (form.description !== (props.task.description ?? "")) {
    patch("description", form.description);
  }
}

function destroyTask() {
  if (!props.task) return;
  if (!confirm("Supprimer cette carte ?")) return;
  form.delete(
    route("projects.tasks.destroy", [props.projectSlug, props.task.id]),
    {
      preserveScroll: true,
      onSuccess: () => emits("update:open", false),
    },
  );
}

function openFilePicker() {
  fileInputRef.value?.click();
}

function onFileSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file || !props.task || uploading.value) return;

  uploading.value = true;
  router.post(
    route("projects.tasks.attachments.store", [
      props.projectSlug,
      props.task.id,
    ]),
    { file },
    {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
      onFinish: () => {
        uploading.value = false;
      },
    },
  );
}

function deleteAttachment(attachment) {
  if (!confirm(`Supprimer « ${attachment.original_name} » ?`)) return;
  router.delete(
    route("projects.attachments.destroy", [
      props.projectSlug,
      attachment.id,
    ]),
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
    },
  );
}

function canDeleteAttachment(attachment) {
  if (isAdmin.value) return true;
  if (attachment.user_id) {
    return attachment.user_id === currentUserId.value;
  }
  return true;
}

function formatFileSize(bytes) {
  if (!bytes) return "";
  if (bytes < 1024) return `${bytes} o`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent
      class="grid max-h-[85vh] w-full max-w-3xl grid-cols-1 gap-5 overflow-y-auto p-6 md:grid-cols-[1fr_220px]"
    >
      <div v-if="task" class="flex flex-col gap-5">
        <header class="flex items-start gap-2">
          <CheckSquare class="mt-1 h-5 w-5 text-muted-foreground" />
          <div class="min-w-0 flex-1">
            <DialogTitle as="h2" class="sr-only">{{ form.title }}</DialogTitle>
            <input
              v-if="titleEditing"
              v-model="form.title"
              class="w-full rounded-md border border-input bg-background/40 px-2 py-1 text-lg font-semibold focus:outline-none focus:ring-2 focus:ring-ring"
              @blur="saveTitle"
              @keydown.enter.prevent="saveTitle"
              @keydown.esc="titleEditing = false"
            />
            <h2
              v-else
              class="cursor-text text-lg font-semibold leading-tight"
              @click="titleEditing = true"
            >
              {{ form.title }}
            </h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
              dans la liste
              <span class="font-medium text-foreground">
                {{ currentList?.name ?? "—" }}
              </span>
            </p>
          </div>
        </header>

        <div class="flex flex-wrap items-center gap-2">
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-white"
            :style="{ backgroundColor: priorityColor }"
          >
            {{ priorities[form.priority] }}
          </span>
          <span
            v-if="dueLabel"
            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs"
            :class="task.is_overdue ? 'bg-rose-500/15 text-rose-400' : ''"
          >
            <CalendarDays class="h-3 w-3" />
            {{ dueLabel }}
          </span>
          <span
            v-if="task.is_overdue"
            class="inline-flex items-center rounded-full bg-rose-500/15 px-2 py-0.5 text-xs font-semibold text-rose-400"
          >
            En retard
          </span>
        </div>

        <section class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <AlignLeft class="h-4 w-4 text-muted-foreground" />
            <h3 class="text-sm font-semibold">Description</h3>
          </div>

          <Textarea
            v-if="editingDescription"
            v-model="form.description"
            rows="4"
            placeholder="Ajouter une description plus détaillée…"
            @blur="saveDescription"
          />
          <button
            v-else
            type="button"
            class="min-h-[60px] rounded-md bg-muted/30 px-3 py-2 text-left text-sm text-foreground transition-colors hover:bg-muted/60"
            @click="editingDescription = true"
          >
            <span v-if="form.description">{{ form.description }}</span>
            <span v-else class="text-muted-foreground">
              Cliquer pour ajouter une description
            </span>
          </button>
        </section>

        <section class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <Paperclip class="h-4 w-4 text-muted-foreground" />
            <h3 class="text-sm font-semibold">Pièces jointes</h3>
          </div>

          <ul v-if="attachments.length > 0" class="flex flex-col gap-1.5">
            <li
              v-for="attachment in attachments"
              :key="attachment.id"
              class="flex items-center justify-between gap-2 rounded-md border border-border/60 bg-muted/20 px-3 py-2"
            >
              <a
                :href="attachment.url"
                target="_blank"
                rel="noopener noreferrer"
                class="min-w-0 flex-1 truncate text-sm text-primary hover:underline"
              >
                {{ attachment.original_name }}
              </a>
              <span class="shrink-0 text-[11px] text-muted-foreground">
                {{ formatFileSize(attachment.size) }}
              </span>
              <button
                v-if="canDeleteAttachment(attachment)"
                type="button"
                class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-rose-400"
                aria-label="Supprimer la pièce jointe"
                @click="deleteAttachment(attachment)"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">
            Aucune pièce jointe
          </p>

          <div>
            <input
              ref="fileInputRef"
              type="file"
              class="hidden"
              @change="onFileSelected"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-8 gap-1.5 text-xs"
              :disabled="uploading"
              @click="openFilePicker"
            >
              <Paperclip class="h-3.5 w-3.5" />
              {{ uploading ? "Envoi…" : "Ajouter un fichier" }}
            </Button>
          </div>
        </section>

        <Checklist
          v-for="cl in checklists"
          :key="cl.id"
          :project-slug="projectSlug"
          :task-id="task.id"
          :checklist="cl"
        />

        <section v-if="addingChecklist" class="flex flex-col gap-2 rounded-md border border-border bg-muted/20 p-3">
          <Input
            v-model="checklistName"
            type="text"
            placeholder="Nom de la checklist"
            autofocus
            @keydown.enter.prevent="submitChecklist"
            @keydown.esc="addingChecklist = false"
          />
          <div class="flex items-center gap-2">
            <Button type="button" size="sm" class="h-8" @click="submitChecklist">
              Ajouter
            </Button>
            <button
              type="button"
              class="text-xs text-muted-foreground hover:text-foreground"
              @click="addingChecklist = false"
            >
              Annuler
            </button>
          </div>
        </section>

        <TaskComments
          :project-slug="projectSlug"
          :task-id="task.id"
          :comments="comments"
        />
      </div>

      <aside v-if="task" class="flex flex-col gap-4 border-l border-border/60 md:pl-4">
        <div class="flex flex-col gap-1.5">
          <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            Ajouter à la carte
          </p>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md bg-muted/40 px-2.5 py-1.5 text-xs text-foreground hover:bg-muted/60"
            @click="openChecklistForm"
          >
            <ListChecks class="h-3.5 w-3.5" />
            Checklist
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md bg-muted/40 px-2.5 py-1.5 text-xs text-foreground hover:bg-muted/60"
            @click="openFilePicker"
          >
            <Paperclip class="h-3.5 w-3.5" />
            Pièce jointe
          </button>
        </div>

        <div class="flex flex-col gap-3">
          <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            Détails
          </p>

          <div class="flex flex-col gap-1">
            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
              <UserIcon class="h-3 w-3" /> Membre
            </label>
            <Select
              v-model="form.assignee_id"
              class="h-8 text-xs"
              @change="patch('assignee_id', form.assignee_id || null)"
            >
              <option value="">Non assigné</option>
              <option v-for="m in members" :key="m.id" :value="m.id">
                {{ m.name }}
              </option>
            </Select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
              <Tag class="h-3 w-3" /> Priorité
            </label>
            <Select
              v-model="form.priority"
              class="h-8 text-xs"
              @change="patch('priority', form.priority)"
            >
              <option v-for="(label, key) in priorities" :key="key" :value="key">
                {{ label }}
              </option>
            </Select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
              <Clock class="h-3 w-3" /> Liste
            </label>
            <Select
              v-model="form.list_id"
              class="h-8 text-xs"
              @change="patch('list_id', Number(form.list_id))"
            >
              <option v-for="list in lists" :key="list.id" :value="list.id">
                {{ list.name }}
              </option>
            </Select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
              <CalendarDays class="h-3 w-3" /> Échéance
            </label>
            <Input
              v-model="form.due_date"
              type="date"
              class="h-8 text-xs"
              @change="patch('due_date', form.due_date || null)"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
              <CalendarClock class="h-3 w-3" /> Début (Gantt)
            </label>
            <Input
              v-model="form.start_date"
              type="date"
              class="h-8 text-xs"
              @change="patch('start_date', form.start_date || null)"
            />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            Actions
          </p>
          <Button
            type="button"
            variant="outline"
            class="h-8 justify-start gap-1.5 border-rose-500/30 text-xs text-rose-300 hover:bg-rose-500/10 hover:text-rose-200"
            @click="destroyTask"
          >
            <Trash2 class="h-3.5 w-3.5" />
            Supprimer
          </Button>
        </div>
      </aside>
    </DialogContent>
  </Dialog>
</template>
