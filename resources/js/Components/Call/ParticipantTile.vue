<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { MicOff } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";

const props = defineProps({
  participant: { type: Object, required: true },
  // Piste à afficher : caméra par défaut, ou partage d'écran si fourni.
  screen: { type: Boolean, default: false },
});

const videoEl = ref(null);
let attached = null;

function currentTrack() {
  return props.screen ? props.participant.screenTrack : props.participant.camTrack;
}

function sync() {
  const track = currentTrack();
  if (attached && attached !== track) {
    try {
      attached.detach(videoEl.value);
    } catch (e) {
      /* noop */
    }
    attached = null;
  }
  if (track && videoEl.value && attached !== track) {
    track.attach(videoEl.value);
    attached = track;
  }
}

watch(() => [props.participant.camTrack, props.participant.screenTrack], () => sync());
onMounted(sync);
onBeforeUnmount(() => {
  if (attached) {
    try {
      attached.detach(videoEl.value);
    } catch (e) {
      /* noop */
    }
  }
});

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
    class="relative flex aspect-video items-center justify-center overflow-hidden rounded-xl border bg-black/60"
    :class="participant.speaking ? 'border-emerald-400 ring-2 ring-emerald-400/50' : 'border-border'"
  >
    <video
      v-show="screen ? participant.screenTrack : participant.camTrack"
      ref="videoEl"
      autoplay
      playsinline
      :muted="participant.isLocal"
      class="h-full w-full"
      :class="[
        screen ? 'object-contain' : 'object-cover',
        !screen && participant.isLocal ? '-scale-x-100' : '',
      ]"
    />

    <div
      v-if="!(screen ? participant.screenTrack : participant.camTrack)"
      class="flex flex-col items-center gap-2"
    >
      <Avatar
        :src="participant.avatar_url ?? ''"
        :fallback="initials(participant.name)"
        size="xl"
      />
    </div>

    <div class="absolute bottom-2 left-2 flex items-center gap-1.5 rounded-full bg-black/55 px-2 py-0.5">
      <MicOff v-if="!screen && !participant.micOn" class="h-3 w-3 text-rose-400" />
      <span class="text-[11px] font-medium text-white">
        {{ participant.name }}<span v-if="screen"> · écran</span>
        <span v-if="participant.isLocal && !screen" class="text-white/60">(vous)</span>
      </span>
    </div>
  </div>
</template>
