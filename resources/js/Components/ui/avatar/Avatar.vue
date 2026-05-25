<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  src: { type: String, required: false, default: "" },
  alt: { type: String, required: false, default: "" },
  fallback: { type: String, required: false, default: "" },
  size: {
    type: String,
    required: false,
    default: "md",
    validator: (v) => ["xs", "sm", "md", "lg", "xl"].includes(v),
  },
  rounded: {
    type: String,
    required: false,
    default: "full",
    validator: (v) => ["md", "lg", "full"].includes(v),
  },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const sizes = {
  xs: "h-6 w-6 text-[10px]",
  sm: "h-8 w-8 text-xs",
  md: "h-10 w-10 text-sm",
  lg: "h-12 w-12 text-base",
  xl: "h-16 w-16 text-lg",
};

const radii = {
  md: "rounded-md",
  lg: "rounded-lg",
  full: "rounded-full",
};
</script>

<template>
  <span
    :class="
      cn(
        'relative inline-flex shrink-0 select-none items-center justify-center overflow-hidden bg-muted text-muted-foreground',
        sizes[props.size],
        radii[props.rounded],
        props.class,
      )
    "
  >
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      class="h-full w-full object-cover"
      loading="lazy"
    />
    <span
      v-else
      class="flex h-full w-full items-center justify-center bg-gradient-to-br from-fuchsia-500/30 via-violet-500/30 to-indigo-500/30 font-semibold text-foreground/90"
    >
      {{ fallback }}
    </span>
  </span>
</template>
