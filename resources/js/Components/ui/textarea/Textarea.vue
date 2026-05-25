<script setup>
import { computed } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps({
  modelValue: { type: [String, Number], required: false, default: "" },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const emits = defineEmits(["update:modelValue"]);

const value = computed({
  get: () => props.modelValue,
  set: (v) => emits("update:modelValue", v),
});
</script>

<template>
  <textarea
    v-model="value"
    :class="
      cn(
        'flex min-h-[88px] w-full rounded-md border border-input bg-background/40 px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
