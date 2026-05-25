<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], required: true },
  icon: { type: Object, default: null },
  tint: {
    type: String,
    default: "default",
  },
});

const tintMap = {
  default: "text-muted-foreground",
  primary: "text-primary",
  success: "text-emerald-400",
  warning: "text-amber-400",
  danger: "text-rose-400",
  info: "text-sky-400",
};
</script>

<template>
  <div class="rounded-lg border border-border bg-card/60 p-3">
    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
      <component
        v-if="icon"
        :is="icon"
        class="h-3.5 w-3.5"
        :class="tintMap[tint]"
      />
      <span>{{ label }}</span>
    </div>
    <p class="mt-1.5 text-2xl font-semibold tracking-tight">{{ value }}</p>
  </div>
</template>
