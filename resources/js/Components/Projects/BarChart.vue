<script setup>
import { computed } from "vue";

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  height: { type: Number, default: 200 },
  color: { type: String, default: "hsl(var(--primary))" },
});

const padding = { top: 12, right: 12, bottom: 28, left: 32 };

const maxValue = computed(() => {
  const m = Math.max(0, ...props.data.map((d) => Number(d.count) || 0));
  return m < 4 ? 4 : Math.ceil(m * 1.1);
});

const yTicks = computed(() => {
  const m = maxValue.value;
  const step = m / 4;
  return [0, step, step * 2, step * 3, m].map((v) => Math.round(v));
});

const innerHeight = computed(() => props.height - padding.top - padding.bottom);

const bars = computed(() => {
  if (props.data.length === 0) return [];
  return props.data.map((d, i) => {
    const value = Number(d.count) || 0;
    const h = maxValue.value > 0 ? (value / maxValue.value) * innerHeight.value : 0;
    return {
      label: d.label,
      value,
      heightPx: h,
      index: i,
    };
  });
});
</script>

<template>
  <div class="w-full" :style="{ height: `${height}px` }">
    <svg
      class="h-full w-full overflow-visible"
      preserveAspectRatio="none"
      viewBox="0 0 400 200"
    >
      <line
        v-for="(tick, i) in yTicks"
        :key="`grid-${i}`"
        :x1="padding.left"
        :x2="400 - padding.right"
        :y1="padding.top + innerHeight - (tick / maxValue) * innerHeight"
        :y2="padding.top + innerHeight - (tick / maxValue) * innerHeight"
        stroke="hsl(var(--border))"
        stroke-dasharray="3 3"
        stroke-width="1"
        vector-effect="non-scaling-stroke"
      />

      <text
        v-for="(tick, i) in yTicks"
        :key="`y-${i}`"
        :x="padding.left - 6"
        :y="padding.top + innerHeight - (tick / maxValue) * innerHeight + 3"
        text-anchor="end"
        class="fill-muted-foreground text-[9px]"
      >
        {{ tick }}
      </text>

      <g v-for="bar in bars" :key="`bar-${bar.index}`">
        <rect
          :x="
            padding.left
            + ((400 - padding.left - padding.right) / bars.length) * bar.index
            + ((400 - padding.left - padding.right) / bars.length) * 0.18
          "
          :y="padding.top + innerHeight - bar.heightPx"
          :width="((400 - padding.left - padding.right) / bars.length) * 0.64"
          :height="Math.max(0, bar.heightPx)"
          :fill="color"
          rx="3"
          class="opacity-90"
        />
        <text
          :x="
            padding.left
            + ((400 - padding.left - padding.right) / bars.length) * (bar.index + 0.5)
          "
          :y="200 - 10"
          text-anchor="middle"
          class="fill-muted-foreground text-[10px]"
        >
          {{ bar.label }}
        </text>
      </g>
    </svg>
  </div>
</template>
