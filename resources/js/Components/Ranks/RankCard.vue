<script setup>
import { computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
  ArrowRight,
  Bug,
  Crown,
  ListTodo,
  StickyNote,
  Trash2,
  UserPlus,
  Users,
  X,
} from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import { Switch } from "@/Components/ui/switch";
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

function canRemoveMember(member) {
  if (!canManageMembers.value) return false;
  // Un responsable non-admin ne peut pas se retirer lui-même du rank.
  if (
    !props.canEdit &&
    member.is_responsible &&
    member.id === currentUserId.value
  ) {
    return false;
  }
  return true;
}

const emits = defineEmits(["add-member", "rename"]);

const responsibles = computed(() => props.rank.responsibles ?? []);

function toggleResponsible(userId) {
  if (!props.canEdit) return;
  router.post(
    route("projects.ranks.responsible", [props.projectSlug, props.rank.id]),
    { user_id: userId },
    { preserveScroll: true, preserveState: true, only: ["ranks", "members"] },
  );
}

const color = computed(() => props.rank.color || "#6366f1");
const memberCount = computed(
  () => props.rank.counts?.members ?? props.rank.members?.length ?? 0,
);
const taskCount = computed(() => props.rank.counts?.tasks ?? 0);
const noteCount = computed(() => props.rank.counts?.notes ?? 0);

const stats = computed(() => [
  { key: "members", label: "Membres", value: memberCount.value, icon: Users },
  { key: "tasks", label: "Tâches", value: taskCount.value, icon: ListTodo },
  { key: "notes", label: "Notes", value: noteCount.value, icon: StickyNote },
]);

