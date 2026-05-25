<script setup>
import { Label } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  for: { type: String, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <Label
    :for="props.for"
    :class="
      cn(
        'text-sm font-medium leading-none text-foreground/80 peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
        props.class,
      )
    "
  >
    <slot />
  </Label>
</template>
