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
  note: { type: Object, default: null },
  rankId: { type: Number, default: null },
});

const emits = defineEmits(["update:open"]);

const isEdit = computed(() => Boolean(props.note));

const DEFAULT_COLOR = "#fef3c7";

const form = useForm({
  title: "",
  content: "",
  color: DEFAULT_COLOR,
  rank_id: props.rankId,
});

function reset() {
  if (isEdit.value && props.note) {
    form.title = props.note.title ?? "";
    form.content = props.note.content ?? "";
    form.color = props.note.color ?? DEFAULT_COLOR;
  } else {
    form.title = "";
    form.content = "";
    form.color = DEFAULT_COLOR;
  }
  form.rank_id = props.rankId;
  form.clearErrors();
}

watch(
  () => [props.open, props.note?.id],
  ([open]) => {
    if (open) reset();
  },
);

function submit() {
  const onSuccess = () => emits("update:open", false);
  if (isEdit.value) {
    form.put(route("projects.notes.update", [props.projectSlug, props.note.id]), {
      preserveScroll: true,
      onSuccess,
    });
  } else {
    form.post(route("projects.notes.store", props.projectSlug), {
      preserveScroll: true,
      onSuccess,
    });
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>
          {{ isEdit ? "Modifier la note" : "Nouvelle note" }}
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
            v-model="form.content"
            placeholder="Contenu…"
            rows="5"
          />
          <InputError :message="form.errors.content" />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] text-muted-foreground">Couleur de fond</label>
          <ColorPicker
            v-model="form.color"
            trigger-label="Choisir une couleur"
          />
          <InputError :message="form.errors.color" />
        </div>

        <Button
          type="submit"
          class="h-10 w-full"
          :disabled="form.processing"
        >
          {{ form.processing ? "Enregistrement…" : isEdit ? "Enregistrer" : "Créer" }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>
