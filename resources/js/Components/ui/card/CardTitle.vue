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
  <h3
    :class="
      cn('text-lg font-semibold leading-tight tracking-tight', props.class)
    "
  >
    <slot />
  </h3>
</template>