function tint(hex, alpha) {
  const h = (hex || "#6366f1").replace("#", "");
  const r = parseInt(h.substring(0, 2), 16);
  const g = parseInt(h.substring(2, 4), 16);
  const b = parseInt(h.substring(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

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
  <article
    class="group/card relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-all hover:border-border/80 hover:shadow-md"
  >
    <!-- Bandeau d'accent à la couleur du rank -->
    <span class="h-1 w-full shrink-0" :style="{ backgroundColor: color }" />

    <div class="flex flex-1 flex-col gap-4 p-4">
      <!-- En-tête -->
      <header class="flex items-start gap-3">
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-base font-bold"
          :style="{ backgroundColor: tint(color, 0.16), color }"
        >
          {{ initials(rank.name) }}
        </span>
        <div class="min-w-0 flex-1">
          <h3 class="truncate text-sm font-semibold leading-tight text-foreground">
            {{ rank.name }}
          </h3>
          <p
            v-if="rank.description"
            class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
          >
            {{ rank.description }}
          </p>
          <p v-else class="mt-0.5 text-xs text-muted-foreground/60">
            /{{ rank.slug }}
          </p>
        </div>
        <button
          v-if="canEdit"
          type="button"
          class="-mr-1 -mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-muted-foreground/60 opacity-0 transition-all hover:bg-rose-500/10 hover:text-rose-400 focus-visible:opacity-100 group-hover/card:opacity-100"
          title="Supprimer le rank"
          @click="destroy"
        >
          <Trash2 class="h-4 w-4" />
        </button>
      </header>

      <!-- Responsable(s) -->
      <div class="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5">
        <p class="mb-1.5 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-amber-500">
          <Crown class="h-3 w-3" />
          {{ responsibles.length > 1 ? "Responsables" : "Responsable" }}
        </p>
        <ul v-if="responsibles.length" class="flex flex-wrap gap-1.5">
          <li
            v-for="resp in responsibles"
            :key="resp.id"
            class="inline-flex items-center gap-1.5 rounded-full bg-background/60 py-0.5 pl-0.5 pr-2 text-[11px] text-foreground"
          >
            <Avatar
              :src="resp.avatar_url ?? ''"
              :fallback="initials(resp.name)"
              size="xs"
              class="!h-5 !w-5 !text-[8px]"
            />
            <span class="max-w-[7rem] truncate">{{ resp.name }}</span>
          </li>
        </ul>
        <p v-else class="text-xs italic text-muted-foreground">
          Aucun responsable désigné<span v-if="canEdit"> · cliquez sur la couronne d'un membre</span>
        </p>
      </div>

      <!-- Statistiques -->
      <div class="grid grid-cols-3 gap-2">
        <div
          v-for="stat in stats"
          :key="stat.key"
          class="flex flex-col items-center gap-1 rounded-xl border border-border/50 bg-background/40 py-2.5"
        >
          <component :is="stat.icon" class="h-3.5 w-3.5 text-muted-foreground" />
          <span class="text-base font-semibold leading-none tabular-nums text-foreground">
            {{ stat.value }}
          </span>
          <span class="text-[10px] text-muted-foreground">{{ stat.label }}</span>
        </div>
      </div>

      <!-- Membres -->
      <div v-if="rank.members && rank.members.length" class="flex flex-col gap-1.5">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          Équipe
        </p>
        <ul class="flex flex-wrap gap-1.5">
          <li
            v-for="member in rank.members"
            :key="member.id"
            class="inline-flex items-center gap-1.5 rounded-full border border-border bg-background/50 py-0.5 pl-0.5 pr-1 text-[11px] text-foreground"
          >
            <Avatar
              :src="member.avatar_url ?? ''"
              :fallback="initials(member.name)"
              size="xs"
              class="!h-5 !w-5 !text-[8px]"
            />
            <span class="max-w-[7rem] truncate">{{ member.name }}</span>
            <button
              v-if="canEdit"
              type="button"
              class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full transition-colors hover:bg-amber-500/15"
              :title="member.is_responsible ? 'Retirer des responsables' : 'Désigner responsable'"
              @click="toggleResponsible(member.id)"
            >
              <Crown
                class="h-3 w-3"
                :class="member.is_responsible ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/50'"
              />
            </button>
            <Crown
              v-else-if="member.is_responsible"
              class="h-3 w-3 shrink-0 fill-amber-400 text-amber-400"
            />
            <button
              v-if="canRemoveMember(member)"
              type="button"
              class="ml-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-border bg-muted/60 text-muted-foreground transition-colors hover:border-rose-500/40 hover:bg-rose-500/15 hover:text-rose-400"
              title="Retirer du rank"
              @click="removeMember(member.id)"
            >
              <X class="h-3 w-3" />
            </button>
          </li>
        </ul>
      </div>

      <!-- Gestion des bugs -->
      <label
        class="flex items-center gap-2.5 rounded-xl border border-border/60 bg-muted/20 px-3 py-2"
        :class="canEdit ? 'cursor-pointer' : 'cursor-default'"
      >
        <span
          class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
          :class="rank.manages_bugs ? 'bg-amber-500/15 text-amber-500' : 'bg-muted text-muted-foreground'"
        >
          <Bug class="h-3.5 w-3.5" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-foreground">Gestion des bugs</p>
          <p class="text-[10px] text-muted-foreground">
            Ce rank traite les rapports de bugs
          </p>
        </div>
        <Switch
          :model-value="rank.manages_bugs"
          :disabled="!canEdit"
          @update:model-value="toggleBugs"
        />
      </label>

      <!-- Actions -->
      <div class="mt-auto flex flex-col gap-2 pt-1">
        <Link
          :href="`${route('projects.show', projectSlug)}?space=${rank.slug}`"
          class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-primary text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
        >
          Ouvrir l'espace
          <ArrowRight class="h-4 w-4" />
        </Link>
        <button
          v-if="canManageMembers"
          type="button"
          class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-input bg-background/40 text-sm font-medium text-foreground transition-colors hover:bg-muted/60"
          @click="emits('add-member', rank)"
        >
          <UserPlus class="h-3.5 w-3.5" />
          Ajouter un membre
        </button>
      </div>
    </div>
  </article>
</template>
