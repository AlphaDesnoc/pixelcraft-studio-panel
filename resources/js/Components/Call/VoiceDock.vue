<script setup>
import { computed } from "vue";
import {
  Hand,
  Headphones,
  HeadphoneOff,
  Maximize2,
  Mic,
  MicOff,
  PhoneOff,
  Video,
  VideoOff,
  Volume1,
  Volume2,
  VolumeX,
} from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import {
  cameraEnabled,
  canPublishLocal,
  currentRoom,
  deafened,
  getParticipantVolume,
  inRoom,
  isStage,
  leaveRoom,
  localMuted,
  masterVolume,
  meetingOpen,
  openMeeting,
  raiseHand,
  roomParticipants,
  setMasterVolume,
  setParticipantVolume,
  toggleCamera,
  toggleDeafen,
  toggleMute,
} from "@/composables/useVoiceRoom.js";

const myHandRaised = computed(
  () => Boolean(roomParticipants.value.find((p) => p.isLocal)?.handRaised),
);

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
}
</script>

<template>
  <div
    v-if="inRoom && !meetingOpen"
    class="fixed bottom-4 left-4 z-[120] w-[280px] overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
  >
    <div class="flex items-center gap-2 border-b border-border bg-emerald-500/10 px-3 py-2">
      <Volume2 class="h-4 w-4 text-emerald-400" />
      <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold text-foreground">
          {{ isStage ? "Conférence" : "Salon vocal" }} · {{ currentRoom?.label }}
        </p>
        <p class="text-[10px] text-emerald-400">
          {{ roomParticipants.length }} connecté{{ roomParticipants.length > 1 ? "s" : "" }}
          <span v-if="!canPublishLocal" class="text-muted-foreground">· auditeur</span>
        </p>
      </div>
    </div>

    <ul class="max-h-56 overflow-y-auto p-2">
      <li
        v-for="p in roomParticipants"
        :key="p.identity"
        class="group rounded-md px-2 py-1.5 hover:bg-muted/40"
      >
        <div class="flex items-center gap-2">
          <Avatar
            :src="p.avatar_url ?? ''"
            :fallback="initials(p.name)"
            size="xs"
            :class="p.speaking ? 'ring-2 ring-emerald-400' : ''"
          />
          <span class="min-w-0 flex-1 truncate text-xs text-foreground">
            {{ p.name }}
            <span v-if="p.isLocal" class="text-muted-foreground">(vous)</span>
          </span>
          <Hand v-if="p.handRaised" class="h-3.5 w-3.5 text-amber-400" />
          <MicOff
            v-if="p.isLocal && localMuted"
            class="h-3.5 w-3.5 text-rose-400"
          />
        </div>

        <!-- Volume individuel (local à cet utilisateur) -->
        <div
          v-if="!p.isLocal"
          class="mt-1 hidden items-center gap-1.5 pl-7 group-hover:flex"
        >
          <Volume1 class="h-3 w-3 shrink-0 text-muted-foreground" />
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
          <span class="w-7 shrink-0 text-right text-[9px] text-muted-foreground">
            {{ Math.round(getParticipantVolume(p.identity) * 100) }}
          </span>
        </div>
      </li>
    </ul>

    <!-- Volume global (local à vous) -->
    <div class="flex items-center gap-2 border-t border-border px-3 py-2">
      <component
        :is="masterVolume === 0 ? VolumeX : Volume2"
        class="h-4 w-4 shrink-0 text-muted-foreground"
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
      <span class="w-8 shrink-0 text-right text-[10px] text-muted-foreground">
        {{ Math.round(masterVolume * 100) }}%
      </span>
    </div>

    <div class="flex items-center justify-center gap-2 border-t border-border px-3 py-2.5">
      <template v-if="canPublishLocal">
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full border border-border transition-colors"
          :class="localMuted ? 'bg-rose-500/20 text-rose-400' : 'bg-muted text-foreground hover:bg-muted/70'"
          :title="localMuted ? 'Réactiver le micro' : 'Couper le micro'"
          @click="toggleMute"
        >
          <component :is="localMuted ? MicOff : Mic" class="h-4 w-4" />
        </button>
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full border border-border transition-colors"
          :class="cameraEnabled ? 'bg-primary/20 text-primary' : 'bg-muted text-foreground hover:bg-muted/70'"
          :title="cameraEnabled ? 'Couper la caméra' : 'Activer la caméra'"
          @click="toggleCamera"
        >
          <component :is="cameraEnabled ? Video : VideoOff" class="h-4 w-4" />
        </button>
      </template>
      <button
        v-else
        type="button"
        class="flex h-9 w-9 items-center justify-center rounded-full border border-border transition-colors"
        :class="myHandRaised ? 'bg-amber-500/25 text-amber-400' : 'bg-muted text-foreground hover:bg-muted/70'"
        :title="myHandRaised ? 'Baisser la main' : 'Demander à parler'"
        @click="raiseHand(!myHandRaised)"
      >
        <Hand class="h-4 w-4" />
      </button>
      <button
        type="button"
        class="flex h-9 w-9 items-center justify-center rounded-full border border-border transition-colors"
        :class="deafened ? 'bg-rose-500/20 text-rose-400' : 'bg-muted text-foreground hover:bg-muted/70'"
        :title="deafened ? 'Réactiver le son' : 'Se rendre sourd'"
        @click="toggleDeafen"
      >
        <component :is="deafened ? HeadphoneOff : Headphones" class="h-4 w-4" />
      </button>
      <button
        type="button"
        class="flex h-9 w-9 items-center justify-center rounded-full border border-border bg-muted text-foreground transition-colors hover:bg-muted/70"
        title="Ouvrir la réunion (vidéo)"
        @click="openMeeting"
      >
        <Maximize2 class="h-4 w-4" />
      </button>
      <button
        type="button"
        class="flex h-9 w-9 items-center justify-center rounded-full bg-rose-500 text-white transition-colors hover:bg-rose-600"
        title="Quitter le salon"
        @click="leaveRoom"
      >
        <PhoneOff class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
