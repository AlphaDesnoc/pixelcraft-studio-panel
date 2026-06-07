<script setup>
import { computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { Bug, Crown, Star, Trash2, UserPlus } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { confirmDialog } from "@/composables/useConfirm.js";

const page = usePage();

const props = defineProps({
  projectSlug: { type: String, required: true },
  rank: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
});

const canManageMembers = computed(
  () => props.canEdit || props.rank.can_manage_members === true,
);

const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

function canRemoveMember(memberId) {
  if (!canManageMembers.value) return false;
  if (
    !props.canEdit &&
    props.rank.responsible?.id === memberId &&
    memberId === currentUserId.value
  ) {
    return false;
  }
  return true;
}

const emits = defineEmits(["add-member", "set-responsible", "rename"]);

const memberCount = computed(() => props.rank.counts?.members ?? props.rank.members?.length ?? 0);
const taskCount = computed(() => props.rank.counts?.tasks ?? 0);
const noteCount = computed(() => props.rank.counts?.notes ?? 0);

async function destroy() {
  if (!props.canEdit) return;
  if (
    !(await confirmDialog({
      title: "Supprimer le rank",
      message: `Le rank "${props.rank.name}" sera supprimé.`,
    }))
  )
    return;
  router.delete(
    route("projects.ranks.destroy", [props.projectSlug, props.rank.id]),
    { preserveScroll: true, preserveState: true, only: ["ranks", "members"] },
  );
}

function toggleBugs() {
  if (!props.canEdit) return;
  router.post(
    route("projects.ranks.bugs", [props.projectSlug, props.rank.id]),
    {},
    { preserveScroll: true, preserveState: true, only: ["ranks", "members"] },
  );
}

async function removeMember(userId) {
  if (!canManageMembers.value) return;
  if (
    !(await confirmDialog({
      title: "Retirer du rank",
      message: "Ce membre sera retiré du rank.",
      confirmLabel: "Retirer",
    }))
  )
    return;
  router.delete(
    route("projects.ranks.members.remove", [
      props.projectSlug,
      props.rank.id,
      userId,
    ]),
    { preserveScroll: true, preserveState: true, only: ["ranks", "members"] },
  );
}
</script>

<template>
  <article class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4">
    <header class="flex items-start justify-between gap-2">
      <div class="flex items-center gap-2">
        <span
          class="inline-block h-2.5 w-2.5 rounded-full"
          :style="{ backgroundColor: rank.color }"
        />
        <h3 class="text-sm font-semibold text-foreground">{{ rank.name }}</h3>
      </div>
      <button
        v-if="canEdit"
        type="button"
        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-rose-400 transition-colors hover:bg-rose-500/10 hover:text-rose-300"
        title="Supprimer"
        @click="destroy"
      >
        <Trash2 class="h-3.5 w-3.5" />
      </button>
    </header>

    <div class="flex flex-col gap-1.5">
      <div class="flex items-center gap-1.5 text-xs">
        <Crown class="h-3.5 w-3.5 text-amber-400" />
        <span v-if="rank.responsible" class="text-foreground">
          Responsable : <span class="font-medium">{{ rank.responsible.name }}</span>
        </span>
        <span v-else class="italic text-muted-foreground">Aucun responsable</span>
      </div>

      <div class="flex items-center gap-3 text-[11px] text-muted-foreground">
        <span>{{ memberCount }} membres</span>
        <span>{{ taskCount }} tâches</span>
        <span>{{ noteCount }} notes</span>
      </div>

      <label class="mt-1 inline-flex w-fit cursor-pointer items-center gap-2 text-xs">
        <input
          type="checkbox"
          :checked="rank.manages_bugs"
          :disabled="!canEdit"
          class="h-3.5 w-3.5 rounded border-border bg-background accent-primary"
          @change="toggleBugs"
        />
        <Bug class="h-3.5 w-3.5 text-amber-400" />
        <span class="text-foreground">Gestion des bugs</span>
      </label>

      <ul v-if="rank.members && rank.members.length > 0" class="mt-1 flex flex-wrap gap-1.5">
        <li
          v-for="member in rank.members"
          :key="member.id"
          class="group/member inline-flex items-center gap-1 rounded-full border border-border bg-background/40 px-2 py-0.5 text-[11px] text-foreground"
        >
          <span>{{ member.name }}</span>
          <Star
            v-if="rank.responsible && rank.responsible.id === member.id"
            class="h-2.5 w-2.5 fill-amber-400 text-amber-400"
          />
          <button
            v-if="canRemoveMember(member.id)"
            type="button"
            class="ml-0.5 hidden text-muted-foreground hover:text-rose-400 group-hover/member:inline-flex"
            title="Retirer du rank"
            @click="removeMember(member.id)"
          >
            ×
          </button>
        </li>
      </ul>
    </div>

    <div class="mt-auto flex items-center gap-1.5">
      <Button as-child class="h-9 flex-1">
        <Link :href="`${route('projects.show', projectSlug)}?space=${rank.slug}`">
          Ouvrir l'espace
        </Link>
      </Button>
      <button
        v-if="canManageMembers"
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background/40 text-foreground transition-colors hover:bg-muted/60"
        title="Ajouter un membre"
        @click="emits('add-member', rank)"
      >
        <UserPlus class="h-3.5 w-3.5" />
      </button>
      <button
        v-if="canEdit"
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background/40 text-amber-400 transition-colors hover:bg-amber-500/10"
        title="Définir le responsable"
        @click="emits('set-responsible', rank)"
      >
        <Crown class="h-3.5 w-3.5" />
      </button>
    </div>
  </article>
</template>
