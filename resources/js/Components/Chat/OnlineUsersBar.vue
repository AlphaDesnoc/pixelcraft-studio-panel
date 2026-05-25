<script setup>
import { computed } from "vue";

const props = defineProps({
  members: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  currentUserId: { type: Number, default: null },
  label: { type: String, default: "En ligne" },
  showOffline: { type: Boolean, default: true },
});

const resolvedMembers = computed(() => {
  if (props.members.length > 0) {
    return props.members;
  }

  return props.users.map((user) => ({
    ...user,
    is_online: true,
  }));
});

const onlineMembers = computed(() =>
  resolvedMembers.value.filter((member) => member.is_online),
);

const offlineMembers = computed(() =>
  resolvedMembers.value.filter((member) => !member.is_online),
);
</script>

<template>
  <div
    v-if="resolvedMembers.length > 0"
    class="border-b border-border bg-muted/20 px-4 py-3"
  >
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
      <span class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
        {{ label }} ({{ onlineMembers.length }})
      </span>
      <span
        v-if="onlineMembers.length === 0"
        class="text-xs text-muted-foreground"
      >
        Personne pour l'instant
      </span>
      <span
        v-for="user in onlineMembers"
        :key="user.id"
        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-background/60 px-2 py-0.5 text-xs text-foreground"
        :class="user.id === currentUserId ? 'border-primary/30 bg-primary/10' : ''"
      >
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" />
        {{ user.name }}
        <span v-if="user.id === currentUserId" class="text-muted-foreground">(vous)</span>
      </span>
    </div>

    <div
      v-if="showOffline && offlineMembers.length > 0"
      class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border/60 pt-2"
    >
      <span class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
        Peuvent participer ({{ resolvedMembers.length }})
      </span>
      <span
        v-for="user in offlineMembers"
        :key="user.id"
        class="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-background/40 px-2 py-0.5 text-xs text-muted-foreground"
      >
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-muted-foreground/40" />
        {{ user.name }}
        <span v-if="user.id === currentUserId" class="opacity-70">(vous)</span>
      </span>
    </div>
  </div>
</template>
