<script setup>
import { computed } from "vue";
import { emojiToUrl } from "@/lib/twemojiRender.js";

const props = defineProps({
  emoji: { type: String, required: true },
  size: {
    type: String,
    default: "md",
    validator: (value) => ["sm", "md", "lg", "reaction"].includes(value),
  },
});

const src = computed(() => emojiToUrl(props.emoji));

const sizeClass = computed(() => {
  switch (props.size) {
    case "sm":
      return "h-4 w-4";
    case "lg":
      return "h-8 w-8";
    case "reaction":
      return "h-5 w-5";
    default:
      return "h-[1.25em] w-[1.25em]";
  }
});
</script>

<template>
  <img
    :src="src"
    :alt="emoji"
    :title="emoji"
    draggable="false"
    class="twemoji inline-block align-[-0.15em] select-none"
    :class="sizeClass"
  />
</template>
