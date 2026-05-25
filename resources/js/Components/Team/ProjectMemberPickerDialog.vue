<script setup>
import { ref, watch } from "vue";
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
  projectSlug: { type: String, required: true },
  candidates: { type: Array, default: () => [] },
  memberRoles: { type: Object, default: () => ({}) },
});

const emits = defineEmits(["update:open"]);

const selectedUser = ref("");
const selectedRole = ref("member");
const processing = ref(false);

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    selectedUser.value = "";
    selectedRole.value = "member";
    processing.value = false;
  },
);

function close() {
  emits("update:open", false);
}

function submit() {
  if (!selectedUser.value) return;
  processing.value = true;
  router.post(
    route("projects.members.store", props.projectSlug),
    {
      user_id: Number(selectedUser.value),
      role: selectedRole.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["teamMembers", "teamCandidates", "members", "stats"],
      onSuccess: close,
      onFinish: () => (processing.value = false),
    },
  );
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-sm">
      <DialogHeader>
        <DialogTitle>Ajouter un membre au projet</DialogTitle>
      </DialogHeader>

      <form class="flex flex-col gap-3" @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-muted-foreground">Utilisateur</label>
          <select
            v-model="selectedUser"
            class="h-10 w-full rounded-md border border-input bg-background px-2.5 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="" disabled>Choisir un utilisateur</option>
            <option v-for="c in candidates" :key="c.id" :value="c.id">
              {{ c.name }} ({{ c.email }})
            </option>
          </select>
          <p v-if="!candidates.length" class="text-xs text-muted-foreground">
            Tous les utilisateurs sont déjà membres du projet.
          </p>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-muted-foreground">Rôle</label>
          <select
            v-model="selectedRole"
            class="h-10 w-full rounded-md border border-input bg-background px-2.5 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring"
          >
            <option
              v-for="(label, key) in memberRoles"
              :key="key"
              :value="key"
              :disabled="key === 'owner'"
            >
              {{ label }}
            </option>
          </select>
        </div>

        <Button
          type="submit"
          class="h-10 w-full"
          :disabled="processing || !selectedUser || !candidates.length"
        >
          {{ processing ? "…" : "Ajouter" }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>
