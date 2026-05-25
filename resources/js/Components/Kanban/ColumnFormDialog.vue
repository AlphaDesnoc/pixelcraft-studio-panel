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
import { Select } from "@/Components/ui/select";
import ColorPicker from "@/Components/ui/ColorPicker.vue";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  list: { type: Object, default: null },
  statusKinds: { type: Object, required: true },
  rankId: { type: Number, default: null },
});

const emits = defineEmits(["update:open"]);

const isEdit = computed(() => Boolean(props.list));

const DEFAULT_COLOR = "#9ca3af";

const form = useForm({
  name: "",
  color: DEFAULT_COLOR,
  status_kind: "todo",
  rank_id: props.rankId,
});

function reset() {
  if (isEdit.value && props.list) {
    form.name = props.list.name ?? "";
    form.color = props.list.color ?? DEFAULT_COLOR;
    form.status_kind = props.list.status_kind ?? "todo";
  } else {
    form.name = "";
    form.color = DEFAULT_COLOR;
    form.status_kind = "todo";
  }
  form.rank_id = props.rankId;
  form.clearErrors();
}

watch(
  () => [props.open, props.list?.id],
  ([open]) => {
    if (open) reset();
  },
);

function submit() {
  const onSuccess = () => emits("update:open", false);
  if (isEdit.value) {
    form.put(
      route("projects.lists.update", [props.projectSlug, props.list.id]),
      { preserveScroll: true, onSuccess },
    );
  } else {
    form.post(route("projects.lists.store", props.projectSlug), {
      preserveScroll: true,
      onSuccess,
    });
  }
}

function destroy() {
  if (!isEdit.value) return;
  if (!confirm("Supprimer la colonne et toutes ses cartes ?")) return;
  form.delete(
    route("projects.lists.destroy", [props.projectSlug, props.list.id]),
    {
      preserveScroll: true,
      onSuccess: () => emits("update:open", false),
    },
  );
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-sm">
      <DialogHeader>
        <DialogTitle>
          {{ isEdit ? "Modifier la colonne" : "Nouvelle colonne" }}
        </DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3.5" @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
          <Input
            v-model="form.name"
            type="text"
            placeholder="Nom de la colonne"
            required
            autofocus
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] text-muted-foreground">Couleur</label>
          <ColorPicker v-model="form.color" />
          <InputError :message="form.errors.color" />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs text-muted-foreground">
            Comportement (statistiques)
          </label>
          <Select v-model="form.status_kind">
            <option v-for="(label, key) in statusKinds" :key="key" :value="key">
              {{ label }}
            </option>
          </Select>
          <InputError :message="form.errors.status_kind" />
        </div>

        <div class="flex items-center gap-2">
          <Button
            type="submit"
            class="h-10 flex-1"
            :disabled="form.processing"
          >
            {{ form.processing ? "Enregistrement…" : "Enregistrer" }}
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
