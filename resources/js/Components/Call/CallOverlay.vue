<script setup>
import { computed, ref, watch } from "vue";
import { Mic, MicOff, Phone, PhoneOff, Video, VideoOff } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import {
  acceptCall,
  callRole,
  callStatus,
  cameraOff,
  currentCall,
  declineCall,
  hangup,
  isMuted,
  localStream,
  remoteStream,
  toggleCamera,
  toggleMute,
} from "@/composables/useCall.js";

const localVideo = ref(null);
const remoteVideo = ref(null);

watch(localStream, (stream) => {
  if (localVideo.value) localVideo.value.srcObject = stream ?? null;
});
watch(remoteStream, (stream) => {
  if (remoteVideo.value) remoteVideo.value.srcObject = stream ?? null;
});

const peer = computed(() => {
  if (!currentCall.value) return null;
  return callRole.value === "caller"
    ? currentCall.value.callee
    : currentCall.value.caller;
});

const peerInitials = computed(() => {
  const name = peer.value?.name ?? "?";
  return name
    .split(" ")
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
});

const withVideo = computed(() => Boolean(currentCall.value?.with_video));

const statusLabel = computed(() => {
  switch (callStatus.value) {
    case "outgoing":
      return "Appel en cours…";
    case "incoming":
      return withVideo.value ? "Appel vidéo entrant" : "Appel entrant";
    case "connecting":
      return "Connexion…";
    case "active":
      return "En communication";
    case "ended":
      return "Appel terminé";
    default:
      return "";
  }
});

const showIncoming = computed(() => callStatus.value === "incoming");
const showWindow = computed(() =>
  ["outgoing", "connecting", "active", "ended"].includes(callStatus.value),
);
</script>

<template>
  <!-- Fenêtre d'appel sortant / actif -->
  <div
    v-if="showWindow"
    class="fixed bottom-4 right-4 z-[120] w-[340px] overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
  >
    <div class="relative aspect-video bg-black">
      <video
        v-show="withVideo && callStatus === 'active'"
        ref="remoteVideo"
        autoplay
        playsinline
        class="h-full w-full object-cover"
      />
      <div
        v-if="!(withVideo && callStatus === 'active')"
        class="flex h-full flex-col items-center justify-center gap-3 text-center"
      >
        <Avatar
          :src="peer?.avatar_url ?? ''"
          :fallback="peerInitials"
          size="xl"
          class="ring-2 ring-white/20"
        />
        <div>
          <p class="text-sm font-semibold text-white">{{ peer?.name }}</p>
          <p class="text-xs text-white/60">{{ statusLabel }}</p>
        </div>
      </div>

      <video
        v-show="withVideo"
        ref="localVideo"
        autoplay
        playsinline
        muted
        class="absolute bottom-2 right-2 h-20 w-28 rounded-lg border border-white/20 object-cover shadow-lg"
      />

      <span
        v-if="callStatus === 'active'"
        class="absolute left-2 top-2 rounded-full bg-black/50 px-2 py-0.5 text-[11px] font-medium text-white"
      >
        {{ peer?.name }}
      </span>
    </div>

    <div class="flex items-center justify-center gap-3 px-4 py-3">
      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full border border-border transition-colors"
        :class="isMuted ? 'bg-rose-500/20 text-rose-400' : 'bg-muted text-foreground hover:bg-muted/70'"
        :title="isMuted ? 'Réactiver le micro' : 'Couper le micro'"
        @click="toggleMute"
      >
        <component :is="isMuted ? MicOff : Mic" class="h-4 w-4" />
      </button>

      <button
        v-if="withVideo"
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full border border-border transition-colors"
        :class="cameraOff ? 'bg-rose-500/20 text-rose-400' : 'bg-muted text-foreground hover:bg-muted/70'"
        :title="cameraOff ? 'Activer la caméra' : 'Couper la caméra'"
        @click="toggleCamera"
      >
        <component :is="cameraOff ? VideoOff : Video" class="h-4 w-4" />
      </button>

      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-500 text-white transition-colors hover:bg-rose-600"
        title="Raccrocher"
        @click="hangup"
      >
        <PhoneOff class="h-4 w-4" />
      </button>
    </div>
  </div>

  <!-- Prompt d'appel entrant -->
  <Transition name="call-pop">
    <div
      v-if="showIncoming"
      class="fixed bottom-4 right-4 z-[130] w-[320px] overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
    >
      <div class="flex items-center gap-3 p-4">
        <Avatar
          :src="peer?.avatar_url ?? ''"
          :fallback="peerInitials"
          size="lg"
          class="animate-pulse ring-2 ring-primary/40"
        />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-foreground">
            {{ peer?.name }}
          </p>
          <p class="text-xs text-muted-foreground">{{ statusLabel }}</p>
        </div>
      </div>
      <div class="flex border-t border-border">
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-2 py-3 text-sm font-medium text-rose-400 transition-colors hover:bg-rose-500/10"
          @click="declineCall"
        >
          <PhoneOff class="h-4 w-4" />
          Refuser
        </button>
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-2 border-l border-border py-3 text-sm font-medium text-emerald-400 transition-colors hover:bg-emerald-500/10"
          @click="acceptCall"
        >
          <Phone class="h-4 w-4" />
          Répondre
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.call-pop-enter-active,
.call-pop-leave-active {
  transition: all 0.2s ease;
}
.call-pop-enter-from,
.call-pop-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
