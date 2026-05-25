<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";

const props = defineProps({
  open: { type: Boolean, required: true },
  title: { type: String, default: "Ajouter un membre au rank" },
  submitLabel: { type: String, default: "Ajouter" },
  emptyLabel: { type: String, default: "Choisir un membre du projet" },
  projectSlug: { type: String, required: true },
  rank: { type: Object, default: null },
  candidates: { type: Array, default: () => [] },
  mode: {
    type: String,
    default: "add-member",
    validator: (v) => ["add-member", "set-responsible"].includes(v),
  },
});

const emits = defineEmits(["update:open"]);

const selected = ref("");
const processing = ref(false);

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    selected.value = "";
    processing.value = false;
  },
);

function close() {
  emits("update:open", false);
}

function submit() {
  if (!props.rank) return;
  processing.value = true;
  const userId = selected.value === "" ? null : Number(selected.value);

  if (props.mode === "add-member") {
    if (!userId) {
      processing.value = false;
      return;
    }
    router.post(
      route("projects.ranks.members.add", [props.projectSlug, props.rank.id]),
      { user_id: userId },
      {
        preserveScroll: true,
        preserveState: true,
        only: ["ranks", "members"],
        onSuccess: close,
        onFinish: () => (processing.value = false),
      },
    );
  } else {
    router.post(
      route("projects.ranks.responsible", [props.projectSlug, props.rank.id]),
      { user_id: userId },
      {
        preserveScroll: true,
        preserveState: true,
        only: ["ranks", "members"],
        onSuccess: close,
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
        <select
          v-model="selected"
          class="h-10 w-full rounded-md border border-input bg-background px-2.5 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring"
        >
          <option value="" disabled>{{ emptyLabel }}</option>
          <option v-if="mode === 'set-responsible'" :value="''">— Aucun responsable —</option>
          <option v-for="c in candidates" :key="c.id" :value="c.id">
            {{ c.name }}
          </option>
        </select>

        <Button
          type="submit"
          class="h-10 w-full"
          :disabled="processing || (mode === 'add-member' && !selected)"
        >
          {{ processing ? "…" : submitLabel }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>
