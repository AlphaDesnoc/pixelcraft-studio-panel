<script setup>
defineProps({
  showText: { type: Boolean, default: true },
});
</script>

<template>
  <div class="flex flex-col items-center gap-1.5 select-none">
    <img
      src="/images/logo.png"
      alt="PixelCraft Studio"
      class="h-12 w-auto object-contain"
    />

    <div
      v-if="showText"
      class="text-center font-semibold tracking-[0.18em] uppercase"
    >
      <span
        class="bg-gradient-to-r from-fuchsia-400 via-violet-400 to-indigo-400 bg-clip-text text-[10px] text-transparent"
      >
        Pixelcraft
      </span>
      <span
        class="ml-1 bg-gradient-to-r from-fuchsia-400 via-violet-400 to-indigo-400 bg-clip-text text-[10px] text-transparent"
      >
        Studios
      </span>
    </div>
  </div>
</template>
