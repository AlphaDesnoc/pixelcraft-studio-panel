<script setup>
import {
  Headphones,
  HeadphoneOff,
  Maximize2,
  Mic,
  MicOff,
  PhoneOff,
  Video,
  VideoOff,
  Volume2,
} from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import {
  cameraEnabled,
  currentRoom,
  deafened,
  inRoom,
  leaveRoom,
  localMuted,
  meetingOpen,
  openMeeting,
  roomParticipants,
  toggleCamera,
  toggleDeafen,
  toggleMute,
} from "@/composables/useVoiceRoom.js";

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
          Salon vocal · {{ currentRoom?.label }}
        </p>
        <p class="text-[10px] text-emerald-400">
          {{ roomParticipants.length }} connecté{{ roomParticipants.length > 1 ? "s" : "" }}
        </p>
      </div>
    </div>

    <ul class="max-h-56 overflow-y-auto p-2">
      <li
        v-for="p in roomParticipants"
        :key="p.id"
        class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted/40"
      >
        <Avatar
          :src="p.avatar_url ?? ''"
          :fallback="initials(p.name)"
          size="xs"
        />
        <span class="min-w-0 flex-1 truncate text-xs text-foreground">
          {{ p.name }}
          <span v-if="p.isLocal" class="text-muted-foreground">(vous)</span>
        </span>
        <MicOff
          v-if="p.isLocal && localMuted"
          class="h-3.5 w-3.5 text-rose-400"
        />
      </li>
    </ul>

    <div class="flex items-center justify-center gap-2 border-t border-border px-3 py-2.5">
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
