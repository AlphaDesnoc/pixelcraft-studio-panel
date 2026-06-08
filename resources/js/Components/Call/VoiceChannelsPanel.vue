<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import {
  Check,
  ChevronDown,
  Loader2,
  Lock,
  Pencil,
  Plus,
  Trash2,
  Users,
  Video,
  Volume2,
  X,
} from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import {
  connecting,
  currentRoom,
  joinRoom,
  leaveRoom,
} from "@/composables/useVoiceRoom.js";
import { confirmDialog } from "@/composables/useConfirm.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectId: { type: Number, required: true },
  voiceChannels: { type: Array, default: () => [] },
  ranks: { type: Array, default: () => [] },
  // « Admin » du projet (admin/proprio/gestionnaire) : gère tous les salons.
  canManage: { type: Boolean, default: false },
  // Rangs dont l'utilisateur est responsable : [{ id, name, color }].
  manageRanks: { type: Array, default: () => [] },
  // Espace actif : les salons affichés et créés sont ceux de cet espace.
  activeSpace: { type: String, default: "global" },
  activeRankId: { type: Number, default: null },
  spaceLabel: { type: String, default: "" },
});

const channels = ref([]);

// ---- Droits de gestion ----
// Espace global ou vue d'ensemble admin → salons « tout le projet ».
const isGlobalSpace = computed(
  () => props.activeSpace === "global" || props.activeSpace === "full",
);
const manageableRankIds = computed(
  () => new Set(props.manageRanks.map((r) => r.id)),
);
// On crée un salon dans l'espace courant : le global est réservé aux admins,
// l'espace d'un rang est ouvert à l'admin et au responsable de ce rang.
const canCreate = computed(() => {
  if (isGlobalSpace.value) return props.canManage;
  return props.canManage || manageableRankIds.value.has(props.activeRankId);
});

function canManageChannel(channel) {
  return props.canManage || manageableRankIds.value.has(channel.rank_id);
}

function syncFromProps() {
  channels.value = (props.voiceChannels ?? []).map((c) => ({
    ...c,
    participants: [...(c.participants ?? [])],
  }));
}
watch(() => props.voiceChannels, syncFromProps, { immediate: true, deep: true });

// ---- Regroupement par rang ----
const collapsed = ref({});

function toggleGroup(key) {
  collapsed.value = { ...collapsed.value, [key]: !collapsed.value[key] };
}

const groups = computed(() => {
  const result = [];

  const globalChannels = channels.value.filter((c) => !c.rank_id);
  if (globalChannels.length) {
    result.push({
      key: "global",
      label: "Tout le projet",
      color: null,
      channels: globalChannels,
    });
  }

  const seen = new Set();
  for (const r of props.ranks) {
    const rc = channels.value.filter((c) => c.rank_id === r.id);
    if (rc.length) {
      result.push({
        key: `rank-${r.id}`,
        label: r.label ?? r.name,
        color: r.color ?? null,
        restricted: true,
        channels: rc,
      });
      seen.add(r.id);
    }
  }

  // Rangs non présents dans la liste accessible : on s'appuie sur channel.rank.
  const leftover = {};
  for (const c of channels.value) {
    if (c.rank_id && !seen.has(c.rank_id)) {
      const k = c.rank_id;
      leftover[k] ??= {
        key: `rank-${k}`,
        label: c.rank?.name ?? "Rang",
        color: c.rank?.color ?? null,
        restricted: true,
        channels: [],
      };
      leftover[k].channels.push(c);
    }
  }
  Object.values(leftover).forEach((g) => result.push(g));

  return result;
});

function isActive(channel) {
  return currentRoom.value?.channelId === channel.id;
}

async function join(channel) {
  if (isActive(channel) || connecting.value) return;
  if (currentRoom.value) await leaveRoom();
  await joinRoom(props.projectSlug, channel.id, channel.name, {
    withVideo: false,
    openMeetingView: Boolean(channel.with_video),
    projectId: props.projectId,
  });
}

