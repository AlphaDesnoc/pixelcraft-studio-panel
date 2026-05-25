<script setup>
import { computed } from "vue";
import { Globe, Eye } from "lucide-vue-next";

const props = defineProps({
  label: { type: String, required: true },
  icon: { type: String, default: null },
  color: { type: String, default: null },
  active: { type: Boolean, default: false },
});

const IconComponent = computed(() => {
  if (props.icon === "globe") return Globe;
  if (props.icon === "eye") return Eye;
  return null;
});
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
    :class="
      active
        ? 'border-primary/50 bg-primary/15 text-primary-foreground'
        : 'border-border bg-card/40 text-muted-foreground hover:bg-card hover:text-foreground'
    "
  >
    <component
      v-if="IconComponent"
      :is="IconComponent"
      class="h-3.5 w-3.5"
      :class="active ? 'text-primary' : ''"
    />
    <span
      v-else-if="color"
      class="inline-block h-2 w-2 rounded-full"
      :style="{ backgroundColor: color }"
    />
    <span>{{ label }}</span>
  </button>
</template>
