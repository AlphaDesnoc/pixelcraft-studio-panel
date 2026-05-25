<script setup>
import { DialogTitle } from "reka-ui";
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
  <DialogTitle
    :class="
      cn(
        'text-base font-semibold leading-tight tracking-tight',
        props.class,
      )
    "
  >
    <slot />
  </DialogTitle>
</template>
