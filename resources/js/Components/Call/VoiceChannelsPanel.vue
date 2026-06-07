<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Hash, Plus, Trash2, Video, Volume2, X } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import {
  currentRoom,
  joinRoom,
  leaveRoom,
} from "@/composables/useVoiceRoom.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectId: { type: Number, required: true },
  voiceChannels: { type: Array, default: () => [] },
  ranks: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const channels = ref([]);

function syncFromProps() {
  channels.value = (props.voiceChannels ?? []).map((c) => ({
    ...c,
    participants: [...(c.participants ?? [])],
  }));
}
watch(() => props.voiceChannels, syncFromProps, { immediate: true, deep: true });

function isActive(channel) {
  return currentRoom.value?.channelId === channel.id;
}

async function toggleJoin(channel) {
  if (isActive(channel)) {
    await leaveRoom();
    return;
  }
  if (currentRoom.value) await leaveRoom();
  await joinRoom(props.projectSlug, channel.id, channel.name, {
    withVideo: false,
    projectId: props.projectId,
  });
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
const newRankId = ref("");

function submitCreate() {
  const name = newName.value.trim();
  if (!name) return;
  router.post(
    route("projects.voice-channels.store", props.projectSlug),
    { name, rank_id: newRankId.value ? Number(newRankId.value) : null },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["voiceChannels"],
      onSuccess: () => {
        creating.value = false;
        newName.value = "";
        newRankId.value = "";
      },
    },
  );
}

function removeChannel(channel) {
  if (!confirm(`Supprimer le salon « ${channel.name} » ?`)) return;
  router.delete(
    route("projects.voice-channels.destroy", [props.projectSlug, channel.id]),
    { preserveScroll: true, preserveState: true, only: ["voiceChannels"] },
  );
}

// ---- Présence temps réel ----
let lobby = null;

function applyMembership(evt) {
  if (!evt) return;
  // Un utilisateur n'est que dans un salon : on le retire partout d'abord.
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
  <section class="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
    <header class="flex items-center justify-between">
      <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
        <Volume2 class="h-4 w-4 text-emerald-400" />
        Salons vocaux
      </h3>
      <button
        v-if="canManage"
        type="button"
        class="inline-flex h-7 items-center gap-1 rounded-md border border-border px-2 text-xs text-muted-foreground hover:bg-muted/60 hover:text-foreground"
        @click="creating = !creating"
      >
        <Plus class="h-3.5 w-3.5" />
        Salon
      </button>
    </header>

    <div
      v-if="creating"
      class="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/20 p-2"
    >
      <input
        v-model="newName"
        type="text"
        placeholder="Nom du salon"
        class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-ring"
        @keydown.enter.prevent="submitCreate"
      />
      <select
        v-model="newRankId"
        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
      >
        <option value="">Tout le projet</option>
        <option v-for="r in ranks" :key="r.id ?? r.key" :value="r.id">
          {{ r.label ?? r.name }}
        </option>
      </select>
      <button
        type="button"
        class="inline-flex h-8 items-center rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground"
        @click="submitCreate"
      >
        Créer
      </button>
    </div>

    <p v-if="!channels.length" class="py-2 text-xs text-muted-foreground">
      Aucun salon vocal. {{ canManage ? "Créez-en un." : "" }}
    </p>

    <ul class="flex flex-col gap-1">
      <li
        v-for="channel in channels"
        :key="channel.id"
        class="rounded-lg border px-2 py-1.5 transition-colors"
        :class="isActive(channel) ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-transparent hover:bg-muted/40'"
      >
        <div class="flex items-center gap-2">
          <Hash class="h-3.5 w-3.5 text-muted-foreground" />
          <button
            type="button"
            class="min-w-0 flex-1 truncate text-left text-sm font-medium text-foreground"
            @click="toggleJoin(channel)"
          >
            {{ channel.name }}
          </button>
          <span
            v-if="channel.rank"
            class="rounded-full px-1.5 py-px text-[10px] font-medium"
            :style="{ backgroundColor: (channel.rank.color ?? '#888') + '22', color: channel.rank.color ?? '#888' }"
          >
            {{ channel.rank.name }}
          </span>
          <button
            type="button"
            class="inline-flex h-6 items-center gap-1 rounded-md px-2 text-[11px] font-medium transition-colors"
            :class="isActive(channel) ? 'bg-rose-500/15 text-rose-400' : 'bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25'"
            @click="toggleJoin(channel)"
          >
            {{ isActive(channel) ? "Quitter" : "Rejoindre" }}
          </button>
          <button
            v-if="canManage"
            type="button"
            class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-rose-400"
            title="Supprimer"
            @click="removeChannel(channel)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>

        <div
          v-if="channel.participants.length"
          class="mt-1 flex flex-wrap items-center gap-1 pl-5"
        >
          <span
            v-for="p in channel.participants"
            :key="p.id"
            class="inline-flex items-center gap-1 rounded-full bg-muted/60 py-0.5 pl-0.5 pr-1.5"
            :title="p.name"
          >
            <Avatar :src="p.avatar_url ?? ''" :fallback="initials(p.name)" size="xs" class="!h-4 !w-4 !text-[8px]" />
            <span class="text-[10px] text-foreground">{{ p.name }}</span>
          </span>
        </div>
      </li>
    </ul>
  </section>
</template>
