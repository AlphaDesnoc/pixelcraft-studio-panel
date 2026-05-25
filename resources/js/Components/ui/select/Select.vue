<script setup>
import { computed } from "vue";
import { ChevronDown } from "lucide-vue-next";
import { cn } from "@/lib/utils";

const props = defineProps({
  modelValue: { type: [String, Number, null], required: false, default: "" },
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
  <div class="relative">
    <select
      v-model="value"
      :class="
        cn(
          'flex h-10 w-full appearance-none rounded-md border border-input bg-background/40 px-3 pr-9 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
          props.class,
        )
      "
    >
      <slot />
    </select>
    <ChevronDown
      class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
    />
  </div>
</template>
