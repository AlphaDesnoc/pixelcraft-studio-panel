<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { Crown, Shield, ShieldAlert, Trash2, UserPlus, Users } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import ProjectMemberPickerDialog from "@/Components/Team/ProjectMemberPickerDialog.vue";
import { writeKeyFor } from "@/lib/projectPermissions.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  teamMembers: { type: Array, default: () => [] },
  teamCandidates: { type: Array, default: () => [] },
  canManageTeam: { type: Boolean, default: false },
  canManageRanks: { type: Boolean, default: false },
  memberRoles: { type: Object, default: () => ({}) },
});

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));

/** Granular overrides; empty object → all caps allowed (backward compatible). */
const MEMBER_PERM_KEYS = Object.freeze([
  { key: "kanban", label: "Kanban" },
  { key: "calendar", label: "Calendrier" },
  { key: "gantt", label: "Gantt" },
  { key: "notes", label: "Notes" },
  { key: "spreadsheet", label: "Tableur" },
  { key: "files", label: "Fichiers" },
  { key: "chat", label: "Chat" },
  { key: "bugs", label: "Bugs" },
  { key: "team", label: "Équipe" },
]);

function memberPermState(member) {
  const base = Object.fromEntries(
    MEMBER_PERM_KEYS.flatMap(({ key }) => [
      [key, true],
      [writeKeyFor(key), true],
    ]),
  );
  const p = member.permissions;
  if (p && typeof p === "object" && Object.keys(p).length > 0) {
    Object.assign(base, p);
    for (const { key } of MEMBER_PERM_KEYS) {
      if (base[key] === false) {
        base[writeKeyFor(key)] = false;
      }
    }
  }
  return base;
}

function updatePermission(member, key, checked) {
  if (!props.canManageTeam || member.is_owner) return;
  const state = memberPermState(member);
  state[key] = checked;
  if (key.endsWith("_write") && checked) {
    state[key.replace(/_write$/, "")] = true;
  }
  if (!key.endsWith("_write") && !checked) {
    state[writeKeyFor(key)] = false;
  }
  router.put(
    route("projects.members.permissions", [props.projectSlug, member.id]),
    { permissions: state },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["teamMembers", "members"],
    },
  );
}

function updateWritePermission(member, feature, checked) {
  updatePermission(member, writeKeyFor(feature), checked);
}

const pickerOpen = ref(false);

const roleVariant = {
  owner: "default",
  manager: "secondary",
  member: "outline",
};

function roleLabel(role) {
  return props.memberRoles[role] ?? role;
}

function initials(name) {
  return name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

function formatDate(iso) {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function updateRole(member, role) {
  if (!props.canManageTeam || member.is_owner) return;
  router.put(
    route("projects.members.update", [props.projectSlug, member.id]),
    { role },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["teamMembers", "members"],
    },
  );
}

function removeMember(member) {
  if (!props.canManageTeam || member.is_owner) return;
  if (!confirm(`Retirer ${member.name} du projet ?`)) return;
  router.delete(route("projects.members.destroy", [props.projectSlug, member.id]), {
    preserveScroll: true,
    preserveState: true,
    only: ["teamMembers", "teamCandidates", "members", "stats", "ranks"],
  });
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-base font-semibold text-foreground">Équipe du projet</h2>
        <p class="mt-0.5 text-sm text-muted-foreground">
          {{ teamMembers.length }} membre{{ teamMembers.length > 1 ? "s" : "" }} · accès au projet
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Link
          v-if="canManageRanks"
          :href="route('projects.ranks.index', projectSlug)"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted/50"
        >
          <Shield class="h-4 w-4" />
          Gérer les ranks
        </Link>
        <Button
          v-if="canManageTeam"
          type="button"
          class="h-9 gap-1.5"
          @click="pickerOpen = true"
        >
          <UserPlus class="h-4 w-4" />
          Ajouter un membre
        </Button>
      </div>
    </header>

    <div
      v-if="!teamMembers.length"
      class="flex min-h-[200px] flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-6 py-10 text-center"
    >
      <Users class="mb-2 h-8 w-8 text-muted-foreground/60" />
      <p class="text-sm font-medium text-foreground">Aucun membre</p>
      <p class="mt-1 text-xs text-muted-foreground">
        Ajoutez des membres pour leur donner accès au projet.
      </p>
    </div>

    <ul v-else class="flex flex-col gap-2">
      <li
        v-for="member in teamMembers"
        :key="member.id"
        class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card px-4 py-3"
      >
        <Avatar class="h-9 w-9 shrink-0 text-xs">
          {{ initials(member.name) }}
        </Avatar>

        <div class="min-w-0 flex-1 space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <span class="truncate text-sm font-medium text-foreground">
              {{ member.name }}
            </span>
            <ShieldAlert
              v-if="canManageTeam && !member.is_owner"
              class="h-3.5 w-3.5 text-amber-500"
              aria-hidden="true"
            />
            <Badge :variant="roleVariant[member.role] ?? 'outline'" class="gap-1">
              <Crown v-if="member.is_owner" class="h-3 w-3" />
              {{ roleLabel(member.role) }}
            </Badge>
          </div>
          <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
          <div
            v-if="canManageTeam && !member.is_owner"
            class="w-full rounded-md border border-border/60 bg-muted/10 px-3 py-2"
          >
            <p class="mb-2 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
              <ShieldAlert class="h-3 w-3" />
              Permissions granulaires
            </p>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
              <div
                v-for="row in MEMBER_PERM_KEYS"
                :key="row.key"
                class="inline-flex items-center gap-3 text-[11px] text-foreground"
              >
                <span class="min-w-[72px] font-medium">{{ row.label }}</span>
                <label class="inline-flex cursor-pointer items-center gap-1">
                  <input
                    type="checkbox"
                    class="h-3.5 w-3.5 rounded border-input text-primary"
                    :checked="memberPermState(member)[row.key]"
                    @change="updatePermission(member, row.key, $event.target.checked)"
                  />
                  Voir
                </label>
                <label class="inline-flex cursor-pointer items-center gap-1">
                  <input
                    type="checkbox"
                    class="h-3.5 w-3.5 rounded border-input text-primary"
                    :checked="memberPermState(member)[writeKeyFor(row.key)]"
                    :disabled="!memberPermState(member)[row.key]"
                    @change="updateWritePermission(member, row.key, $event.target.checked)"
                  />
                  Modifier
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="text-xs text-muted-foreground">
          Depuis {{ formatDate(member.joined_at) }}
        </div>

        <div v-if="canManageTeam && !member.is_owner" class="flex items-center gap-2">
          <select
            :value="member.role"
            class="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground outline-none focus:ring-2 focus:ring-ring"
            @change="updateRole(member, $event.target.value)"
          >
            <option
              v-for="(label, key) in memberRoles"
              :key="key"
              :value="key"
              :disabled="key === 'owner' && !isAdmin"
            >
              {{ label }}
            </option>
          </select>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-rose-400 transition-colors hover:bg-rose-500/10 hover:text-rose-300"
            title="Retirer du projet"
            @click="removeMember(member)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </li>
    </ul>

    <p v-if="!canManageTeam" class="text-xs text-muted-foreground">
      Seuls les gestionnaires et propriétaires peuvent modifier l'équipe.
    </p>

    <ProjectMemberPickerDialog
      v-if="canManageTeam"
      v-model:open="pickerOpen"
      :project-slug="projectSlug"
      :candidates="teamCandidates"
      :member-roles="memberRoles"
    />
  </div>
</template>
