<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <div
    :class="
      cn(
        'rounded-xl border border-border/60 bg-card text-card-foreground shadow-2xl shadow-black/30',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
