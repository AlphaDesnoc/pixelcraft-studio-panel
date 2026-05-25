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
  <aside
    class="flex w-44 shrink-0 flex-col border-l border-border bg-muted/10 sm:w-52"
  >
    <div class="shrink-0 border-b border-border px-3 py-2.5">
      <h3 class="text-xs font-semibold text-foreground">Membres</h3>
      <p class="mt-0.5 text-[11px] text-muted-foreground">
        <template v-if="loading && members.length === 0">Chargement…</template>
        <template v-else>
          {{ onlineCount }} en ligne · {{ members.length }} autorisé{{ members.length > 1 ? "s" : "" }}
        </template>
      </p>
    </div>

    <ul class="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2">
      <li
        v-if="loading && members.length === 0"
        class="px-1 py-4 text-center text-xs text-muted-foreground"
      >
        Chargement…
      </li>

      <li
        v-else-if="members.length === 0"
        class="px-1 py-4 text-center text-xs text-muted-foreground"
      >
        Aucun membre.
      </li>

      <li
        v-for="member in members"
        :key="member.id"
        class="flex items-center gap-2 rounded-md px-1.5 py-1.5"
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
          </p>
          <p
            class="text-[10px]"
            :class="member.is_online ? 'text-emerald-400' : 'text-muted-foreground'"
          >
            {{ member.is_online ? "En ligne" : "Hors ligne" }}
          </p>
        </div>
      </li>
    </ul>
  </aside>
</template>
