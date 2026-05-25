<script setup>
import { computed } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps({
  value: { type: Number, required: false, default: 0 },
  max: { type: Number, required: false, default: 100 },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const percent = computed(() => {
  if (props.max <= 0) return 0;
  return Math.max(0, Math.min(100, (props.value / props.max) * 100));
});
</script>

<template>
  <div
    :class="
      cn(
        'relative h-1.5 w-full overflow-hidden rounded-full bg-secondary',
        props.class,
      )
    "
    role="progressbar"
    :aria-valuenow="value"
    :aria-valuemin="0"
    :aria-valuemax="max"
  >
    <div
      class="h-full rounded-full bg-gradient-to-r from-fuchsia-500 via-violet-500 to-indigo-500 transition-[width] duration-500"
      :style="{ width: percent + '%' }"
    />
  </div>
</template>
