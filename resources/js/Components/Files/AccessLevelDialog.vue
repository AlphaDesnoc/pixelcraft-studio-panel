<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Lock } from "lucide-vue-next";
import InputError from "@/Components/InputError.vue";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  node: { type: Object, default: null },
  levels: { type: Array, default: () => [] },
});

const emits = defineEmits(["update:open"]);

const level = ref("0");
const error = ref("");
const processing = ref(false);

watch(
  () => [props.open, props.node?.id],
  ([open]) => {
    if (!open) return;
    level.value = String(props.node?.access_level ?? 0);
    error.value = "";
    processing.value = false;
  },
);

function close() {
  emits("update:open", false);
}

function submit() {
  processing.value = true;
  router.put(
    route("projects.files.access-level", [props.projectSlug, props.node.id]),
    { level: Number(level.value) },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["fileNodes"],
      onSuccess: close,
      onError: (errs) => {
        error.value = errs?.level ?? "Erreur";
      },
      onFinish: () => (processing.value = false),
    },
  );
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-sm">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Lock class="h-4 w-4" />
          Niveau d'accès
        </DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
        <p class="text-sm text-muted-foreground">
          Seuls les membres dont l'accréditation est supérieure ou égale au niveau
          choisi pourront voir
          <span class="font-medium text-foreground">{{ node?.name }}</span>
          <template v-if="node?.type === 'folder'"> et son contenu</template>.
        </p>

        <Select v-model="level">
          <option v-for="lvl in levels" :key="lvl.value" :value="String(lvl.value)">
            {{ lvl.value }} — {{ lvl.name }}
          </option>
        </Select>
        <InputError :message="error" />

        <div class="flex items-center justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" @click="close">Annuler</Button>
          <Button type="submit" :disabled="processing">
            {{ processing ? "…" : "Appliquer" }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
