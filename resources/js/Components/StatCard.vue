<script setup>
import { Card } from "@/Components/ui/card";

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], required: true },
  color: {
    type: String,
    required: false,
    default: "violet",
    validator: (v) =>
      ["violet", "blue", "emerald", "rose", "amber"].includes(v),
  },
});

const palettes = {
  violet: {
    icon: "text-violet-300",
    bg: "bg-violet-500/12 ring-violet-500/20",
  },
  blue: {
    icon: "text-sky-300",
    bg: "bg-sky-500/12 ring-sky-500/20",
  },
  emerald: {
    icon: "text-emerald-300",
    bg: "bg-emerald-500/12 ring-emerald-500/20",
  },
  rose: {
    icon: "text-rose-300",
    bg: "bg-rose-500/12 ring-rose-500/20",
  },
  amber: {
    icon: "text-amber-300",
    bg: "bg-amber-500/12 ring-amber-500/20",
  },
};
</script>

<template>
  <Card class="p-4">
    <div class="flex items-center gap-2 text-xs text-muted-foreground">
      <span
        :class="[
          'flex h-6 w-6 items-center justify-center rounded-md ring-1',
          palettes[props.color].bg,
          palettes[props.color].icon,
        ]"
      >
        <slot name="icon" />
      </span>
      <span>{{ label }}</span>
    </div>
    <div class="mt-3 text-2xl font-semibold leading-tight tracking-tight">
      {{ value }}
    </div>
  </Card>
</template>
