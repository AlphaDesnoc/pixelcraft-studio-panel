<script setup>
import { computed, onMounted, onUnmounted, provide, ref, watch } from "vue";
import { canWriteFeature } from "@/lib/projectPermissions.js";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import {
  BarChart3,
  Bell,
  CheckCircle2,
  Clock,
  StickyNote,
  TriangleAlert,
  Users,
  Shield,
  Calendar,
} from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import VoiceChannelsPanel from "@/Components/Call/VoiceChannelsPanel.vue";
import { Avatar } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import SpaceSwitcher from "@/Components/Projects/SpaceSwitcher.vue";
import ProjectTabs from "@/Components/Projects/ProjectTabs.vue";
import StatChip from "@/Components/Projects/StatChip.vue";
import DonutChart from "@/Components/Projects/DonutChart.vue";
import BarChart from "@/Components/Projects/BarChart.vue";
import KanbanBoard from "@/Components/Kanban/KanbanBoard.vue";
import CalendarView from "@/Components/Calendar/Calendar.vue";
import Gantt from "@/Components/Gantt/Gantt.vue";
import NotesView from "@/Components/Notes/NotesView.vue";
import SpreadsheetView from "@/Components/Spreadsheet/SpreadsheetView.vue";
import FilesView from "@/Components/Files/FilesView.vue";
import BugsView from "@/Components/Bugs/BugsView.vue";
import ChatView from "@/Components/Chat/ChatView.vue";
import AnnouncementsView from "@/Components/Announcements/AnnouncementsView.vue";
import TeamView from "@/Components/Team/TeamView.vue";
import ProjectHistoryPanel from "@/Components/Projects/ProjectHistoryPanel.vue";
import ProjectPlayers from "@/Components/Projects/ProjectPlayers.vue";
import TaskActivityByRank from "@/Components/Projects/TaskActivityByRank.vue";
import { spaceOnlyProps } from "@/composables/useProjectSpace.js";

