<script setup>
import ChatMediaAttachment from "@/Components/Chat/ChatMediaAttachment.vue";

defineProps({
  attachment: { type: Object, required: true },
  projectSlug: { type: String, default: "" },
});
</script>

<template>
  <ChatMediaAttachment :attachment="attachment" :project-slug="projectSlug" />
</template>
