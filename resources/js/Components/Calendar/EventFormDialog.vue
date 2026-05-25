<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
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
import ColorPicker from "@/Components/ui/ColorPicker.vue";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  event: { type: Object, default: null },
  defaultDate: { type: String, default: null },
  rankId: { type: Number, default: null },
});

const emits = defineEmits(["update:open"]);

const isEdit = computed(() => Boolean(props.event));

const DEFAULT_COLOR = "#7c5cff";

const form = useForm({
  title: "",
  description: "",
  start_at: "",
  end_at: "",
  all_day: false,
  color: DEFAULT_COLOR,
  rank_id: props.rankId,
});

function toLocalInput(iso, options = {}) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const pad = (n) => String(n).padStart(2, "0");
  const base = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  if (options.dateOnly) return base;
  return `${base}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function defaultStart(date) {
  const d = date ? new Date(date) : new Date();
  d.setHours(9, 0, 0, 0);
  return d;
}

function defaultEnd(date) {
  const d = date ? new Date(date) : new Date();
  d.setHours(10, 0, 0, 0);
  return d;
}

function reset() {
  if (isEdit.value && props.event) {
    const dateOnly = props.event.all_day;
    form.title = props.event.title ?? "";
    form.description = props.event.description ?? "";
    form.start_at = toLocalInput(props.event.start_at, { dateOnly });
    form.end_at = toLocalInput(props.event.end_at, { dateOnly });
    form.all_day = Boolean(props.event.all_day);
    form.color = props.event.color ?? DEFAULT_COLOR;
  } else {
    form.title = "";
    form.description = "";
    form.start_at = toLocalInput(defaultStart(props.defaultDate));
    form.end_at = toLocalInput(defaultEnd(props.defaultDate));
    form.all_day = false;
    form.color = DEFAULT_COLOR;
  }
  form.rank_id = props.rankId;
  form.clearErrors();
}

watch(
  () => [props.open, props.event?.id, props.defaultDate],
  ([open]) => {
    if (open) reset();
  },
);

watch(
  () => form.all_day,
  (allDay) => {
    if (allDay) {
      form.start_at = toLocalInput(form.start_at || new Date(), { dateOnly: true });
      form.end_at = toLocalInput(form.end_at || new Date(), { dateOnly: true });
    } else {
      form.start_at = toLocalInput(defaultStart(form.start_at));
      form.end_at = toLocalInput(defaultEnd(form.end_at));
    }
  },
);

function submit() {
  const payload = (data) => ({
    ...data,
    start_at: data.all_day ? `${data.start_at} 00:00:00` : data.start_at,
    end_at: data.all_day ? `${data.end_at} 23:59:59` : data.end_at,
  });

  const onSuccess = () => emits("update:open", false);

  if (isEdit.value) {
    form
      .transform(payload)
      .put(route("projects.events.update", [props.projectSlug, props.event.id]), {
        preserveScroll: true,
        onSuccess,
      });
  } else {
    form
      .transform(payload)
      .post(route("projects.events.store", props.projectSlug), {
        preserveScroll: true,
        onSuccess,
      });
  }
}

function destroy() {
  if (!isEdit.value) return;
  if (!confirm("Supprimer cet événement ?")) return;
  form.delete(
    route("projects.events.destroy", [props.projectSlug, props.event.id]),
    { preserveScroll: true, onSuccess: () => emits("update:open", false) },
  );
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>
          {{ isEdit ? "Modifier l'événement" : "Nouvel événement" }}
        </DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
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

        <div class="grid grid-cols-2 gap-2">
          <div class="flex flex-col gap-1">
            <label class="text-[11px] text-muted-foreground">Début</label>
            <Input
              v-model="form.start_at"
              :type="form.all_day ? 'date' : 'datetime-local'"
              required
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[11px] text-muted-foreground">Fin</label>
            <Input
              v-model="form.end_at"
              :type="form.all_day ? 'date' : 'datetime-local'"
              required
            />
          </div>
        </div>
        <InputError :message="form.errors.start_at || form.errors.end_at" />

        <label class="flex items-center gap-2 text-sm">
          <input
            v-model="form.all_day"
            type="checkbox"
            class="h-4 w-4 rounded border-border accent-primary"
          />
          <span>Journée entière</span>
        </label>

        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] text-muted-foreground">Couleur</label>
          <ColorPicker v-model="form.color" />
          <InputError :message="form.errors.color" />
        </div>

        <div class="flex items-center gap-2">
          <Button type="submit" class="h-10 flex-1" :disabled="form.processing">
            {{ form.processing ? "Enregistrement…" : isEdit ? "Enregistrer" : "Créer" }}
          </Button>
          <Button
            v-if="isEdit"
            type="button"
            variant="outline"
            class="h-10 border-rose-500/30 text-rose-300 hover:bg-rose-500/10 hover:text-rose-200"
            @click="destroy"
          >
            Supprimer
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