const props = defineProps({
  project: { type: Object, required: true },
  activeSpace: { type: String, default: "global" },
  activeRankId: { type: Number, default: null },
  spaceLabel: { type: String, default: "Global" },
  stats: { type: Object, required: true },
  progress: { type: Number, default: 0 },
  byStatus: { type: Array, default: () => [] },
  byPriority: { type: Array, default: () => [] },
  spaces: { type: Array, default: () => [] },
  ranks: { type: Array, default: () => [] },
  canManageRanks: { type: Boolean, default: false },
  lists: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  notes: { type: Array, default: () => [] },
  sheets: { type: Array, default: () => [] },
  voiceChannels: { type: Array, default: () => [] },
  voiceManageRanks: { type: Array, default: () => [] },
  fileNodes: { type: Array, default: () => [] },
  trashedFileNodes: { type: Array, default: () => [] },
  storageUsed: { type: Number, default: 0 },
  storageQuota: { type: Number, default: 0 },
  accessLevels: { type: Array, default: () => [] },
  userClearance: { type: Number, default: 0 },
  canSetAccessLevels: { type: Boolean, default: false },
  members: { type: Array, default: () => [] },
  teamMembers: { type: Array, default: () => [] },
  teamCandidates: { type: Array, default: () => [] },
  canManageTeam: { type: Boolean, default: false },
  memberRoles: { type: Object, default: () => ({}) },
  priorities: { type: Object, default: () => ({}) },
  statusKinds: { type: Object, default: () => ({}) },
  canReportBugs: { type: Boolean, default: false },
  canManageBugs: { type: Boolean, default: false },
  bugs: { type: Array, default: () => [] },
  bugRanks: { type: Array, default: () => [] },
  bugPriorities: { type: Object, default: () => ({}) },
  bugStatuses: { type: Object, default: () => ({}) },
  chatMessages: { type: Array, default: () => [] },
  chatMembers: { type: Array, default: () => [] },
  chatRankMentions: { type: Array, default: () => [] },
  announcements: { type: Array, default: () => [] },
  canPostAnnouncements: { type: Boolean, default: false },
  activityLogs: { type: Array, default: () => [] },
  taskActivityByRank: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  myPermissions: { type: Object, default: () => ({}) },
  taskTemplates: { type: Array, default: () => [] },
  pinnedChatMessages: { type: Array, default: () => [] },
  canManagePlayers: { type: Boolean, default: false },
  minecraftServer: { type: Object, default: null },
  minecraftPlayers: { type: Array, default: () => [] },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const kanbanBoardRef = ref(null);
const gNavPending = ref(false);
let gNavTimer = null;

const activeSpace = ref(props.activeSpace);
const swimlaneMode = ref(false);

watch(
  () => props.activeSpace,
  (value) => {
    activeSpace.value = value;
  },
);

watch(activeSpace, (space) => {
  if (activeTab.value === "bugs" && !bugsAccess.value.show) {
    activeTab.value = "overview";
  }
  if (activeTab.value === "chat" && space === "full") {
    activeTab.value = "overview";
  }
  if (activeTab.value === "team" && space !== "global") {
    activeTab.value = "overview";
  }
  if (activeTab.value === "announcements" && space !== "global") {
    activeTab.value = "overview";
  }
  if (space === props.activeSpace) return;
  router.get(
    route("projects.show", props.project.slug),
    { space },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: spaceOnlyProps(),
    },
  );
});

const activeRankId = computed(() => props.activeRankId);

const bugsAccess = computed(() => {
  const space = activeSpace.value;
  if (space === "global") {
    return {
      show: true,
      canReport: true,
      canManage: Boolean(props.canManageBugs),
    };
  }
  if (space === "full") {
    return { show: false, canReport: false, canManage: false };
  }
  const rank = props.ranks.find((r) => r.key === space);
  const canManage = Boolean(rank?.manages_bugs) && Boolean(props.canManageBugs);
  return { show: canManage, canReport: false, canManage };
});

const baseTabs = [
  { key: "overview", label: "Vue d'ensemble" },
  { key: "announcements", label: "Annonces" },
  { key: "kanban", label: "Kanban" },
  { key: "calendar", label: "Calendrier" },
  { key: "gantt", label: "Gantt" },
  { key: "notes", label: "Notes" },
  { key: "spreadsheet", label: "Tableur" },
  { key: "files", label: "Fichiers" },
  { key: "chat", label: "Chat" },
  { key: "team", label: "Équipe" },
  { key: "history", label: "Historique" },
];

const tabPermissionKey = {
  overview: null,
  announcements: null,
  kanban: "kanban",
  calendar: "calendar",
  gantt: "gantt",
  notes: "notes",
  spreadsheet: "spreadsheet",
  files: "files",
  chat: "chat",
  team: "team",
  history: null,
  bugs: "bugs",
};

function tabAllowed(tabKey) {
  const permKey = tabPermissionKey[tabKey];
  if (!permKey) return true;
  const perms = props.myPermissions;
  if (!perms || Object.keys(perms).length === 0) return true;
  return perms[permKey] !== false;
}

const tabs = computed(() => {
  let result = [...baseTabs];
  if (activeSpace.value === "full") {
    result = result.filter((t) => t.key !== "chat");
  }
  if (activeSpace.value !== "global") {
    result = result.filter((t) => t.key !== "team" && t.key !== "announcements");
  }
  result = result.filter((t) => tabAllowed(t.key));
  if (bugsAccess.value.show && tabAllowed("bugs")) {
    const chatIndex = result.findIndex((t) => t.key === "chat");
    result.splice(chatIndex, 0, {
      key: "bugs",
      label: "Bugs",
    });
  }
  // Onglet « Joueurs » : réservé aux admins, uniquement sur l'espace global.
  if (props.canManagePlayers && activeSpace.value === "global") {
    result.push({ key: "players", label: "Joueurs" });
  }
  return result;
});

watch(
  () => bugsAccess.value.show,
  (show) => {
    if (activeTab.value === "bugs" && !show) {
      activeTab.value = "overview";
    }
  },
);

watch(
  tabs,
  (next) => {
    if (!next.some((t) => t.key === activeTab.value)) {
      activeTab.value = next[0]?.key ?? "overview";
    }
  },
);

const canWriteKanban = computed(() => canWriteFeature(props.myPermissions, "kanban"));
const canWriteGantt = computed(() => canWriteFeature(props.myPermissions, "gantt"));

const activeTab = ref("overview");

function readInitialTabFromUrl() {
  if (typeof window === "undefined") return;
  const params = new URLSearchParams(window.location.search);
  const tab = params.get("tab");
  if (tab && tabs.value.some((t) => t.key === tab)) {
    activeTab.value = tab;
  }
}

provide(
  "floatingProjectChat",
  computed(() =>
    activeSpace.value === "full"
      ? null
      : {
          projectSlug: props.project.slug,
          projectId: props.project.id,
          spaceKey: activeSpace.value,
          spaceLabel: props.spaceLabel,
          members: props.chatMembers,
          rankMentions: props.chatRankMentions,
        },
  ),
);

function isTypingTarget(target) {
  if (!target) return false;
  const tag = target.tagName?.toLowerCase();
  return tag === "input" || tag === "textarea" || tag === "select" || target.isContentEditable;
}

function onProjectKeydown(event) {
  if (isTypingTarget(event.target)) return;

  if (event.key === "n" || event.key === "N") {
    if (activeTab.value === "kanban" && canWriteKanban.value) {
      event.preventDefault();
      kanbanBoardRef.value?.openNewTask?.();
    }
    return;
  }

  if (event.key === "/") {
    if (activeTab.value === "kanban") {
      event.preventDefault();
      kanbanBoardRef.value?.focusFilters?.();
    }
    return;
  }

  if (gNavPending.value) {
    clearTimeout(gNavTimer);
    gNavPending.value = false;
    const key = event.key.toLowerCase();
    if (key === "k") activeTab.value = "kanban";
    else if (key === "c") activeTab.value = "calendar";
    else if (key === "b" && bugsAccess.value.show) activeTab.value = "bugs";
    event.preventDefault();
    return;
  }

  if (event.key === "g" || event.key === "G") {
    gNavPending.value = true;
    gNavTimer = setTimeout(() => {
      gNavPending.value = false;
    }, 1200);
  }
}

onMounted(() => {
  readInitialTabFromUrl();
  window.addEventListener("keydown", onProjectKeydown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", onProjectKeydown);
  clearTimeout(gNavTimer);
  if (activeTab.value === "chat") {
    markChatSpaceRead(activeSpace.value);
  }
});

const chatLastReadKey = computed(
  () => `chat-last-read:${props.project.slug}:${currentUserId.value}`,
);

function loadChatLastRead() {
  try {
    return JSON.parse(localStorage.getItem(chatLastReadKey.value) ?? "{}");
  } catch {
    return {};
  }
}

// Réactif : la modif déclenche le recalcul de chatUnreadSummary (corrige le
// badge « Non lus » qui ne se vidait jamais, faute d'écriture du dernier accès).
const chatLastRead = ref(loadChatLastRead());

function markChatSpaceRead(space) {
  if (!space || space === "full") {
    return;
  }
  chatLastRead.value = { ...chatLastRead.value, [space]: new Date().toISOString() };
  try {
    localStorage.setItem(chatLastReadKey.value, JSON.stringify(chatLastRead.value));
  } catch {
    // localStorage indisponible (mode privé, quota) : on ignore.
  }
}

const chatUnreadSummary = computed(() => {
  const lastRead = chatLastRead.value;
  const counts = {};
  for (const message of props.chatMessages) {
    const space = message.space_key ?? props.activeSpace;
    const ts = new Date(message.created_at).getTime();
    const readTs = lastRead[space] ? new Date(lastRead[space]).getTime() : 0;
    if (message.user?.id !== currentUserId.value && ts > readTs) {
      counts[space] = (counts[space] ?? 0) + 1;
    }
  }

  return Object.entries(counts).map(([space, count]) => ({
    space,
    label: space === "global" ? "Global" : props.ranks.find((r) => r.key === space)?.label ?? space,
    count,
  }));
});

// Consulter le chat d'un espace le marque comme lu (à l'ouverture et à chaque
// changement d'espace tant qu'on est sur l'onglet chat).
watch(
  () => [activeTab.value, activeSpace.value],
  ([tab, space]) => {
    if (tab === "chat") {
      markChatSpaceRead(space);
    }
  },
  { immediate: true },
);

const initials = computed(() =>
  props.project.name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);

const statusLabel = computed(
  () =>
    ({
      active: "Actif",
      completed: "Terminé",
      archived: "Archivé",
    })[props.project.status] ?? props.project.status,
);

const statusVariant = computed(
  () =>
    ({
      active: "success",
      completed: "default",
      archived: "secondary",
    })[props.project.status] ?? "secondary",
);

const statusColors = {
  todo: "#a78bfa",
  in_progress: "#38bdf8",
  done: "#34d399",
};

const totalStatusCount = computed(() =>
  props.byStatus.reduce((acc, s) => acc + s.count, 0),
);

const kanbanBugLinkTasks = computed(() =>
  props.lists.flatMap((list) =>
    (list.tasks ?? []).map((task) => ({
      id: task.id,
      title: task.title,
      list_name: list.name,
    })),
  ),
);
</script>

<template>
  <Head :title="project.name" />

  <AuthenticatedLayout>
    <div class="flex flex-col gap-5">
      <header class="flex flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <Avatar
              :src="project.image_url ?? ''"
              :fallback="initials"
              size="lg"
              rounded="lg"
            />
            <div>
              <div class="flex items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                  {{ project.name }}
                </h1>
                <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
              </div>
              <p
                v-if="project.description"
                class="mt-1 max-w-xl text-sm text-muted-foreground"
              >
                {{ project.description }}
              </p>
            </div>
          </div>

          <Button
            v-if="canManageRanks"
            as-child
            variant="outline"
            size="sm"
            class="gap-1.5"
          >
            <Link :href="route('projects.ranks.index', project.slug)">
              <Shield class="h-3.5 w-3.5" />
              Gérer les ranks
            </Link>
          </Button>
        </div>

        <SpaceSwitcher
          v-model="activeSpace"
          :spaces="spaces"
          :ranks="ranks"
        />
      </header>

      <ProjectTabs :tabs="tabs" :active="activeTab" @update:active="activeTab = $event" />

      <VoiceChannelsPanel
        :project-slug="project.slug"
        :project-id="project.id"
        :voice-channels="voiceChannels"
        :ranks="ranks"
        :can-manage="canManageTeam"
        :manage-ranks="voiceManageRanks"
        :active-space="activeSpace"
        :active-rank-id="activeRankId"
        :space-label="spaceLabel"
      />

      <aside
        v-if="pinnedChatMessages.length || chatUnreadSummary.length"
        class="flex flex-wrap gap-2 rounded-lg border border-border/60 bg-card/30 p-3 text-xs"
      >
        <div v-if="chatUnreadSummary.length" class="flex flex-wrap items-center gap-2">
          <span class="font-medium text-muted-foreground">Non lus :</span>
          <button
            v-for="row in chatUnreadSummary"
            :key="row.space"
            type="button"
            class="rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-foreground hover:bg-primary/15"
            @click="activeSpace = row.space; activeTab = 'chat'"
          >
            {{ row.count }} dans {{ row.label }}
          </button>
        </div>
        <div v-if="pinnedChatMessages.length" class="flex min-w-[200px] flex-1 flex-col gap-1">
          <span class="font-medium text-muted-foreground">Messages épinglés</span>
          <button
            v-for="msg in pinnedChatMessages.slice(0, 3)"
            :key="msg.id"
            type="button"
            class="truncate text-left text-foreground hover:text-primary"
            @click="activeSpace = msg.space_key; activeTab = 'chat'"
          >
            {{ msg.user?.name }} · {{ msg.body }}
          </button>
        </div>
      </aside>

      <section v-if="activeTab === 'overview'" class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
          <StatChip
            label="Tâches totales"
            :value="stats.tasks_total"
            :icon="BarChart3"
            tint="info"
          />
          <StatChip
            label="Terminées"
            :value="stats.tasks_done"
            :icon="CheckCircle2"
            tint="success"
          />
          <StatChip
            label="En cours"
            :value="stats.tasks_in_progress"
            :icon="Clock"
            tint="info"
          />
          <StatChip
            label="En retard"
            :value="stats.tasks_overdue"
            :icon="TriangleAlert"
            tint="danger"
          />
          <StatChip
            label="Membres"
            :value="stats.members"
            :icon="Users"
            tint="primary"
          />
          <StatChip
            label="Notes"
            :value="stats.notes"
            :icon="StickyNote"
            tint="default"
          />
          <StatChip
            label="Événements"
            :value="stats.events"
            :icon="Calendar"
            tint="default"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <Card>
            <CardHeader>
              <CardTitle>Progression globale</CardTitle>
              <CardDescription>Avancement moyen du projet</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col items-center justify-center gap-3 py-6">
              <DonutChart :value="progress" :size="180" />
              <p class="text-xs text-muted-foreground">
                Taux de complétion : {{ progress }}%
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Par statut</CardTitle>
              <CardDescription>Répartition des tâches</CardDescription>
            </CardHeader>
            <CardContent class="flex h-[260px] flex-col justify-center">
              <div
                v-if="totalStatusCount === 0"
                class="flex h-full flex-col items-center justify-center text-center"
              >
                <Bell class="h-6 w-6 text-muted-foreground/60" />
                <p class="mt-2 text-sm text-muted-foreground">
                  Aucune tâche pour le moment
                </p>
              </div>
              <ul v-else class="flex flex-col gap-3">
                <li
                  v-for="status in byStatus"
                  :key="status.key"
                  class="flex flex-col gap-1"
                >
                  <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1.5 font-medium">
                      <span
                        class="inline-block h-2 w-2 rounded-full"
                        :style="{ backgroundColor: statusColors[status.key] }"
                      />
                      {{ status.label }}
                    </span>
                    <span class="text-muted-foreground">
                      {{ status.count }}
                    </span>
                  </div>
                  <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div
                      class="h-full rounded-full transition-all"
                      :style="{
                        width: `${(status.count / Math.max(1, totalStatusCount)) * 100}%`,
                        backgroundColor: statusColors[status.key],
                      }"
                    />
                  </div>
                </li>
              </ul>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Par priorité</CardTitle>
              <CardDescription>Distribution des priorités</CardDescription>
            </CardHeader>
            <CardContent>
              <BarChart :data="byPriority" :height="220" />
            </CardContent>
          </Card>
        </div>

        <TaskActivityByRank
          :groups="taskActivityByRank"
          :export-url="route('projects.export.activity', project.slug)"
        />
      </section>

      <section v-else-if="activeTab === 'announcements'">
        <AnnouncementsView
          :project-slug="project.slug"
          :project-id="project.id"
          :announcements="announcements"
          :can-post="canPostAnnouncements"
        />
      </section>

      <section v-else-if="activeTab === 'kanban'">
        <KanbanBoard
          ref="kanbanBoardRef"
          :project-slug="project.slug"
          :project-id="project.id"
          :lists="lists"
          :members="members"
          :priorities="priorities"
          :status-kinds="statusKinds"
          :rank-id="activeRankId"
          :global-kanban="activeSpace === 'full'"
          :tags="tags"
          :task-templates="taskTemplates"
          :swimlane-mode="swimlaneMode"
          :my-permissions="myPermissions"
          :current-user-id="currentUserId"
          @update:swimlane-mode="swimlaneMode = $event"
        />
      </section>

      <section v-else-if="activeTab === 'calendar'">
        <CalendarView
          :project-slug="project.slug"
          :events="events"
          :rank-id="activeRankId"
        />
      </section>

      <section v-else-if="activeTab === 'gantt'">
        <Gantt
          :project-slug="project.slug"
          :project-id="project.id"
          :lists="lists"
          :priorities="priorities"
          :can-write="canWriteGantt"
        />
      </section>

      <section v-else-if="activeTab === 'notes'">
        <NotesView
          :project-slug="project.slug"
          :notes="notes"
          :rank-id="activeRankId"
        />
      </section>

      <section v-else-if="activeTab === 'spreadsheet'">
        <SpreadsheetView
          :project-slug="project.slug"
          :sheets="sheets"
          :rank-id="activeRankId"
        />
      </section>

      <section v-else-if="activeTab === 'files'">
        <FilesView
          :project-slug="project.slug"
          :nodes="fileNodes"
          :trashed-nodes="trashedFileNodes"
          :storage-used="storageUsed"
          :storage-quota="storageQuota"
          :rank-id="activeRankId"
          :access-levels="accessLevels"
          :user-clearance="userClearance"
          :can-set-access-levels="canSetAccessLevels"
          :can-delete="canManageTeam"
        />
      </section>

      <section v-else-if="activeTab === 'chat'">
        <ChatView
          :project-slug="project.slug"
          :project-id="project.id"
          :space-key="activeSpace"
          :space-label="spaceLabel"
          :active="activeTab === 'chat'"
          :initial-chat-members="chatMembers"
          :chat-rank-mentions="chatRankMentions"
          :can-manage-chat="canManageTeam"
        />
      </section>

      <section v-else-if="activeTab === 'bugs'">
        <BugsView
          :project-slug="project.slug"
          :bugs="bugs"
          :can-report="bugsAccess.canReport"
          :can-manage="bugsAccess.canManage"
          :priorities="bugPriorities"
          :statuses="bugStatuses"
          :members="members"
          :bug-ranks="bugRanks"
          :task-options="kanbanBugLinkTasks"
        />
      </section>

      <section v-else-if="activeTab === 'team'">
        <TeamView
          :project-slug="project.slug"
          :team-members="teamMembers"
          :team-candidates="teamCandidates"
          :can-manage-team="canManageTeam"
          :can-manage-ranks="canManageRanks"
          :member-roles="memberRoles"
          :access-levels="accessLevels"
        />
      </section>

      <section v-else-if="activeTab === 'history'">
        <ProjectHistoryPanel
          :project-slug="project.slug"
          :members="members"
          :initial-logs="activityLogs"
        />
      </section>

      <section v-else-if="activeTab === 'players'">
        <ProjectPlayers
          :project-slug="project.slug"
          :server="minecraftServer"
          :players="minecraftPlayers"
        />
      </section>

      <section
        v-else
        class="flex min-h-[280px] flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-8 py-12 text-center"
      >
        <p class="text-sm font-medium">
          Section "{{ tabs.find((t) => t.key === activeTab)?.label ?? activeTab }}" à venir
        </p>
        <p class="mt-1 text-xs text-muted-foreground">
          Cette vue sera implémentée plus tard.
        </p>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
