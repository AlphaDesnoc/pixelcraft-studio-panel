<script setup>
defineProps({
  attachment: { type: Object, required: true },
});

const emits = defineEmits(["preview"]);
</script>

<template>
  <button
    type="button"
    class="block overflow-hidden rounded-md border border-border/60 text-left transition-opacity hover:opacity-90"
    @click="emits('preview', attachment)"
  >
    <img
      :src="attachment.url"
      :alt="attachment.original_name"
      class="max-h-48 max-w-full object-cover"
      loading="lazy"
    />
  </button>
</template>
