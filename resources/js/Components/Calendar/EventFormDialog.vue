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
import { Select } from "@/Components/ui/select";
import ColorPicker from "@/Components/ui/ColorPicker.vue";
import {
  RECURRENCE_OPTIONS,
  WEEKDAY_OPTIONS,
  recurrenceSummary,
  weekdayFromStartInput,
} from "@/lib/calendarRecurrence.js";

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
  recurrence: "",
  recurrence_weekdays: [],
  recurrence_until: "",
});

const showWeekdayPicker = computed(() => form.recurrence === "weekly");
const recurrenceHint = computed(() =>
  form.recurrence ? recurrenceSummary(form.data()) : "",
);

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

function defaultRecurrenceUntil(startValue) {
  const base = startValue ? new Date(startValue) : new Date();
  if (Number.isNaN(base.getTime())) {
    return "";
  }
  base.setFullYear(base.getFullYear() + 1);
  return toLocalInput(base, { dateOnly: true });
}

function ensureWeeklyWeekday() {
  if (form.recurrence !== "weekly") {
    return;
  }
  if (form.recurrence_weekdays.length > 0) {
    return;
  }
  form.recurrence_weekdays = [weekdayFromStartInput(form.start_at)];
}

function toggleWeekday(day) {
  const current = [...form.recurrence_weekdays];
  const index = current.indexOf(day);
  if (index === -1) {
    current.push(day);
  } else if (current.length > 1) {
    current.splice(index, 1);
  }
  form.recurrence_weekdays = current.sort((a, b) => {
    const order = [1, 2, 3, 4, 5, 6, 0];
    return order.indexOf(a) - order.indexOf(b);
  });
}

function isWeekdaySelected(day) {
  return form.recurrence_weekdays.includes(day);
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
    form.recurrence = props.event.recurrence ?? "";
    form.recurrence_weekdays = [...(props.event.recurrence_weekdays ?? [])];
    form.recurrence_until = props.event.recurrence_until ?? "";
  } else {
    form.title = "";
    form.description = "";
    form.start_at = toLocalInput(defaultStart(props.defaultDate));
    form.end_at = toLocalInput(defaultEnd(props.defaultDate));
    form.all_day = false;
    form.color = DEFAULT_COLOR;
    form.recurrence = "";
    form.recurrence_weekdays = [];
    form.recurrence_until = "";
  }
  form.rank_id = props.rankId;
  ensureWeeklyWeekday();
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

watch(
  () => form.recurrence,
  (recurrence) => {
    if (!recurrence) {
      form.recurrence_weekdays = [];
      form.recurrence_until = "";
      return;
    }

    if (!form.recurrence_until) {
      form.recurrence_until = defaultRecurrenceUntil(form.start_at);
    }

    if (recurrence === "weekly") {
      ensureWeeklyWeekday();
    } else {
      form.recurrence_weekdays = [];
    }
  },
);

watch(
  () => form.start_at,
  () => {
    if (form.recurrence === "weekly" && form.recurrence_weekdays.length === 0) {
      ensureWeeklyWeekday();
    }
  },
);

function submit() {
  const payload = (data) => {
    const next = {
      ...data,
      start_at: data.all_day ? `${data.start_at} 00:00:00` : data.start_at,
      end_at: data.all_day ? `${data.end_at} 23:59:59` : data.end_at,
      recurrence: data.recurrence || null,
      recurrence_weekdays:
        data.recurrence === "weekly" ? data.recurrence_weekdays : null,
      recurrence_until: data.recurrence ? data.recurrence_until || null : null,
    };

    return next;
  };

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
          <label class="text-[11px] text-muted-foreground">Répétition</label>
          <Select v-model="form.recurrence">
            <option
              v-for="option in RECURRENCE_OPTIONS"
              :key="option.value || 'none'"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </Select>
          <InputError :message="form.errors.recurrence" />
          <p v-if="recurrenceHint" class="text-xs text-muted-foreground">
            {{ recurrenceHint }}
          </p>
        </div>

        <div v-if="showWeekdayPicker" class="flex flex-col gap-1.5">
          <label class="text-[11px] text-muted-foreground">Jours de la semaine</label>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="day in WEEKDAY_OPTIONS"
              :key="day.value"
              type="button"
              class="inline-flex h-8 min-w-10 items-center justify-center rounded-md border px-2 text-xs font-medium transition-colors"
              :class="
                isWeekdaySelected(day.value)
                  ? 'border-primary bg-primary/15 text-primary'
                  : 'border-border text-muted-foreground hover:bg-muted/60 hover:text-foreground'
              "
              :title="day.full"
              @click="toggleWeekday(day.value)"
            >
              {{ day.label }}
            </button>
          </div>
          <InputError :message="form.errors.recurrence_weekdays" />
        </div>

        <div v-if="form.recurrence" class="flex flex-col gap-1">
          <label class="text-[11px] text-muted-foreground">Répéter jusqu'au</label>
          <Input v-model="form.recurrence_until" type="date" />
          <InputError :message="form.errors.recurrence_until" />
        </div>

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
