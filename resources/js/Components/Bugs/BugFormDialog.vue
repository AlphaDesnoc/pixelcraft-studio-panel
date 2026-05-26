<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  bug: { type: Object, default: null },
  canManage: { type: Boolean, default: false },
  priorities: { type: Object, required: true },
  statuses: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  bugRanks: { type: Array, default: () => [] },
});

const emits = defineEmits(["update:open"]);

const isEdit = computed(() => Boolean(props.bug));

const canFullManage = computed(
  () => Boolean(props.bug?.can_manage ?? props.canManage),
);
const canEditReport = computed(() => Boolean(props.bug?.can_edit));

const title = ref("");
const description = ref("");
const priority = ref("medium");
const status = ref("open");
const assigneeId = ref("");
const assignedRankId = ref("");
const screenshots = ref([]);
const removeScreenshots = ref([]);
const processing = ref(false);
const error = ref("");

watch(
  () => [props.open, props.bug?.id],
  ([open]) => {
    if (!open) return;
    removeScreenshots.value = [];
    screenshots.value = [];
    if (isEdit.value && props.bug) {
      title.value = props.bug.title ?? "";
      description.value = props.bug.description ?? "";
      priority.value = props.bug.priority ?? "medium";
      status.value = props.bug.status ?? "open";
      assigneeId.value = props.bug.assignee?.id ? String(props.bug.assignee.id) : "";
      assignedRankId.value = props.bug.assigned_rank?.id
        ? String(props.bug.assigned_rank.id)
        : "";
    } else {
      title.value = "";
      description.value = "";
      priority.value = "medium";
      status.value = "open";
      assigneeId.value = "";
      assignedRankId.value = "";
    }
    error.value = "";
    processing.value = false;
  },
);

function onFilesChange(e) {
  screenshots.value = Array.from(e.target.files ?? []).slice(0, 5);
}

function submitForm() {
  if (!title.value.trim()) {
    error.value = "Le titre est requis.";
    return;
  }
  processing.value = true;
  error.value = "";

  const form = new FormData();
  form.append("title", title.value.trim());
  form.append("description", description.value);
  form.append("priority", priority.value);
  screenshots.value.forEach((f) => form.append("screenshots[]", f));

  if (isEdit.value && (canFullManage.value || canEditReport.value)) {
    form.append("_method", "PUT");
    if (canFullManage.value) {
      form.append("status", status.value);
      if (assigneeId.value) form.append("assignee_id", assigneeId.value);
      if (assignedRankId.value) form.append("assigned_rank_id", assignedRankId.value);
    }
    removeScreenshots.value.forEach((p) => form.append("remove_screenshots[]", p));

    router.post(route("projects.bugs.update", [props.projectSlug, props.bug.id]), form, {
      preserveScroll: true,
      preserveState: true,
      only: ["bugs"],
      forceFormData: true,
      onSuccess: () => emits("update:open", false),
      onError: (errs) => {
        error.value = Object.values(errs ?? {})[0] ?? "Erreur";
      },
      onFinish: () => (processing.value = false),
    });
    return;
  }

  router.post(route("projects.bugs.store", props.projectSlug), form, {
    preserveScroll: true,
    preserveState: true,
    only: ["bugs"],
    forceFormData: true,
    onSuccess: () => emits("update:open", false),
    onError: (errs) => {
      error.value = Object.values(errs ?? {})[0] ?? "Erreur";
    },
    onFinish: () => (processing.value = false),
  });
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ isEdit ? "Modifier le bug" : "Signaler un bug" }}
        </DialogTitle>
      </DialogHeader>

      <form class="flex max-h-[70vh] flex-col gap-3 overflow-y-auto pr-1" @submit.prevent="submitForm">
        <Input v-model="title" placeholder="Titre du bug" required autofocus />
        <Textarea
          v-model="description"
          placeholder="Description détaillée (étapes pour reproduire, comportement attendu…)"
          rows="4"
        />

        <div class="flex flex-col gap-1.5">
          <label class="text-xs text-muted-foreground">Captures d'écran (optionnel)</label>
          <label
            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-border bg-background/40 px-4 py-6 text-center text-xs text-muted-foreground hover:bg-muted/30"
          >
            <span>Ajouter des images (max 5, 5 Mo chacune)</span>
            <input
              type="file"
              accept="image/*"
              multiple
              class="hidden"
              @change="onFilesChange"
            />
          </label>
          <p v-if="screenshots.length" class="text-xs text-muted-foreground">
            {{ screenshots.length }} fichier(s) sélectionné(s)
          </p>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs text-muted-foreground">Priorité</label>
          <Select v-model="priority">
            <option v-for="(label, key) in priorities" :key="key" :value="key">
              {{ label }}
            </option>
          </Select>
        </div>

        <template v-if="isEdit && canFullManage">
          <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground">Statut</label>
            <Select v-model="status">
              <option v-for="(label, key) in statuses" :key="key" :value="key">
                {{ label }}
              </option>
            </Select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground">Assigné à</label>
            <Select v-model="assigneeId">
              <option value="">— Non assigné —</option>
              <option v-for="m in members" :key="m.id" :value="String(m.id)">
                {{ m.name }}
              </option>
            </Select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground">Rank responsable</label>
            <Select v-model="assignedRankId">
              <option value="">— Aucun —</option>
              <option v-for="r in bugRanks" :key="r.id" :value="String(r.id)">
                {{ r.name }}
              </option>
            </Select>
          </div>
        </template>

        <InputError :message="error" />

        <Button type="submit" class="h-10 w-full" :disabled="processing">
          {{ processing ? "Envoi…" : isEdit ? "Enregistrer" : "Envoyer le rapport" }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>
