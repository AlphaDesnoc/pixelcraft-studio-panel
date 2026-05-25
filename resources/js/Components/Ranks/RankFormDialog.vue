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
  rank: { type: Object, default: null },
});

const emits = defineEmits(["update:open"]);

const isEdit = computed(() => Boolean(props.rank));

const form = useForm({
  name: "",
  description: "",
  color: "#7c5cff",
});

function reset() {
  if (isEdit.value && props.rank) {
    form.name = props.rank.name ?? "";
    form.description = props.rank.description ?? "";
    form.color = props.rank.color ?? "#7c5cff";
  } else {
    form.name = "";
    form.description = "";
    form.color = "#7c5cff";
  }
  form.clearErrors();
}

watch(
  () => [props.open, props.rank?.id],
  ([open]) => {
    if (open) reset();
  },
);

function submit() {
  const onSuccess = () => emits("update:open", false);
  if (isEdit.value) {
    form.put(route("projects.ranks.update", [props.projectSlug, props.rank.id]), {
      preserveScroll: true,
      onSuccess,
    });
  } else {
    form.post(route("projects.ranks.store", props.projectSlug), {
      preserveScroll: true,
      onSuccess,
    });
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-sm">
      <DialogHeader>
        <DialogTitle>
          {{ isEdit ? "Modifier le rank" : "Nouveau rank" }}
        </DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
        <div class="flex flex-col gap-1">
          <Input
            v-model="form.name"
            type="text"
            placeholder="Nom du rank (ex: Dev, Design, Marketing)"
            required
            autofocus
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-1">
          <Textarea v-model="form.description" placeholder="Description" rows="3" />
          <InputError :message="form.errors.description" />
        </div>

        <div class="flex items-center justify-between rounded-md border border-input bg-background/40 px-2.5 py-2">
          <span class="text-xs text-muted-foreground">Couleur du rank</span>
          <ColorPicker v-model="form.color" />
        </div>
        <InputError :message="form.errors.color" />

        <Button type="submit" class="h-10 w-full" :disabled="form.processing">
          {{
            form.processing
              ? "Enregistrement…"
              : isEdit
                ? "Enregistrer"
                : "Créer le rank"
          }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>
