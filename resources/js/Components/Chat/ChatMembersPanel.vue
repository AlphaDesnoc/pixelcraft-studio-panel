<script setup>
import { computed } from "vue";

const props = defineProps({
  members: { type: Array, default: () => [] },
  currentUserId: { type: Number, default: null },
  loading: { type: Boolean, default: false },
});

const onlineCount = computed(
  () => props.members.filter((member) => member.is_online).length,
);

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}
</script>

<template>
  <div class="shrink-0 border-b border-border bg-muted/10">
    <div class="flex items-center justify-between gap-2 px-4 py-2">
      <h3 class="text-xs font-semibold text-foreground">Membres du chat</h3>
      <p class="text-[11px] text-muted-foreground">
        <template v-if="loading && members.length === 0">Chargement…</template>
        <template v-else>
          {{ onlineCount }} en ligne · {{ members.length }} autorisé{{ members.length > 1 ? "s" : "" }}
        </template>
      </p>
    </div>

    <ul class="max-h-28 space-y-0.5 overflow-y-auto px-2 pb-2">
      <li
        v-if="loading && members.length === 0"
        class="px-2 py-3 text-center text-xs text-muted-foreground"
      >
        Chargement des membres…
      </li>

      <li
        v-else-if="members.length === 0"
        class="px-2 py-3 text-center text-xs text-muted-foreground"
      >
        Aucun membre autorisé sur ce chat.
      </li>

      <li
        v-for="member in members"
        :key="member.id"
        class="flex items-center gap-2 rounded-md px-2 py-1.5"
        :class="member.id === currentUserId ? 'bg-primary/10' : 'hover:bg-muted/40'"
      >
        <div class="relative shrink-0">
          <div
            class="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-muted-foreground"
          >
            {{ initials(member.name) }}
          </div>
          <span
            class="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full border border-card"
            :class="member.is_online ? 'bg-emerald-400' : 'bg-muted-foreground/40'"
          />
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-medium text-foreground">
            {{ member.name }}
            <span v-if="member.id === currentUserId" class="font-normal text-muted-foreground">
              (vous)
            </span>
          </p>
        </div>

        <span
          class="shrink-0 text-[10px]"
          :class="member.is_online ? 'text-emerald-400' : 'text-muted-foreground'"
        >
          {{ member.is_online ? "En ligne" : "Hors ligne" }}
        </span>
      </li>
    </ul>
  </div>
</template>
