<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  variant: {
    type: String,
    required: false,
    default: "default",
    validator: (v) =>
      [
        "default",
        "secondary",
        "success",
        "warning",
        "destructive",
        "outline",
      ].includes(v),
  },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const variants = {
  default:
    "border-transparent bg-primary/15 text-primary-foreground/90 text-primary",
  secondary: "border-transparent bg-muted text-muted-foreground",
  success: "border-emerald-500/25 bg-emerald-500/12 text-emerald-300",
  warning: "border-amber-500/25 bg-amber-500/12 text-amber-300",
  destructive: "border-rose-500/25 bg-rose-500/12 text-rose-300",
  outline: "border-border bg-transparent text-foreground/80",
};
</script>

<template>
  <span
    :class="
      cn(
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium leading-none',
        variants[props.variant],
        props.class,
      )
    "
  >
    <slot />
  </span>
</template>
