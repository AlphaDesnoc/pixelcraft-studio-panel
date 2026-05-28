<script setup>
import { Hash } from "lucide-vue-next";

const props = defineProps({
  channels: { type: Array, default: () => [] },
  activeKey: { type: String, required: true },
});

const emit = defineEmits(["select"]);
</script>

<template>
  <aside class="flex w-44 shrink-0 flex-col border-r border-border/60 bg-muted/10">
    <div class="border-b border-border/60 px-3 py-2.5">
      <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
        Canaux
      </p>
    </div>
    <nav class="flex-1 overflow-y-auto p-2">
      <button
        v-for="channel in channels"
        :key="channel.key"
        type="button"
        class="mb-0.5 flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm transition-colors"
        :class="
          channel.key === activeKey
            ? 'bg-primary/15 font-medium text-foreground'
            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
        "
        @click="emit('select', channel.key)"
      >
        <Hash class="h-3.5 w-3.5 shrink-0" :style="channel.color ? { color: channel.color } : undefined" />
        <span class="truncate">{{ channel.label }}</span>
      </button>
    </nav>
  </aside>
</template>
