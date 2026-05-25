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

const props = defineProps({
  open: { type: Boolean, required: true },
  mode: {
    type: String,
    default: "create-folder",
    validator: (v) => ["create-folder", "rename"].includes(v),
  },
  projectSlug: { type: String, required: true },
  node: { type: Object, default: null },
  parentId: { type: [Number, null], default: null },
  rankId: { type: Number, default: null },
});

const emits = defineEmits(["update:open"]);

const name = ref("");
const error = ref("");
const processing = ref(false);

const title = computed(() =>
  props.mode === "create-folder" ? "Nouveau dossier" : "Renommer",
);
const submitLabel = computed(() =>
  props.mode === "create-folder" ? "Créer" : "Enregistrer",
);
const placeholder = computed(() =>
  props.mode === "create-folder" ? "Nom du dossier" : "Nouveau nom",
);

watch(
  () => [props.open, props.mode, props.node?.id],
  ([open]) => {
    if (!open) return;
    name.value = props.mode === "rename" ? props.node?.name ?? "" : "";
    error.value = "";
    processing.value = false;
  },
);

function close() {
  emits("update:open", false);
}

function submit() {
  if (!name.value.trim()) {
    error.value = "Le nom est requis.";
    return;
  }
  processing.value = true;
  if (props.mode === "create-folder") {
    router.post(
      route("projects.files.folder.store", props.projectSlug),
      { name: name.value.trim(), parent_id: props.parentId, rank_id: props.rankId },
      {
        preserveScroll: true,
        preserveState: true,
        only: ["fileNodes"],
        onSuccess: close,
        onError: (errs) => {
          error.value = errs?.name ?? "Erreur";
        },
        onFinish: () => (processing.value = false),
      },
    );
  } else {
    router.put(
      route("projects.files.update", [props.projectSlug, props.node.id]),
      { name: name.value.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        only: ["fileNodes"],
        onSuccess: close,
        onError: (errs) => {
          error.value = errs?.name ?? "Erreur";
        },
        onFinish: () => (processing.value = false),
      },
    );
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-sm">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
        <Input
          v-model="name"
          type="text"
          :placeholder="placeholder"
          autofocus
          required
        />
        <InputError :message="error" />

        <div class="flex items-center justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" @click="close">Annuler</Button>
          <Button type="submit" :disabled="processing">
            {{ processing ? "…" : submitLabel }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
