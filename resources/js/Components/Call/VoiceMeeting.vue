<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import {
  Hand,
  Headphones,
  HeadphoneOff,
  Maximize2,
  Mic,
  MicOff,
  Minimize2,
  MonitorUp,
  Phone,
  Pin,
  PinOff,
  Shield,
  Users,
  UserMinus,
  UserPlus,
  Video,
  VideoOff,
  Volume1,
  Volume2,
  VolumeX,
} from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import ParticipantTile from "./ParticipantTile.vue";
import {
  amModerator,
  cameraEnabled,
  canPublishLocal,
  closeMeeting,
  currentRoom,
  deafened,
  getParticipantVolume,
  inRoom,
  isStage,
  joinStage,
  leaveRoom,
  leaveStage,
  localMuted,
  masterVolume,
  meetingOpen,
  raiseHand,
  roomParticipants,
  screenSharing,
  setMasterVolume,
  setParticipantRole,
  setParticipantVolume,
  toggleCamera,
  toggleDeafen,
  toggleMute,
  toggleScreenShare,
} from "@/composables/useVoiceRoom.js";

const show = computed(() => inRoom.value && meetingOpen.value);
const showParticipants = ref(false);

// ---- Répartition intervenants / auditoire ----
const speakers = computed(() =>
  roomParticipants.value.filter((p) => p.role === "speaker"),
);
const audience = computed(() =>
  // Mains levées en premier.
  [...roomParticipants.value.filter((p) => p.role === "audience")].sort(
    (a, b) => Number(b.handRaised) - Number(a.handRaised),
  ),
);
const raisedCount = computed(
  () => audience.value.filter((p) => p.handRaised).length,
);

// Sur la scène : tous les participants (vocal) ou les intervenants (réunion).
const stageParticipants = computed(() =>
  isStage.value ? speakers.value : roomParticipants.value,
);

const me = computed(() => roomParticipants.value.find((p) => p.isLocal));
const myHandRaised = computed(() => Boolean(me.value?.handRaised));

// ---- Sources affichables (caméra + écrans partagés) ----
const sources = computed(() => {
  const arr = [];
  stageParticipants.value.forEach((p) => {
    arr.push({ key: p.identity, participant: p, screen: false });
    if (p.screenTrack) {
      arr.push({ key: `screen-${p.identity}`, participant: p, screen: true });
    }
  });
  return arr;
});

// ---- Spotlight ----
const pinnedKey = ref(null);
const stageSource = computed(() => {
  if (pinnedKey.value) {
    const s = sources.value.find((x) => x.key === pinnedKey.value);
    if (s) return s;
  }
  return sources.value.find((x) => x.screen) ?? null;
});
const tileLabel = (s) =>
  s.screen ? `screen-${s.participant.identity}` : s.participant.identity;
const isStaged = (s) =>
  stageSource.value && tileLabel(stageSource.value) === tileLabel(s);
function toggleStage(s) {
  if (isStaged(s)) {
    pinnedKey.value = null;
    return;
  }
  pinnedKey.value = s.key;
}
watch(sources, (list) => {
  if (pinnedKey.value && !list.some((s) => s.key === pinnedKey.value)) {
    pinnedKey.value = null;
  }
});

// ---- Plein écran (agrandir le partage / spotlight) ----
const spotlightEl = ref(null);
const isFullscreen = ref(false);
function toggleFullscreen() {
  if (document.fullscreenElement) {
    document.exitFullscreen?.();
  } else {
    spotlightEl.value?.requestFullscreen?.();
  }
}
function onFullscreenChange() {
  isFullscreen.value = document.fullscreenElement === spotlightEl.value;
}
onMounted(() => document.addEventListener("fullscreenchange", onFullscreenChange));
onBeforeUnmount(() =>
  document.removeEventListener("fullscreenchange", onFullscreenChange),
);

const gridCols = computed(() => {
  const n = stageParticipants.value.length;
  if (n <= 1) return "grid-cols-1";
  if (n <= 4) return "grid-cols-2";
  if (n <= 9) return "grid-cols-3";
  if (n <= 16) return "grid-cols-4";
  return "grid-cols-5";
});

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

function promote(p) {
  setParticipantRole(p.identity, "speaker");
}
function demote(p) {
  setParticipantRole(p.identity, "audience");
}
</script>

