<script setup>
defineProps({
  users: { type: Array, default: () => [] },
  currentUserId: { type: Number, default: null },
  label: { type: String, default: "En ligne" },
});
</script>

<template>
  <div
    v-if="users.length > 0"
    class="flex flex-wrap items-center gap-1.5 border-b border-border bg-muted/20 px-4 py-2"
  >
    <span class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
      {{ label }} ({{ users.length }})
    </span>
    <span
      v-for="user in users"
      :key="user.id"
      class="inline-flex items-center gap-1.5 rounded-full border border-border bg-background/60 px-2 py-0.5 text-xs text-foreground"
      :class="user.id === currentUserId ? 'border-primary/30 bg-primary/10' : ''"
    >
      <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" />
      {{ user.name }}
      <span v-if="user.id === currentUserId" class="text-muted-foreground">(vous)</span>
    </span>
  </div>
</template>
