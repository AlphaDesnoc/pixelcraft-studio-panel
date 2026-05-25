<script setup>
import { DialogRoot } from "reka-ui";

const props = defineProps({
  open: { type: Boolean, required: false, default: undefined },
});

const emits = defineEmits(["update:open"]);
</script>

<template>
  <DialogRoot :open="props.open" @update:open="(v) => emits('update:open', v)">
    <slot />
  </DialogRoot>
</template>