<template>
  <Transition name="meet-fade">
    <div
      v-if="show"
      class="fixed inset-0 z-[140] flex flex-col bg-[#0b0d12] text-white"
    >
      <!-- Barre supérieure -->
      <header
        class="flex items-center justify-between border-b border-white/10 px-4 py-3"
      >
        <div class="flex items-center gap-2.5">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/15">
            <Video class="h-4 w-4 text-sky-400" />
          </span>
          <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold leading-tight">
              {{ currentRoom?.label }}
              <span
                v-if="isStage"
                class="rounded bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-medium text-sky-300"
              >
                Conférence
              </span>
            </h2>
            <p class="flex items-center gap-1 text-xs text-white/50">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
              {{ roomParticipants.length }} participant{{ roomParticipants.length > 1 ? "s" : "" }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-white/10 px-3 text-xs transition-colors"
            :class="showParticipants ? 'bg-white/10 text-white' : 'text-white/60 hover:bg-white/5 hover:text-white'"
            @click="showParticipants = !showParticipants"
          >
            <Users class="h-3.5 w-3.5" />
            {{ roomParticipants.length }}
          </button>
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-white/10 px-3 text-xs text-white/60 transition-colors hover:bg-white/5 hover:text-white"
            @click="closeMeeting"
          >
            <Minimize2 class="h-3.5 w-3.5" />
            Réduire
          </button>
        </div>
      </header>

      <!-- Corps -->
      <div class="relative flex min-h-0 flex-1">
        <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
          <!-- Étiquette section intervenants (mode conférence) -->
          <div
            v-if="isStage"
            class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-white/40"
          >
            <Mic class="h-3.5 w-3.5" />
            Intervenants — {{ speakers.length }}
          </div>

          <!-- Scène : spotlight (partage / épinglé) -->
          <template v-if="stageSource">
            <div
              ref="spotlightEl"
              class="group relative min-h-0 flex-1 bg-black"
              :class="isFullscreen ? 'rounded-none' : ''"
            >
              <ParticipantTile
                :participant="stageSource.participant"
                :screen="stageSource.screen"
                class="!aspect-auto h-full w-full"
                :class="isFullscreen ? '!rounded-none !border-0' : '!rounded-2xl'"
                @dblclick="toggleFullscreen"
              />
              <div class="absolute right-3 top-3 hidden items-center gap-2 group-hover:flex">
                <button
                  type="button"
                  class="flex h-8 items-center gap-1.5 rounded-md bg-black/60 px-3 text-xs text-white backdrop-blur transition hover:bg-black/80"
                  :title="isFullscreen ? 'Quitter le plein écran' : 'Agrandir en plein écran'"
                  @click="toggleFullscreen"
                >
                  <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
                  {{ isFullscreen ? "Réduire" : "Plein écran" }}
                </button>
                <button
                  v-if="!isFullscreen"
                  type="button"
                  class="flex h-8 items-center gap-1.5 rounded-md bg-black/60 px-3 text-xs text-white backdrop-blur transition hover:bg-black/80"
                  @click="toggleStage(stageSource)"
                >
                  <PinOff class="h-3.5 w-3.5" />
                  Détacher
                </button>
              </div>
            </div>

            <!-- Filmstrip intervenants -->
            <div class="flex shrink-0 gap-2 overflow-x-auto pb-1">
              <div
                v-for="s in sources"
                :key="s.key"
                class="group relative w-40 shrink-0"
              >
                <button
                  type="button"
                  class="block w-full overflow-hidden rounded-xl transition"
                  :class="isStaged(s) ? 'ring-2 ring-sky-400' : 'opacity-80 hover:opacity-100'"
                  @click="toggleStage(s)"
                >
                  <ParticipantTile :participant="s.participant" :screen="s.screen" />
                </button>
                <button
                  v-if="isStage && amModerator && !s.participant.isLocal && !s.screen"
                  type="button"
                  class="absolute right-1.5 top-1.5 hidden h-6 w-6 items-center justify-center rounded-md bg-black/70 text-white/80 hover:text-rose-400 group-hover:flex"
                  title="Retirer de la scène"
                  @click.stop="demote(s.participant)"
                >
                  <UserMinus class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </template>

          <!-- Scène : grille -->
          <div
            v-else
            :class="['grid h-full content-center gap-3', gridCols]"
          >
            <div
              v-for="p in stageParticipants"
              :key="p.identity"
              class="group relative"
            >
              <button
                type="button"
                class="block w-full"
                @click="toggleStage({ key: p.identity, participant: p, screen: false })"
              >
                <ParticipantTile :participant="p" />
                <span
                  class="pointer-events-none absolute right-2 top-2 hidden h-7 w-7 items-center justify-center rounded-md bg-black/60 text-white backdrop-blur group-hover:flex"
                >
                  <Pin class="h-3.5 w-3.5" />
                </span>
              </button>
              <button
                v-if="isStage && amModerator && !p.isLocal"
                type="button"
                class="absolute left-2 top-2 hidden h-7 items-center gap-1 rounded-md bg-black/70 px-2 text-[11px] text-white/80 hover:text-rose-400 group-hover:flex"
                title="Retirer de la scène"
                @click.stop="demote(p)"
              >
                <UserMinus class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>

          <!-- Auditoire (mode conférence) -->
          <div
            v-if="isStage"
            class="shrink-0 border-t border-white/10 pt-3"
          >
            <div class="mb-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-white/40">
              <Users class="h-3.5 w-3.5" />
              Auditoire — {{ audience.length }}
              <span
                v-if="raisedCount"
                class="rounded bg-amber-500/15 px-1.5 py-0.5 text-amber-300"
              >
                {{ raisedCount }} main{{ raisedCount > 1 ? "s" : "" }} levée{{ raisedCount > 1 ? "s" : "" }}
              </span>
            </div>

            <p v-if="!audience.length" class="text-xs text-white/30">
              Personne dans l'auditoire.
            </p>

            <div class="flex flex-wrap gap-2">
              <div
                v-for="p in audience"
                :key="p.identity"
                class="group/aud flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors"
                :class="p.handRaised ? 'bg-amber-500/15 ring-1 ring-amber-400/40' : 'bg-white/5'"
              >
                <Avatar
                  :src="p.avatar_url ?? ''"
                  :fallback="initials(p.name)"
                  size="xs"
                />
                <span class="text-xs text-white/80">
                  {{ p.name }}
                  <span v-if="p.isLocal" class="text-white/40">(vous)</span>
                </span>
                <Hand v-if="p.handRaised" class="h-3.5 w-3.5 text-amber-300" />
                <button
                  v-if="amModerator"
                  type="button"
                  class="flex h-6 items-center gap-1 rounded-full bg-emerald-500/20 px-2 text-[11px] font-medium text-emerald-300 transition-colors hover:bg-emerald-500/30"
                  :class="p.handRaised ? '' : 'opacity-0 group-hover/aud:opacity-100'"
                  title="Inviter à parler"
                  @click="promote(p)"
                >
                  <UserPlus class="h-3 w-3" />
                  Inviter
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Panneau participants -->
        <Transition name="panel-slide">
          <aside
            v-if="showParticipants"
            class="flex w-64 shrink-0 flex-col border-l border-white/10 bg-black/20"
          >
            <p class="px-4 pt-3 text-xs font-semibold uppercase tracking-wide text-white/50">
              Participants — {{ roomParticipants.length }}
            </p>

            <!-- Volume global (local à vous) -->
            <div class="mx-2 mt-2 flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2">
              <component
                :is="masterVolume === 0 ? VolumeX : Volume2"
                class="h-4 w-4 shrink-0 text-white/60"
              />
              <input
                type="range"
                min="0"
                max="1"
                step="0.02"
                :value="masterVolume"
                class="h-1 flex-1 cursor-pointer accent-emerald-500"
                title="Volume global (pour vous)"
                @input="setMasterVolume($event.target.value)"
              />
              <span class="w-8 shrink-0 text-right text-[10px] text-white/50">
                {{ Math.round(masterVolume * 100) }}%
              </span>
            </div>

            <ul class="mt-1 flex-1 overflow-y-auto px-2 pb-3">
              <li
                v-for="p in roomParticipants"
                :key="p.identity"
                class="group/row rounded-lg px-2 py-1.5 hover:bg-white/5"
              >
                <div class="flex items-center gap-2.5">
                  <Avatar
                    :src="p.avatar_url ?? ''"
                    :fallback="initials(p.name)"
                    size="sm"
                    :class="p.speaking ? 'ring-2 ring-emerald-400' : ''"
                  />
                  <span class="flex min-w-0 flex-1 items-center gap-1 truncate text-sm">
                    {{ p.name }}
                    <Shield v-if="p.canModerate" class="h-3 w-3 shrink-0 text-sky-400" />
                    <span v-if="p.isLocal" class="text-white/40">(vous)</span>
                  </span>
                  <Hand v-if="p.handRaised" class="h-3.5 w-3.5 text-amber-300" />
                  <MonitorUp v-if="p.screenTrack" class="h-3.5 w-3.5 text-sky-400" />
                  <component
                    :is="p.role === 'audience' ? Headphones : p.micOn ? Mic : MicOff"
                    class="h-3.5 w-3.5"
                    :class="p.role === 'audience' ? 'text-white/30' : p.micOn ? 'text-white/50' : 'text-rose-400'"
                  />
                </div>

                <!-- Volume individuel (local à vous) -->
                <div
                  v-if="!p.isLocal"
                  class="mt-1 hidden items-center gap-1.5 pl-[42px] group-hover/row:flex"
                >
                  <Volume1 class="h-3 w-3 shrink-0 text-white/40" />
                  <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.02"
                    :value="getParticipantVolume(p.identity)"
                    class="h-1 flex-1 cursor-pointer accent-emerald-500"
                    title="Volume de cette personne (pour vous)"
                    @input="setParticipantVolume(p.identity, $event.target.value)"
                  />
                  <span class="w-7 shrink-0 text-right text-[9px] text-white/40">
                    {{ Math.round(getParticipantVolume(p.identity) * 100) }}
                  </span>
                </div>
              </li>
            </ul>
          </aside>
        </Transition>
      </div>

      <!-- Barre de contrôle flottante -->
      <footer class="flex items-center justify-center px-4 py-4">
        <div
          class="flex items-center gap-2 rounded-2xl border border-white/10 bg-[#15181f]/90 px-3 py-2.5 shadow-2xl backdrop-blur"
        >
          <!-- Contrôles de publication (intervenants / vocal) -->
          <template v-if="canPublishLocal">
            <button
              type="button"
              class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors"
              :class="localMuted ? 'bg-rose-500/20 text-rose-400 hover:bg-rose-500/30' : 'bg-white/10 text-white hover:bg-white/20'"
              :title="localMuted ? 'Réactiver le micro' : 'Couper le micro'"
              @click="toggleMute"
            >
              <component :is="localMuted ? MicOff : Mic" class="h-5 w-5" />
            </button>
            <button
              type="button"
              class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors"
              :class="cameraEnabled ? 'bg-sky-500/20 text-sky-300 hover:bg-sky-500/30' : 'bg-white/10 text-white hover:bg-white/20'"
              :title="cameraEnabled ? 'Couper la caméra' : 'Activer la caméra'"
              @click="toggleCamera"
            >
              <component :is="cameraEnabled ? Video : VideoOff" class="h-5 w-5" />
            </button>
            <button
              type="button"
              class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors"
              :class="screenSharing ? 'bg-sky-500/20 text-sky-300 hover:bg-sky-500/30' : 'bg-white/10 text-white hover:bg-white/20'"
              :title="screenSharing ? 'Arrêter le partage' : 'Partager l\'écran'"
              @click="toggleScreenShare"
            >
              <MonitorUp class="h-5 w-5" />
            </button>
          </template>

          <!-- Auditeur : lever la main / monter sur scène -->
          <template v-else>
            <button
              v-if="amModerator"
              type="button"
              class="flex h-11 items-center gap-2 rounded-xl bg-emerald-500/20 px-4 text-sm font-medium text-emerald-300 transition-colors hover:bg-emerald-500/30"
              title="Monter sur scène"
              @click="joinStage"
            >
              <Mic class="h-5 w-5" />
              Monter sur scène
            </button>
            <button
              v-else
              type="button"
              class="flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-medium transition-colors"
              :class="myHandRaised ? 'bg-amber-500/25 text-amber-200 hover:bg-amber-500/35' : 'bg-white/10 text-white hover:bg-white/20'"
              :title="myHandRaised ? 'Baisser la main' : 'Demander à parler'"
              @click="raiseHand(!myHandRaised)"
            >
              <Hand class="h-5 w-5" />
              {{ myHandRaised ? "Baisser la main" : "Demander à parler" }}
            </button>
          </template>

          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors"
            :class="deafened ? 'bg-rose-500/20 text-rose-400 hover:bg-rose-500/30' : 'bg-white/10 text-white hover:bg-white/20'"
            :title="deafened ? 'Réactiver le son' : 'Se rendre sourd'"
            @click="toggleDeafen"
          >
            <component :is="deafened ? HeadphoneOff : Headphones" class="h-5 w-5" />
          </button>

          <!-- Intervenant non-modérateur : quitter la scène -->
          <button
            v-if="isStage && canPublishLocal && !amModerator"
            type="button"
            class="flex h-11 items-center gap-2 rounded-xl bg-white/10 px-4 text-sm font-medium text-white transition-colors hover:bg-white/20"
            title="Quitter la scène (redevenir auditeur)"
            @click="leaveStage"
          >
            <UserMinus class="h-5 w-5" />
            Quitter la scène
          </button>

          <span class="mx-1 h-7 w-px bg-white/10" />

          <button
            type="button"
            class="flex h-11 items-center gap-2 rounded-xl bg-rose-500 px-5 text-sm font-semibold text-white transition-colors hover:bg-rose-600"
            title="Quitter la réunion"
            @click="leaveRoom"
          >
            <Phone class="h-5 w-5 rotate-[135deg]" />
            Quitter
          </button>
        </div>
      </footer>
    </div>
  </Transition>
</template>

<style scoped>
.meet-fade-enter-active,
.meet-fade-leave-active {
  transition: opacity 0.2s ease;
}
.meet-fade-enter-from,
.meet-fade-leave-to {
  opacity: 0;
}
.panel-slide-enter-active,
.panel-slide-leave-active {
  transition: all 0.2s ease;
}
.panel-slide-enter-from,
.panel-slide-leave-to {
  width: 0;
  opacity: 0;
}
</style>
