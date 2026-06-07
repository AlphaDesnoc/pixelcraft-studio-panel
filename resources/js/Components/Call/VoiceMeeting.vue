<script setup>
import { computed } from "vue";
import {
  Headphones,
  HeadphoneOff,
  Mic,
  MicOff,
  Minimize2,
  MonitorUp,
  PhoneOff,
  Video,
  VideoOff,
} from "lucide-vue-next";
import ParticipantTile from "./ParticipantTile.vue";
import {
  cameraEnabled,
  closeMeeting,
  currentRoom,
  deafened,
  inRoom,
  leaveRoom,
  localMuted,
  meetingOpen,
  roomParticipants,
  screenSharing,
  toggleCamera,
  toggleDeafen,
  toggleMute,
  toggleScreenShare,
} from "@/composables/useVoiceRoom.js";

const show = computed(() => inRoom.value && meetingOpen.value);

const screenShares = computed(() =>
  roomParticipants.value.filter((p) => p.screenTrack),
);

// Colonnes adaptatives selon le nombre de participants.
const gridCols = computed(() => {
  const n = roomParticipants.value.length;
  if (n <= 1) return "grid-cols-1";
  if (n <= 4) return "grid-cols-2";
  if (n <= 9) return "grid-cols-3";
  if (n <= 16) return "grid-cols-4";
  return "grid-cols-5";
});
</script>

<template>
  <Transition name="meet-fade">
    <div
      v-if="show"
      class="fixed inset-0 z-[140] flex flex-col bg-background/95 backdrop-blur"
    >
      <header class="flex items-center justify-between border-b border-border px-4 py-3">
        <div>
          <h2 class="text-sm font-semibold text-foreground">
            Réunion · {{ currentRoom?.label }}
          </h2>
          <p class="text-xs text-muted-foreground">
            {{ roomParticipants.length }} participant{{ roomParticipants.length > 1 ? "s" : "" }}
          </p>
        </div>
        <button
          type="button"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-xs text-muted-foreground hover:bg-muted/60 hover:text-foreground"
          @click="closeMeeting"
        >
          <Minimize2 class="h-3.5 w-3.5" />
          Réduire
        </button>
      </header>

      <div class="flex-1 overflow-auto p-4">
        <div v-if="screenShares.length" class="mb-3 grid gap-3">
          <ParticipantTile
            v-for="p in screenShares"
            :key="'screen-' + p.identity"
            :participant="p"
            screen
            class="!aspect-auto h-[55vh]"
          />
        </div>
        <div :class="['grid gap-3', gridCols]">
          <ParticipantTile
            v-for="p in roomParticipants"
            :key="p.identity"
            :participant="p"
          />
        </div>
      </div>

      <footer class="flex items-center justify-center gap-3 border-t border-border px-4 py-4">
        <button
          type="button"
          class="flex h-11 w-11 items-center justify-center rounded-full border border-border transition-colors"
          :class="localMuted ? 'bg-rose-500/20 text-rose-400' : 'bg-muted text-foreground hover:bg-muted/70'"
          :title="localMuted ? 'Réactiver le micro' : 'Couper le micro'"
          @click="toggleMute"
        >
          <component :is="localMuted ? MicOff : Mic" class="h-5 w-5" />
        </button>
        <button
          type="button"
          class="flex h-11 w-11 items-center justify-center rounded-full border border-border transition-colors"
          :class="cameraEnabled ? 'bg-primary/20 text-primary' : 'bg-muted text-foreground hover:bg-muted/70'"
          :title="cameraEnabled ? 'Couper la caméra' : 'Activer la caméra'"
          @click="toggleCamera"
        >
          <component :is="cameraEnabled ? Video : VideoOff" class="h-5 w-5" />
        </button>
        <button
          type="button"
          class="flex h-11 w-11 items-center justify-center rounded-full border border-border transition-colors"
          :class="screenSharing ? 'bg-primary/20 text-primary' : 'bg-muted text-foreground hover:bg-muted/70'"
          :title="screenSharing ? 'Arrêter le partage' : 'Partager l\'écran'"
          @click="toggleScreenShare"
        >
          <MonitorUp class="h-5 w-5" />
        </button>
        <button
          type="button"
          class="flex h-11 w-11 items-center justify-center rounded-full border border-border transition-colors"
          :class="deafened ? 'bg-rose-500/20 text-rose-400' : 'bg-muted text-foreground hover:bg-muted/70'"
          :title="deafened ? 'Réactiver le son' : 'Se rendre sourd'"
          @click="toggleDeafen"
        >
          <component :is="deafened ? HeadphoneOff : Headphones" class="h-5 w-5" />
        </button>
        <button
          type="button"
          class="flex h-11 items-center gap-2 rounded-full bg-rose-500 px-5 text-sm font-medium text-white transition-colors hover:bg-rose-600"
          @click="leaveRoom"
        >
          <PhoneOff class="h-5 w-5" />
          Quitter
        </button>
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
</style>
