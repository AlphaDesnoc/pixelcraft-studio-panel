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
    class="flex w-full shrink-0 flex-col border-t border-border bg-muted/10 md:w-56 md:border-l md:border-t-0"
  >
    <div class="border-b border-border px-4 py-3">
      <h3 class="text-sm font-semibold text-foreground">Membres</h3>
      <p class="mt-0.5 text-xs text-muted-foreground">
        <template v-if="loading">Chargement…</template>
        <template v-else>
          {{ onlineCount }} en ligne · {{ members.length }} autorisé{{ members.length > 1 ? "s" : "" }}
        </template>
      </p>
    </div>

    <ul class="max-h-[320px] flex-1 space-y-0.5 overflow-y-auto p-2 md:max-h-none">
      <li
        v-if="loading && members.length === 0"
        class="px-2 py-6 text-center text-xs text-muted-foreground"
      >
        Chargement des membres…
      </li>

      <li
        v-else-if="members.length === 0"
        class="px-2 py-6 text-center text-xs text-muted-foreground"
      >
        Aucun membre autorisé sur ce chat.
      </li>

      <li
        v-for="member in members"
        :key="member.id"
        class="flex items-center gap-2.5 rounded-lg px-2 py-2"
        :class="member.id === currentUserId ? 'bg-primary/10' : 'hover:bg-muted/40'"
      >
        <div class="relative shrink-0">
          <div
            class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
          >
            {{ initials(member.name) }}
          </div>
          <span
            class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-card"
            :class="member.is_online ? 'bg-emerald-400' : 'bg-muted-foreground/40'"
          />
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-foreground">
            {{ member.name }}
            <span v-if="member.id === currentUserId" class="font-normal text-muted-foreground">
              (vous)
            </span>
          </p>
          <p class="text-[11px] text-muted-foreground">
            {{ member.is_online ? "En ligne" : "Hors ligne" }}
          </p>
        </div>
      </li>
    </ul>
  </aside>
</template>
