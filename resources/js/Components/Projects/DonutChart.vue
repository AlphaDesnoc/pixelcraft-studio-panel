<script setup>
import { computed } from "vue";

const props = defineProps({
  value: { type: Number, default: 0 },
  size: { type: Number, default: 180 },
  stroke: { type: Number, default: 14 },
  label: { type: String, default: "" },
});

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const clamped = computed(() => Math.max(0, Math.min(100, props.value)));
const dashOffset = computed(
  () => circumference.value - (clamped.value / 100) * circumference.value,
);
</script>

<template>
  <div
    class="relative inline-flex items-center justify-center"
    :style="{ width: `${size}px`, height: `${size}px` }"
  >
    <svg
      :width="size"
      :height="size"
      :viewBox="`0 0 ${size} ${size}`"
      class="-rotate-90"
    >
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        stroke="hsl(var(--muted))"
        :stroke-width="stroke"
      />
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        stroke="hsl(var(--primary))"
        :stroke-width="stroke"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        class="transition-[stroke-dashoffset] duration-700 ease-out"
      />
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
      <span class="text-3xl font-semibold tracking-tight">{{ clamped }}%</span>
      <span v-if="label" class="mt-0.5 text-xs text-muted-foreground">
        {{ label }}
      </span>
    </div>
  </div>
</template>