async function leave() {
  await leaveRoom();
}

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

// ---- Création / suppression ----
const creating = ref(false);
const newName = ref("");
const newType = ref("voice"); // "voice" | "meeting"

function openCreate() {
  creating.value = true;
  newName.value = "";
  newType.value = "voice";
}

function resetCreate() {
  creating.value = false;
  newName.value = "";
  newType.value = "voice";
}

function submitCreate() {
  const name = newName.value.trim();
  if (!name) return;
  router.post(
    route("projects.voice-channels.store", props.projectSlug),
    {
      // Le salon est rattaché à l'espace courant.
      name,
      rank_id: isGlobalSpace.value ? null : props.activeRankId,
      with_video: newType.value === "meeting",
    },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["voiceChannels"],
      onSuccess: resetCreate,
    },
  );
}

// ---- Renommage ----
const editingId = ref(null);
const editName = ref("");
const editInput = ref(null);

async function startRename(channel) {
  editingId.value = channel.id;
  editName.value = channel.name;
  await nextTick();
  const el = Array.isArray(editInput.value) ? editInput.value[0] : editInput.value;
  el?.focus();
  el?.select();
}

function cancelRename() {
  editingId.value = null;
  editName.value = "";
}

function submitRename(channel) {
  const name = editName.value.trim();
  if (!name || name === channel.name) {
    cancelRename();
    return;
  }
  router.patch(
    route("projects.voice-channels.update", [props.projectSlug, channel.id]),
    { name },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["voiceChannels"],
      onSuccess: cancelRename,
    },
  );
}

async function removeChannel(channel) {
  if (
    !(await confirmDialog({
      title: "Supprimer le salon",
      message: `Le salon vocal « ${channel.name} » sera définitivement supprimé.`,
    }))
  )
    return;
  router.delete(
    route("projects.voice-channels.destroy", [props.projectSlug, channel.id]),
    { preserveScroll: true, preserveState: true, only: ["voiceChannels"] },
  );
}

// ---- Présence temps réel ----
let lobby = null;

function applyMembership(evt) {
  if (!evt) return;
  channels.value.forEach((c) => {
    c.participants = c.participants.filter((p) => p.id !== evt.user?.id);
  });
  if (evt.action === "join" && evt.user?.id) {
    const target = channels.value.find((c) => c.id === evt.channel_id);
    if (target && !target.participants.some((p) => p.id === evt.user.id)) {
      target.participants.push(evt.user);
    }
  }
}

onMounted(() => {
  if (!window.Echo) return;
  lobby = window.Echo.private(`voice-lobby.${props.projectId}`);
  lobby.listen(".VoiceMembershipChanged", applyMembership);
});

onBeforeUnmount(() => {
  if (lobby) window.Echo?.leave(`voice-lobby.${props.projectId}`);
});
</script>

