<script setup>
defineProps({
  message: {
    type: String,
    required: false,
    default: "",
  },
});
</script>

<template>
  <p v-show="message" class="text-sm font-medium text-destructive">
    {{ message }}
  </p>
</template>
