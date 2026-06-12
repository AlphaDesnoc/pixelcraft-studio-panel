<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
  ChevronDown,
  Crown,
  Phone,
  Shield,
  Trash2,
  UserPlus,
  Users,
  Video,
} from "lucide-vue-next";
import { startCall } from "@/composables/useCall.js";
import { Avatar } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Select } from "@/Components/ui/select";
import MemberPermissionsMatrix from "@/Components/Team/MemberPermissionsMatrix.vue";
import ProjectMemberPickerDialog from "@/Components/Team/ProjectMemberPickerDialog.vue";
import { writeKeyFor } from "@/lib/projectPermissions.js";
import { confirmDialog } from "@/composables/useConfirm.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  teamMembers: { type: Array, default: () => [] },
  teamCandidates: { type: Array, default: () => [] },
  canManageTeam: { type: Boolean, default: false },
  canManageRanks: { type: Boolean, default: false },
  memberRoles: { type: Object, default: () => ({}) },
  accessLevels: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

function callMember(member, withVideo) {
  startCall({ id: member.id, name: member.name, avatar_url: member.avatar_url }, { withVideo });
}

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

function permissionsSummary(member) {
  const state = memberPermState(member);
  const readCount = MEMBER_PERM_KEYS.filter(({ key }) => state[key]).length;
  const writeCount = MEMBER_PERM_KEYS.filter(
    ({ key }) => state[writeKeyFor(key)],
  ).length;
  const total = MEMBER_PERM_KEYS.length;
  if (readCount === total && writeCount === total) {
    return "Accès complet";
  }
  if (readCount === 0) {
    return "Aucun module";
  }
  return `${readCount}/${total} modules · ${writeCount} en écriture`;
}

function savePermissions(member, permissions) {
  if (!props.canManageTeam || member.is_owner) return;
  router.put(
    route("projects.members.permissions", [props.projectSlug, member.id]),
    { permissions },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["teamMembers", "members"],
    },
  );
}

const pickerOpen = ref(false);
const expandedPermissions = ref(new Set());

function togglePermissions(memberId) {
  const next = new Set(expandedPermissions.value);
  if (next.has(memberId)) {
    next.delete(memberId);
  } else {
    next.add(memberId);
  }
  expandedPermissions.value = next;
}

function isPermissionsExpanded(memberId) {
  return expandedPermissions.value.has(memberId);
}

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

function updateClearance(member, level) {
  if (!props.canManageTeam) return;
  router.put(
    route("projects.members.clearance", [props.projectSlug, member.id]),
    { access_level: Number(level) },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["teamMembers", "members"],
    },
  );
}

async function removeMember(member) {
  if (!props.canManageTeam || member.is_owner) return;
  if (
    !(await confirmDialog({
      title: "Retirer du projet",
      message: `${member.name} sera retiré de ce projet.`,
      confirmLabel: "Retirer",
    }))
  )
    return;
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

    <ul v-else class="flex flex-col gap-3">
      <li
        v-for="member in teamMembers"
        :key="member.id"
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
      >
        <div class="flex flex-wrap items-center gap-4 px-4 py-4 sm:flex-nowrap">
          <Avatar
            class="shrink-0"
            size="md"
            :src="member.avatar_url ?? ''"
            :fallback="initials(member.name)"
          />

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="truncate text-sm font-semibold text-foreground">
                {{ member.name }}
              </span>
              <Badge :variant="roleVariant[member.role] ?? 'outline'" class="gap-1">
                <Crown v-if="member.is_owner" class="h-3 w-3" />
                {{ roleLabel(member.role) }}
              </Badge>
            </div>
            <p class="mt-0.5 truncate text-xs text-muted-foreground">
              {{ member.email }}
            </p>
          </div>

          <div
            v-if="member.id !== currentUserId"
            class="flex shrink-0 items-center gap-1"
          >
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-emerald-500/10 hover:text-emerald-400"
              title="Appel audio"
              @click="callMember(member, false)"
            >
              <Phone class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
              title="Appel vidéo"
              @click="callMember(member, true)"
            >
              <Video class="h-4 w-4" />
            </button>
          </div>

          <div class="flex shrink-0 flex-col items-end gap-0.5 text-right">
            <span class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              Membre depuis
            </span>
            <span class="text-xs tabular-nums text-foreground">
              {{ formatDate(member.joined_at) }}
            </span>
          </div>

          <div
            v-if="canManageTeam && !member.is_owner"
            class="flex shrink-0 items-center gap-2 border-l border-border/50 pl-4"
          >
            <Select
              :model-value="member.role"
              class="h-9 w-[8.5rem] text-xs"
              @update:model-value="updateRole(member, $event)"
            >
              <option
                v-for="(label, key) in memberRoles"
                :key="key"
                :value="key"
                :disabled="key === 'owner' && !isAdmin"
              >
                {{ label }}
              </option>
            </Select>
            <Select
              v-if="accessLevels.length"
              :model-value="String(member.access_level ?? 0)"
              class="h-9 w-[10rem] text-xs"
              title="Niveau d'accréditation"
              @update:model-value="updateClearance(member, $event)"
            >
              <option v-for="lvl in accessLevels" :key="lvl.value" :value="String(lvl.value)">
                {{ lvl.value }} — {{ lvl.name }}
              </option>
            </Select>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
              title="Retirer du projet"
              @click="removeMember(member)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div
          v-if="canManageTeam && !member.is_owner"
          class="border-t border-border/50 bg-muted/5"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/20"
            @click="togglePermissions(member.id)"
          >
            <div>
              <p class="text-xs font-medium text-foreground">Accès aux modules</p>
              <p class="mt-0.5 text-[11px] text-muted-foreground">
                {{ permissionsSummary(member) }}
              </p>
            </div>
            <ChevronDown
              class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
              :class="isPermissionsExpanded(member.id) ? 'rotate-180' : ''"
            />
          </button>

          <div
            v-show="isPermissionsExpanded(member.id)"
            class="border-t border-border/40 px-4 pb-4 pt-3"
          >
            <MemberPermissionsMatrix
              :modules="MEMBER_PERM_KEYS"
              :permissions="memberPermState(member)"
              @update:permissions="savePermissions(member, $event)"
            />
          </div>
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
