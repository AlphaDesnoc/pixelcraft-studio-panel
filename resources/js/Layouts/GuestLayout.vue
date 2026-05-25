<script setup>
import ApplicationLogo from "@/Components/ApplicationLogo.vue";

defineProps({
  tagline: {
    type: String,
    default: "Connectez-vous à votre espace",
  },
});
</script>

<template>
  <div
    class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-background px-4 py-10"
  >
    <div
      class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_50%_-10%,hsl(244_75%_67%/0.18),transparent_55%)]"
    />
    <div
      class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_50%_120%,hsl(280_75%_60%/0.12),transparent_60%)]"
    />

    <div class="flex w-full max-w-sm flex-col items-center gap-8">
      <div class="flex flex-col items-center gap-3">
        <ApplicationLogo class="[&_img]:h-40" :show-text="false"/>
        <p class="text-sm text-muted-foreground">
          {{ tagline }}
        </p>
      </div>

      <div class="w-full">
        <slot />
      </div>
    </div>
  </div>
</template>