<template>
  <section
    class="flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm"
  >
    <header
      class="flex items-center justify-between border-b border-border bg-muted/30 px-3 py-2.5"
    >
      <h3 class="flex items-center gap-2 text-sm font-semibold text-foreground">
        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-500/15">
          <Volume2 class="h-3.5 w-3.5 text-emerald-400" />
        </span>
        Salons vocaux
        <span
          v-if="spaceLabel"
          class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
        >
          {{ spaceLabel }}
        </span>
      </h3>
      <button
        v-if="canCreate"
        type="button"
        class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-background px-2 text-xs font-medium text-muted-foreground transition-colors hover:border-emerald-500/40 hover:text-foreground"
        @click="creating ? resetCreate() : openCreate()"
      >
        <component :is="creating ? X : Plus" class="h-3.5 w-3.5" />
        {{ creating ? "Annuler" : "Nouveau" }}
      </button>
    </header>

    <!-- Formulaire de création -->
    <Transition name="create-slide">
      <div
        v-if="creating"
        class="flex flex-col gap-2.5 border-b border-border bg-muted/15 p-3"
      >
        <input
          v-model="newName"
          type="text"
          placeholder="Nom du salon (ex. Réunion équipe)"
          class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:border-emerald-500/50 focus:ring-2 focus:ring-emerald-500/20"
          @keydown.enter.prevent="submitCreate"
        />

        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="flex items-center gap-2 rounded-md border px-2.5 py-2 text-left text-xs transition-colors"
            :class="newType === 'voice'
              ? 'border-emerald-500/50 bg-emerald-500/10 text-foreground'
              : 'border-border bg-background text-muted-foreground hover:bg-muted/40'"
            @click="newType = 'voice'"
          >
            <Volume2 class="h-4 w-4 shrink-0 text-emerald-400" />
            <span>
              <span class="block font-medium">Vocal</span>
              <span class="block text-[10px] opacity-70">Audio seul</span>
            </span>
          </button>
          <button
            type="button"
            class="flex items-center gap-2 rounded-md border px-2.5 py-2 text-left text-xs transition-colors"
            :class="newType === 'meeting'
              ? 'border-sky-500/50 bg-sky-500/10 text-foreground'
              : 'border-border bg-background text-muted-foreground hover:bg-muted/40'"
            @click="newType = 'meeting'"
          >
            <Video class="h-4 w-4 shrink-0 text-sky-400" />
            <span>
              <span class="block font-medium">Réunion</span>
              <span class="block text-[10px] opacity-70">Visio + écran</span>
            </span>
          </button>
        </div>

        <div class="flex items-center justify-between gap-2">
          <p class="flex min-w-0 items-center gap-1 text-[11px] text-muted-foreground">
            <Lock v-if="!isGlobalSpace" class="h-3 w-3 shrink-0" />
            <span class="truncate">
              Accès :
              <span class="font-medium text-foreground">
                {{ isGlobalSpace ? "tout le projet" : spaceLabel }}
              </span>
            </span>
          </p>
          <button
            type="button"
            class="inline-flex h-9 shrink-0 items-center gap-1 rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
            :disabled="!newName.trim()"
            @click="submitCreate"
          >
            Créer
          </button>
        </div>
      </div>
    </Transition>

    <div class="flex flex-col gap-3 p-2">
      <p
        v-if="!channels.length"
        class="px-1 py-3 text-center text-xs text-muted-foreground"
      >
        Aucun salon vocal dans cet espace.
        <span v-if="canCreate">Créez-en un pour commencer.</span>
      </p>

      <!-- Groupes par rang -->
      <div v-for="group in groups" :key="group.key" class="flex flex-col gap-0.5">
        <button
          type="button"
          class="group/head flex items-center gap-1.5 rounded px-1.5 py-1 text-left transition-colors hover:bg-muted/40"
          @click="toggleGroup(group.key)"
        >
          <ChevronDown
            class="h-3 w-3 shrink-0 text-muted-foreground transition-transform"
            :class="collapsed[group.key] ? '-rotate-90' : ''"
          />
          <span
            v-if="group.color"
            class="h-2 w-2 shrink-0 rounded-full"
            :style="{ backgroundColor: group.color }"
          />
          <span
            class="flex-1 truncate text-[11px] font-semibold uppercase tracking-wide"
            :style="group.color ? { color: group.color } : {}"
            :class="group.color ? '' : 'text-muted-foreground'"
          >
            {{ group.label }}
          </span>
          <Lock
            v-if="group.restricted"
            class="h-3 w-3 shrink-0 text-muted-foreground/60"
          />
          <span class="text-[10px] text-muted-foreground">{{ group.channels.length }}</span>
        </button>

        <ul v-show="!collapsed[group.key]" class="flex flex-col gap-0.5 pl-1">
          <li
            v-for="channel in group.channels"
            :key="channel.id"
            class="rounded-lg border transition-colors"
            :class="isActive(channel)
              ? 'border-emerald-500/40 bg-emerald-500/[0.07]'
              : 'border-transparent hover:bg-muted/40'"
          >
            <div class="flex items-center gap-2 px-2 py-1.5">
              <span
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md"
                :class="channel.with_video ? 'bg-sky-500/15' : 'bg-muted'"
              >
                <component
                  :is="channel.with_video ? Video : Volume2"
                  class="h-3.5 w-3.5"
                  :class="channel.with_video ? 'text-sky-400' : 'text-muted-foreground'"
                />
              </span>

              <div class="min-w-0 flex-1">
                <div v-if="editingId === channel.id" class="flex items-center gap-1">
                  <input
                    ref="editInput"
                    v-model="editName"
                    type="text"
                    maxlength="80"
                    class="h-7 w-full rounded border border-input bg-background px-2 text-sm outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/30"
                    @keydown.enter.prevent="submitRename(channel)"
                    @keydown.esc.prevent="cancelRename"
                  />
                  <button
                    type="button"
                    class="shrink-0 rounded p-1 text-emerald-400 hover:bg-muted"
                    title="Valider"
                    @click="submitRename(channel)"
                  >
                    <Check class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted"
                    title="Annuler"
                    @click="cancelRename"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                </div>
                <template v-else>
                  <p class="truncate text-sm font-medium text-foreground">
                    {{ channel.name }}
                  </p>
                  <p
                    v-if="channel.participants.length"
                    class="flex items-center gap-1 text-[10px] text-emerald-400"
                  >
                    <Users class="h-2.5 w-2.5" />
                    {{ channel.participants.length }} connecté{{ channel.participants.length > 1 ? "s" : "" }}
                  </p>
                </template>
              </div>

              <template v-if="editingId !== channel.id">
                <button
                  v-if="isActive(channel)"
                  type="button"
                  class="inline-flex h-7 items-center gap-1 rounded-md bg-rose-500/15 px-2.5 text-[11px] font-semibold text-rose-400 transition-colors hover:bg-rose-500/25"
                  @click="leave"
                >
                  Quitter
                </button>
                <button
                  v-else
                  type="button"
                  class="inline-flex h-7 items-center gap-1 rounded-md bg-emerald-500/15 px-2.5 text-[11px] font-semibold text-emerald-400 transition-colors hover:bg-emerald-500/25 disabled:opacity-50"
                  :disabled="connecting"
                  @click="join(channel)"
                >
                  <Loader2 v-if="connecting" class="h-3 w-3 animate-spin" />
                  Rejoindre
                </button>

                <button
                  v-if="canManageChannel(channel)"
                  type="button"
                  class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                  title="Renommer le salon"
                  @click="startRename(channel)"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </button>
                <button
                  v-if="canManageChannel(channel)"
                  type="button"
                  class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-rose-400"
                  title="Supprimer le salon"
                  @click="removeChannel(channel)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </template>
            </div>

            <!-- Participants connectés -->
            <div
              v-if="channel.participants.length"
              class="flex flex-wrap items-center gap-1 px-2 pb-2 pl-10"
            >
              <span
                v-for="p in channel.participants"
                :key="p.id"
                class="inline-flex items-center gap-1 rounded-full bg-muted/70 py-0.5 pl-0.5 pr-2"
                :title="p.name"
              >
                <Avatar
                  :src="p.avatar_url ?? ''"
                  :fallback="initials(p.name)"
                  size="xs"
                  class="!h-4 !w-4 !text-[8px]"
                />
                <span class="text-[10px] text-foreground">{{ p.name }}</span>
              </span>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<style scoped>
.create-slide-enter-active,
.create-slide-leave-active {
  transition: opacity 0.18s ease;
}
.create-slide-enter-from,
.create-slide-leave-to {
  opacity: 0;
}
</style>
