<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "#7c5cff" },
  label: { type: String, default: "Couleur" },
  triggerLabel: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const value = computed({
  get: () => props.modelValue,
  set: (v) => emit("update:modelValue", v),
});

const inputRef = ref(null);

function openPicker() {
  inputRef.value?.click();
}
</script>

<template>
  <div class="inline-flex items-center gap-2">
    <button
      v-if="triggerLabel"
      type="button"
      class="inline-flex items-center gap-2 rounded-md border border-input bg-background/40 px-2.5 py-1.5 text-xs text-foreground transition-colors hover:bg-muted/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      :aria-label="label"
      @click="openPicker"
    >
      <span
        class="h-4 w-4 shrink-0 rounded-sm border border-border/60"
        :style="{ backgroundColor: value }"
      />
      <span>{{ triggerLabel }}</span>
    </button>

    <template v-else>
      <button
        type="button"
        class="relative h-9 w-9 shrink-0 overflow-hidden rounded-md border-2 border-border shadow-inner transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :style="{ backgroundColor: value }"
        :aria-label="label"
        @click="openPicker"
      />
      <span class="font-mono text-xs uppercase text-muted-foreground">
        {{ value }}
      </span>
    </template>

    <input
      ref="inputRef"
      v-model="value"
      type="color"
      class="sr-only"
      tabindex="-1"
    />
  </div>
</template>
